import 'dart:async';

import 'package:dio/dio.dart';
import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/storage/storage_service.dart';
import '../../../data/models/recruitment_application_model.dart';
import '../../../data/models/recruitment_offer_model.dart';
import '../../../data/repositories/payment_repository.dart';
import '../../../data/repositories/recruitment_repository.dart';

/// Consultation et suivi des candidatures reçues sur une offre publiée par
/// le client ou le fournisseur connecté.
///
/// Protection anti-contournement : le numéro de l'artisan n'est jamais
/// transmis au recruteur (seule une demande de rappel notifiée est
/// possible), et la liste des postulants reste verrouillée tant que le
/// recruteur n'a pas payé le séquestre d'accès aux candidatures.
class RecruitmentApplicantsController extends GetxController {
  final RecruitmentRepository _repo = RecruitmentRepository();
  final PaymentRepository _paymentRepo = PaymentRepository();

  late final RecruitmentOfferModel offer;
  final applications = <RecruitmentApplicationModel>[].obs;
  final isLoading = false.obs;
  final updatingId = Rx<int?>(null);
  final requestingCallbackId = Rx<int?>(null);

  /// null = statut inconnu (chargement en cours), true/false une fois connu.
  final applicantsUnlocked = Rx<bool?>(null);
  final isUnlocking = false.obs;

  @override
  void onInit() {
    super.onInit();
    offer = Get.arguments as RecruitmentOfferModel;
    unawaited(load());
  }

  Future<void> load() async {
    isLoading.value = true;
    try {
      applications.value = await _repo.getOfferApplications(offer.id);
      applicantsUnlocked.value = true;
    } on DioException catch (e) {
      if (e.response?.statusCode == 402) {
        applicantsUnlocked.value = false;
      } else {
        Get.snackbar('Erreur', 'Impossible de charger les candidatures.');
      }
    } catch (_) {
      Get.snackbar('Erreur', 'Impossible de charger les candidatures.');
    } finally {
      isLoading.value = false;
    }
  }

  /// Paie le séquestre d'accès aux candidatures (Wave) pour déverrouiller la
  /// liste des postulants de cette offre.
  Future<void> unlockApplicants(int dailyRate, int? totalDays) async {
    final phone = StorageService.getPhone();
    if (phone == null || phone.isEmpty) {
      Get.snackbar('Paiement impossible', 'Numéro de téléphone introuvable.');
      return;
    }

    isUnlocking.value = true;
    try {
      final result = await _repo.unlockApplicants(
        offer.id,
        dailyRate: dailyRate,
        totalDays: totalDays,
        provider: 'wave',
        phone: phone,
      );

      final url = (result['wave_launch_url'] as String?) ??
          (result['payment_url'] as String?);
      final transactionId = result['transaction_id'];

      if (url != null && url.isNotEmpty) {
        await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
      }

      final txId =
          transactionId is int ? transactionId : int.tryParse('$transactionId');
      if (txId == null) return;

      for (var attempt = 0; attempt < 3; attempt++) {
        final status = await _paymentRepo.checkStatus(txId);
        if (status.isConfirmed) {
          await _repo.activateApplicantsUnlock(offer.id, txId);
          await load();
          Get.snackbar(
            'Séquestre payé',
            'Les candidatures de cette offre sont maintenant consultables.',
          );
          return;
        }
        await Future.delayed(const Duration(seconds: 2));
      }

      Get.snackbar(
        'Paiement en attente',
        'Validez le paiement puis revenez sur cet écran pour réessayer.',
      );
    } catch (_) {
      Get.snackbar('Erreur', "Impossible d'initier le paiement du séquestre.");
    } finally {
      isUnlocking.value = false;
    }
  }

  Future<void> updateStatus(
    RecruitmentApplicationModel application,
    String status,
  ) async {
    updatingId.value = application.id;
    try {
      await _repo.updateApplicationStatus(offer.id, application.id, status);
      await load();
    } catch (_) {
      Get.snackbar('Erreur', 'Impossible de mettre à jour le statut.');
    } finally {
      updatingId.value = null;
    }
  }

  /// Confirme le candidat et crée l'engagement journalier séquestré.
  Future<int?> createEngagement(
    RecruitmentApplicationModel application,
    int dailyRate,
    int? totalDays,
  ) async {
    updatingId.value = application.id;
    try {
      final engagement = await _repo.createEngagement(
        applicationId: application.id,
        dailyRate: dailyRate,
        totalDays: totalDays,
      );
      await load();
      return engagement.id;
    } catch (_) {
      Get.snackbar('Erreur', "Impossible de créer l'engagement.");
      return null;
    } finally {
      updatingId.value = null;
    }
  }

  /// Demande à l'artisan de rappeler le recruteur — son numéro n'est jamais
  /// affiché, seule cette mise en relation notifiée est possible.
  Future<void> requestCallback(RecruitmentApplicationModel application) async {
    requestingCallbackId.value = application.id;
    try {
      await _repo.requestCallback(application.id);
      Get.snackbar(
        'Demande envoyée',
        "L'artisan a été invité à vous rappeler.",
      );
    } catch (_) {
      Get.snackbar('Erreur', 'Impossible d\'envoyer la demande de rappel.');
    } finally {
      requestingCallbackId.value = null;
    }
  }
}
