import 'dart:async';

import 'package:flutter/material.dart';
import 'package:dio/dio.dart';
import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/storage/storage_service.dart';
import '../../../data/models/devis_model.dart';
import '../../../data/models/payment_model.dart';
import '../../../data/models/supplier_model.dart';
import '../../../data/models/supplier_product_model.dart';
import '../../../data/repositories/devis_repository.dart';
import '../../../data/repositories/payment_repository.dart';
import '../../../data/repositories/supplier_catalog_repository.dart';

class DevisController extends GetxController {
  final DevisRepository _repo = DevisRepository();
  final PaymentRepository _paymentRepo = PaymentRepository();
  final SupplierCatalogRepository _catalogRepo = SupplierCatalogRepository();

  final devisList = <DevisModel>[].obs;
  final currentDevis = Rx<DevisModel?>(null);
  final isLoading = false.obs;
  final isSubmitting = false.obs;
  final isCheckingPayment = false.obs;
  final errorMsg = Rx<String?>(null);
  final suppliers = <SupplierModel>[].obs;
  final supplierProducts = <SupplierProductModel>[].obs;
  final selectedSupplier = Rx<SupplierModel?>(null);
  final isSuppliersLoading = false.obs;
  final isCatalogLoading = false.obs;

  final pendingTransactionId = RxnInt();
  final pendingDevisId = RxnInt();
  final pendingPaymentUrl = RxnString();
  final pendingLaunchUrl = RxnString();
  final pendingProvider = RxnString();
  final pendingVirementInstructions = Rxn<VirementInstructionsModel>();

  // Données pour la création de devis (artisan)
  final lignes = <DevisLigne>[].obs;
  final jalons = <DevisJalon>[].obs;

  // Mission associée
  int? missionId;

  void prepareDraftForMission(int id) {
    if (missionId == id &&
        (lignes.isNotEmpty ||
            jalons.isNotEmpty ||
            selectedSupplier.value != null)) {
      return;
    }

    missionId = id;
    currentDevis.value = null;
    errorMsg.value = null;
    selectedSupplier.value = null;
    supplierProducts.clear();
    lignes.clear();
    jalons.clear();
  }

  final isAiLoading = false.obs;

  Future<void> fetchAiSuggestion() async {
    if (missionId == null) return;
    isAiLoading.value = true;
    try {
      final suggestion = await _repo.getDevisSuggestion(missionId!);
      
      final List<dynamic> suggestedLignes = suggestion['lignes'] ?? [];
      lignes.value = suggestedLignes.map((l) => DevisLigne.fromJson(l as Map<String, dynamic>)).toList();
      
      final List<dynamic> suggestedJalons = suggestion['jalons'] ?? [];
      jalons.value = suggestedJalons.map((j) => DevisJalon.fromJson(j as Map<String, dynamic>)).toList();

      Get.snackbar(
        'Assistant IA',
        'Suggestion de devis générée avec succès !',
        snackPosition: SnackPosition.TOP,
        backgroundColor: const Color(0xFFD1FAE5),
        colorText: const Color(0xFF065F46),
      );
    } catch (e) {
      Get.snackbar(
        'Erreur Assistant IA',
        'Impossible d\'obtenir la suggestion de l\'assistant.',
        snackPosition: SnackPosition.TOP,
        backgroundColor: const Color(0xFFFEE2E2),
        colorText: const Color(0xFF991B1B),
      );
    } finally {
      isAiLoading.value = false;
    }
  }

  List<DevisLigne> get laborLines =>
      lignes.where((ligne) => ligne.type == 'mo').toList();

  List<DevisLigne> get materialLines =>
      lignes.where((ligne) => ligne.type == 'mat').toList();

  Future<void> loadSuppliers({bool forceRefresh = false}) async {
    if (!forceRefresh && suppliers.isNotEmpty) {
      return;
    }

    isSuppliersLoading.value = true;
    try {
      suppliers.value = await _catalogRepo.getApprovedSuppliers();
    } catch (e) {
      _showErrorSnackbar('Impossible de charger les fournisseurs partenaires.');
    } finally {
      isSuppliersLoading.value = false;
    }
  }

