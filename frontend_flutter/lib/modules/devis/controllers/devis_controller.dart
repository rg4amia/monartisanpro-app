import 'dart:async';

import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/storage/storage_service.dart';
import '../../../data/models/devis_model.dart';
import '../../../data/repositories/devis_repository.dart';
import '../../../data/repositories/payment_repository.dart';

class DevisController extends GetxController {
  final DevisRepository _repo = DevisRepository();
  final PaymentRepository _paymentRepo = PaymentRepository();

  final devis = Rx<DevisModel?>(null);
  final devoiList = <DevisModel>[].obs;
  final isLoading = false.obs;

  // Quote builder state
  final lignes = <DevisLigne>[].obs;
  final jalons = <DevisJalon>[].obs;

  int get totalMo =>
      lignes.where((l) => l.type == 'mo').fold(0, (s, l) => s + l.montant);
  int get totalMat =>
      lignes.where((l) => l.type == 'mat').fold(0, (s, l) => s + l.montant);
  int get total => totalMo + totalMat;

  @override
  void onInit() {
    super.onInit();
    final arg = Get.arguments;
    if (arg is int) loadDevis(arg);
    if (arg is Map && arg['missionId'] != null) {
      loadMissionDevis(arg['missionId'] as int);
    }
  }

  Future<void> loadDevis(int id) async {
    isLoading.value = true;
    try {
      devis.value = await _repo.getDevis(id);
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> loadMissionDevis(int missionId) async {
    isLoading.value = true;
    try {
      devoiList.value = await _repo.getMissionDevis(missionId);
    } finally {
      isLoading.value = false;
    }
  }

  void addLigne(String type, String description, int montant) {
    lignes.add(
      DevisLigne(type: type, description: description, montant: montant),
    );
  }

  void removeLigne(int index) => lignes.removeAt(index);

  void addJalon(String description, int montant, String dateCible) {
    jalons.add(
      DevisJalon(
        ordre: jalons.length + 1,
        description: description,
        montant: montant,
        dateCible: dateCible,
      ),
    );
  }

  void removeJalon(int index) {
    jalons.removeAt(index);
    // re-number
    for (var i = 0; i < jalons.length; i++) {
      jalons[i] = DevisJalon(
        ordre: i + 1,
        description: jalons[i].description,
        montant: jalons[i].montant,
        dateCible: jalons[i].dateCible,
      );
    }
  }

  Future<void> submitDevis(int missionId) async {
    isLoading.value = true;
    try {
      await _repo.createDevis(
        missionId: missionId,
        lignes: lignes,
        jalons: jalons,
      );
      Get.back();
      Get.snackbar(
        'Devis envoyé',
        'Le client recevra une notification.',
        snackPosition: SnackPosition.TOP,
      );
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> acceptDevis(int id) async {
    final current = devis.value;
    if (current == null) {
      await loadDevis(id);
    }

    final target = devis.value;
    final phone = StorageService.getPhone();

    if (target == null || phone == null || phone.isEmpty) {
      Get.snackbar(
        'Paiement impossible',
        'Numéro de téléphone introuvable. Reconnectez-vous puis réessayez.',
        snackPosition: SnackPosition.TOP,
      );
      return;
    }

    final payment = await _paymentRepo.initiatePayment(
      missionId: target.missionId,
      devisId: target.id,
      montant: target.totalGeneralTtc,
      provider: 'wave',
      phone: phone,
    );

    final url = payment.launchUrl;
    if (url != null && url.isNotEmpty) {
      await launchUrl(
        Uri.parse(url),
        mode: LaunchMode.externalApplication,
      );
    }

    for (var attempt = 0; attempt < 3; attempt++) {
      final status = await _paymentRepo.checkStatus(payment.transactionId);
      if (status.isConfirmed) {
        devis.value = await _repo.acceptDevis(
          id,
          transactionId: payment.transactionId,
        );
        Get.snackbar(
          'Devis accepté',
          'La mission est maintenant financée.',
          snackPosition: SnackPosition.TOP,
        );
        return;
      }

      await Future.delayed(const Duration(seconds: 2));
    }

    Get.snackbar(
      'Paiement en attente',
      'Validez le paiement puis revenez sur cet écran pour réessayer.',
      snackPosition: SnackPosition.TOP,
    );
  }

  Future<void> refuseDevis(int id) async {
    await _repo.refuseDevis(id);
    Get.back();
  }
}