  Future<void> selectSupplier(SupplierModel? supplier) async {
    final previousSupplierId = selectedSupplier.value?.id;
    selectedSupplier.value = supplier;

    if (supplier == null) {
      supplierProducts.clear();
      _clearMaterialLines();
      return;
    }

    if (previousSupplierId != supplier.id) {
      _clearMaterialLines();
    }

    await loadSupplierProducts(supplier.id);
  }

  void importArtisanCart(List<Map<String, dynamic>> cartLines) {
    final List<DevisLigne> labor = laborLines;
    lignes.assignAll(labor);

    for (final line in cartLines) {
      lignes.add(DevisLigne(
        type: 'mat',
        description: line['description'] as String,
        montant: line['montant'] as int,
        unitPrice: line['unit_price'] as int,
        quantity: line['quantity'] as int,
        source: 'catalog',
        supplierProductId: line['supplier_product_id'] as int,
      ));
    }
  }

  Future<void> loadSupplierProducts(int supplierId) async {
    isCatalogLoading.value = true;
    try {
      supplierProducts.value =
          await _catalogRepo.getSupplierProducts(supplierId);
    } catch (e) {
      supplierProducts.clear();
      _showErrorSnackbar('Impossible de charger le catalogue du fournisseur.');
    } finally {
      isCatalogLoading.value = false;
    }
  }

  void addCatalogProduct(SupplierProductModel product) {
    if (selectedSupplier.value == null) {
      _showErrorSnackbar('Choisissez d\'abord une quincaillerie partenaire.');
      return;
    }

    final index = lignes.indexWhere(
      (ligne) => ligne.type == 'mat' && ligne.supplierProductId == product.id,
    );

    if (index >= 0) {
      final existing = lignes[index];
      final nextQuantity = existing.resolvedQuantity + 1;
      lignes[index] = DevisLigne(
        type: 'mat',
        description: existing.description,
        montant: nextQuantity * existing.resolvedUnitPrice,
        source: existing.source,
        quantity: nextQuantity,
        unitPrice: existing.resolvedUnitPrice,
        sku: existing.sku,
        supplierProductId: existing.supplierProductId,
      );
      return;
    }

    addLigne(
      type: 'mat',
      description: product.name,
      montant: product.unitPrice,
      source: 'catalog',
      quantity: 1,
      unitPrice: product.unitPrice,
      sku: product.sku,
      supplierProductId: product.id,
    );
  }

  void addCustomMaterial({
    required String description,
    required int quantity,
    required int unitPrice,
    String? sku,
  }) {
    if (selectedSupplier.value == null) {
      _showErrorSnackbar('Choisissez d\'abord une quincaillerie partenaire.');
      return;
    }

    addLigne(
      type: 'mat',
      description: description,
      montant: quantity * unitPrice,
      source: 'custom',
      quantity: quantity,
      unitPrice: unitPrice,
      sku: sku,
    );
  }

  void removeLigneItem(DevisLigne ligne) {
    lignes.remove(ligne);
  }

  int quantityForProduct(int productId) {
    for (final ligne in materialLines) {
      if (ligne.supplierProductId == productId) {
        return ligne.resolvedQuantity;
      }
    }
    return 0;
  }

  void _clearMaterialLines() {
    lignes.removeWhere((ligne) => ligne.type == 'mat');
  }

  bool hasPendingPaymentFor(int devisId) =>
      pendingTransactionId.value != null && pendingDevisId.value == devisId;

  bool get canReopenPendingPayment =>
      (pendingLaunchUrl.value ?? pendingPaymentUrl.value)?.isNotEmpty ?? false;

  /// Initialise le controller avec les données de la mission
  void initializeWithMission(int id) {
    missionId = id;
    loadMissionDevis(id);
  }

  /// Charge tous les devis d'une mission
  Future<void> loadMissionDevis(int missionId) async {
    isLoading.value = true;
    errorMsg.value = null;

    try {
      final result = await _repo.getMissionDevis(missionId);
      devisList.value = result;

      if (result.isNotEmpty) {
        currentDevis.value = result.first;
      }

      errorMsg.value = null;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);
      _showErrorSnackbar(errorMsg.value!);
    } catch (_) {
      errorMsg.value = 'Impossible de charger les devis';
      _showErrorSnackbar(errorMsg.value!);
    } finally {
      isLoading.value = false;
    }
  }

  /// Charge un devis spécifique
  Future<void> loadDevis(int id) async {
    isLoading.value = true;
    errorMsg.value = null;

    try {
      final result = await _repo.getDevis(id);
      currentDevis.value = result;
      errorMsg.value = null;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);
      _showErrorSnackbar(errorMsg.value!);
    } catch (_) {
      errorMsg.value = 'Impossible de charger le devis';
      _showErrorSnackbar(errorMsg.value!);
    } finally {
      isLoading.value = false;
    }
  }

  /// Ajoute une ligne au devis (artisan)
  void addLigne({
    required String type,
    required String description,
    required int montant,
    String? source,
    int? quantity,
    int? unitPrice,
    String? sku,
    int? supplierProductId,
  }) {
    lignes.add(
      DevisLigne(
        type: type,
        description: description,
        montant: montant,
        source: source,
        quantity: quantity,
        unitPrice: unitPrice,
        sku: sku,
        supplierProductId: supplierProductId,
      ),
    );
  }

  /// Supprime une ligne du devis
  void removeLigne(int index) {
    if (index >= 0 && index < lignes.length) {
      lignes.removeAt(index);
    }
  }

  /// Ajoute un jalon au devis (artisan)
  void addJalon({
    required String description,
    required int montant,
    required String dateCible,
  }) {
    jalons.add(
      DevisJalon(
        ordre: jalons.length + 1,
        description: description,
        montant: montant,
        dateCible: dateCible,
      ),
    );
  }

  /// Supprime un jalon du devis
  void removeJalon(int index) {
    if (index >= 0 && index < jalons.length) {
      jalons.removeAt(index);

      for (int i = 0; i < jalons.length; i++) {
        jalons[i] = DevisJalon(
          ordre: i + 1,
          description: jalons[i].description,
          montant: jalons[i].montant,
          dateCible: jalons[i].dateCible,
        );
      }
    }
  }

  /// Calcule le montant total des lignes main d'œuvre
  int get totalMo =>
      lignes.where((l) => l.type == 'mo').fold(0, (sum, l) => sum + l.montant);

  /// Calcule le montant total des lignes matériaux
  int get totalMat =>
      lignes.where((l) => l.type == 'mat').fold(0, (sum, l) => sum + l.montant);

  /// Calcule le montant total général
  int get totalGeneral => totalMo + totalMat;

  /// Calcule le ratio matériaux/total (0.0 à 1.0)
  double get ratioMateriaux {
    if (totalGeneral == 0) return 0.0;
    return totalMat / totalGeneral;
  }

  /// Valide que le devis peut être soumis
  bool validateDevis() {
    errorMsg.value = null;

    if (lignes.isEmpty) {
      errorMsg.value = 'Ajoutez au moins une ligne au devis';
      return false;
    }

    if (materialLines.isNotEmpty && selectedSupplier.value == null) {
      errorMsg.value =
          'Sélectionnez un fournisseur partenaire pour les matériaux du devis';
      return false;
    }

    if (jalons.isEmpty) {
      errorMsg.value = 'Ajoutez au moins un jalon au devis';
      return false;
    }

    final totalJalons = jalons.fold(0, (sum, j) => sum + j.montant);
    if (totalJalons != totalGeneral) {
      errorMsg.value =
          'La somme des jalons (${_formatFCFA(totalJalons)}) doit être égale au total général (${_formatFCFA(totalGeneral)})';
      return false;
    }

    return true;
  }

  /// Crée un nouveau devis (artisan)
  Future<bool> createDevis({required int missionId}) async {
    if (!validateDevis()) {
      _showErrorSnackbar(errorMsg.value!);
      return false;
    }

    isSubmitting.value = true;
    errorMsg.value = null;

    try {
      final devis = await _repo.createDevis(
        missionId: missionId,
        lignes: lignes.toList(),
        jalons: jalons.toList(),
      );

      currentDevis.value = devis;

      return true;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);
      _showErrorSnackbar('Erreur lors de la création: ${errorMsg.value}');
      return false;
    } catch (_) {
      errorMsg.value = 'Impossible de créer le devis';
      _showErrorSnackbar(errorMsg.value!);
      return false;
    } finally {
      isSubmitting.value = false;
    }
  }

  /// Met à jour un devis existant (artisan)
  Future<bool> updateDevis(int devisId) async {
    if (!validateDevis()) {
      _showErrorSnackbar(errorMsg.value!);
      return false;
    }

    isSubmitting.value = true;
    errorMsg.value = null;

    try {
      final devis = await _repo.updateDevis(
        id: devisId,
        lignes: lignes.toList(),
        jalons: jalons.toList(),
      );

      currentDevis.value = devis;

      Get.snackbar(
        'Devis modifié',
        'Les modifications ont été envoyées au client',
        snackPosition: SnackPosition.TOP,
        duration: const Duration(seconds: 3),
      );

      return true;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);
      _showErrorSnackbar('Erreur lors de la modification: ${errorMsg.value}');
      return false;
    } catch (_) {
      errorMsg.value = 'Impossible de modifier le devis';
      _showErrorSnackbar(errorMsg.value!);
      return false;
    } finally {
      isSubmitting.value = false;
    }
  }

  /// Initie le paiement puis finalise l'acceptation du devis si le paiement est confirmé.
  Future<bool> acceptDevis(int devisId, {String provider = 'wave', String paymentType = 'total'}) async {
    isSubmitting.value = true;
    errorMsg.value = null;

    try {
      var devis = currentDevis.value;
      if (devis == null || devis.id != devisId) {
        await loadDevis(devisId);
        devis = currentDevis.value;
      }

      if (devis == null) {
        throw StateError('Devis introuvable');
      }

      final phone = StorageService.getPhone();
      if (phone == null || phone.trim().isEmpty) {
        _showErrorSnackbar(
          'Numéro de téléphone introuvable. Reconnectez-vous puis réessayez.',
        );
        return false;
      }

      final montantAcompte = paymentType == 'hybrid' ? devis.montantMateriaux : devis.totalGeneralTtc;

      final payment = await _paymentRepo.initiatePayment(
        missionId: devis.missionId,
        devisId: devis.id,
        montant: montantAcompte,
        provider: provider,
        phone: phone,
        paymentType: paymentType,
      );

      _setPendingPayment(payment, devis.id);

      if (provider == 'virement_bancaire') {
        Get.snackbar(
          'Virement initié',
          'Veuillez effectuer le virement bancaire avec les instructions affichées.',
          snackPosition: SnackPosition.TOP,
          duration: const Duration(seconds: 4),
        );
        return false;
      }

      final launchOpened = await reopenPendingPayment(showError: false);

      if (!launchOpened) {
        Get.snackbar(
          'Paiement prêt',
          'Le lien de paiement est prêt. Utilisez "Ouvrir le paiement" pour continuer.',
          snackPosition: SnackPosition.TOP,
          duration: const Duration(seconds: 4),
        );
      }

      final confirmed = await verifyPendingPayment(
        devisId,
        maxAttempts: 6,
        delayBetweenChecks: const Duration(seconds: 2),
        silentPending: true,
      );

      if (confirmed) {
        return true;
      }

      Get.snackbar(
        'Paiement en attente',
        'Validez le paiement sur votre Mobile Money puis revenez vérifier le statut.',
        snackPosition: SnackPosition.TOP,
        duration: const Duration(seconds: 4),
      );

      return false;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);
      _showErrorSnackbar('Erreur lors de l\'acceptation: ${errorMsg.value}');
      return false;
    } catch (_) {
      errorMsg.value = 'Impossible d\'accepter le devis';
      _showErrorSnackbar(errorMsg.value!);
      return false;
    } finally {
      isSubmitting.value = false;
    }
  }

  Future<bool> payJalon(int jalonId, {String provider = 'wave'}) async {
    isSubmitting.value = true;
    errorMsg.value = null;

    try {
      final phone = StorageService.getPhone();
      if (phone == null || phone.trim().isEmpty) {
        _showErrorSnackbar(
          'Numéro de téléphone introuvable. Reconnectez-vous puis réessayez.',
        );
        return false;
      }

      final payment = await _paymentRepo.initiateJalonPayment(
        jalonId: jalonId,
        provider: provider,
        phone: phone,
      );

      _setPendingPayment(payment, 0);

      if (provider == 'virement_bancaire') {
        Get.snackbar(
          'Virement initié',
          'Veuillez effectuer le virement bancaire avec les instructions affichées.',
          snackPosition: SnackPosition.TOP,
          duration: const Duration(seconds: 4),
        );
        return false;
      }

      final launchOpened = await reopenPendingPayment(showError: false);

      if (!launchOpened) {
        Get.snackbar(
          'Paiement prêt',
          'Le lien de paiement est prêt. Utilisez "Ouvrir le paiement" pour continuer.',
          snackPosition: SnackPosition.TOP,
          duration: const Duration(seconds: 4),
        );
      }

      final confirmed = await verifyPendingPayment(
        0,
        maxAttempts: 6,
        delayBetweenChecks: const Duration(seconds: 2),
        silentPending: true,
      );

      if (confirmed) {
        Get.snackbar(
          'Succès',
          'Le jalon a été payé avec succès !',
          duration: const Duration(seconds: 3),
        );
      }

      return confirmed;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);
      _showErrorSnackbar('Erreur lors du paiement du jalon: ${errorMsg.value}');
      return false;
    } catch (_) {
      errorMsg.value = 'Impossible de payer le jalon';
      _showErrorSnackbar(errorMsg.value!);
      return false;
    } finally {
      isSubmitting.value = false;
    }
  }

  Future<bool> reopenPendingPayment({bool showError = true}) async {
    final rawUrl = pendingLaunchUrl.value ?? pendingPaymentUrl.value;
    if (rawUrl == null || rawUrl.isEmpty) {
      if (showError) {
        _showErrorSnackbar('Aucun lien de paiement disponible.');
      }
      return false;
    }

    final uri = Uri.tryParse(rawUrl);
    if (uri == null) {
      if (showError) {
        _showErrorSnackbar('Lien de paiement invalide.');
      }
      return false;
    }

    final launched = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!launched && showError) {
      _showErrorSnackbar('Impossible d\'ouvrir le lien de paiement.');
    }

    return launched;
  }

  Future<bool> verifyPendingPayment(
    int devisId, {
    int maxAttempts = 1,
    Duration delayBetweenChecks = Duration.zero,
    bool silentPending = false,
  }) async {
    final transactionId = pendingTransactionId.value;
    if (transactionId == null || pendingDevisId.value != devisId) {
      if (!silentPending) {
        _showErrorSnackbar('Aucun paiement en attente pour ce devis.');
      }
      return false;
    }

    isCheckingPayment.value = true;
    errorMsg.value = null;

    try {
      for (int attempt = 0; attempt < maxAttempts; attempt++) {
        final status = await _paymentRepo.checkStatus(transactionId);

        if (status.isConfirmed) {
          if (devisId > 0) {
            await _finalizeAcceptedDevis(devisId, transactionId);
          } else {
            _clearPendingPayment();
          }
          return true;
        }

        if (status.isFailed) {
          _clearPendingPayment();
          _showErrorSnackbar('Le paiement a échoué ou a été annulé.');
          return false;
        }

        if (attempt < maxAttempts - 1 && delayBetweenChecks > Duration.zero) {
          await Future.delayed(delayBetweenChecks);
        }
      }

      if (!silentPending) {
        Get.snackbar(
          'Toujours en attente',
          'Le paiement n\'est pas encore confirmé. Réessayez dans quelques secondes.',
          snackPosition: SnackPosition.TOP,
          duration: const Duration(seconds: 4),
        );
      }

      return false;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);
      if (!silentPending) {
        _showErrorSnackbar('Erreur lors de la vérification: ${errorMsg.value}');
      }
      return false;
    } catch (_) {
      if (!silentPending) {
        _showErrorSnackbar('Impossible de vérifier le paiement.');
      }
      return false;
    } finally {
      isCheckingPayment.value = false;
    }
  }

  Future<void> _finalizeAcceptedDevis(int devisId, int transactionId) async {
    final devis = await _repo.acceptDevis(
      devisId,
      transactionId: transactionId,
    );

    currentDevis.value = devis;
    _replaceDevisInList(devis);
    _clearPendingPayment();

    if (missionId != null) {
      unawaited(loadMissionDevis(missionId!));
    }

    Get.snackbar(
      'Mission financée',
      'Le devis a été accepté et le séquestre a bien été financé.',
      snackPosition: SnackPosition.TOP,
      duration: const Duration(seconds: 4),
    );
  }

  void _replaceDevisInList(DevisModel devis) {
    final index = devisList.indexWhere((item) => item.id == devis.id);
    if (index >= 0) {
      devisList[index] = devis;
    }
  }

  void _setPendingPayment(PaymentInitiationModel payment, int devisId) {
    pendingTransactionId.value = payment.transactionId;
    pendingDevisId.value = devisId;
    pendingPaymentUrl.value = payment.paymentUrl;
    pendingLaunchUrl.value = payment.waveLaunchUrl;
    pendingProvider.value = payment.provider;
    pendingVirementInstructions.value = payment.virementInstructions;
  }

  void _clearPendingPayment() {
    pendingTransactionId.value = null;
    pendingDevisId.value = null;
    pendingPaymentUrl.value = null;
    pendingLaunchUrl.value = null;
    pendingProvider.value = null;
    pendingVirementInstructions.value = null;
  }

  /// Refuse un devis (client)
  Future<bool> refuseDevis(int devisId) async {
    isSubmitting.value = true;
    errorMsg.value = null;

    try {
      await _repo.refuseDevis(devisId);
      _clearPendingPayment();
      await loadDevis(devisId);

      Get.snackbar(
        'Devis refusé',
        'L\'artisan en sera informé',
        snackPosition: SnackPosition.TOP,
        duration: const Duration(seconds: 3),
      );

      return true;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);
      _showErrorSnackbar('Erreur lors du refus: ${errorMsg.value}');
      return false;
    } catch (_) {
      errorMsg.value = 'Impossible de refuser le devis';
      _showErrorSnackbar(errorMsg.value!);
      return false;
    } finally {
      isSubmitting.value = false;
    }
  }

  /// Réinitialise le formulaire de création de devis
  void resetForm() {
    lignes.clear();
    jalons.clear();
    errorMsg.value = null;
    _clearPendingPayment();
  }

  /// Gère les erreurs Dio et retourne un message en français
  String _handleDioError(DioException e) {
    if (e.type == DioExceptionType.connectionTimeout ||
        e.type == DioExceptionType.receiveTimeout) {
      return 'Délai d\'attente dépassé. Vérifiez votre connexion.';
    }

    if (e.type == DioExceptionType.connectionError) {
      return 'Pas de connexion internet';
    }

    if (e.response != null) {
      final statusCode = e.response!.statusCode;

      switch (statusCode) {
        case 401:
          return 'Session expirée. Veuillez vous reconnecter.';
        case 403:
          return 'Accès refusé';
        case 404:
          return 'Devis introuvable';
        case 422:
          final data = e.response!.data;
          if (data is Map && data.containsKey('message')) {
            return data['message'] as String;
          }
          return 'Données invalides';
        case 500:
          return 'Erreur serveur. Veuillez réessayer.';
        default:
          return 'Erreur réseau (code $statusCode)';
      }
    }

    return 'Erreur de connexion au serveur';
  }

  /// Affiche un snackbar d'erreur
  void _showErrorSnackbar(String message) {
    Get.snackbar(
      'Erreur',
      message,
      snackPosition: SnackPosition.TOP,
      duration: const Duration(seconds: 4),
      backgroundColor: Get.theme.colorScheme.error.withValues(alpha: 0.9),
      colorText: Get.theme.colorScheme.onError,
    );
  }

  /// Formate un montant en FCFA
  String _formatFCFA(int amount) {
    return '${amount.toString().replaceAllMapped(
          RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
          (Match m) => '${m[1]} ',
        )} FCFA';
  }
}
