import 'dart:async';

import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/storage/storage_service.dart';
import '../../../data/models/recruitment_engagement_model.dart';
import '../../../data/repositories/payment_repository.dart';
import '../../../data/repositories/recruitment_repository.dart';

/// Détail d'un engagement de recrutement (séquestre journalier), partagé par
/// l'espace artisan (accepter/refuser) et l'espace client/fournisseur (payer
/// le séquestre, valider les journées, prolonger le contrat).
class RecruitmentEngagementController extends GetxController {
  final RecruitmentRepository _repo = RecruitmentRepository();
  final PaymentRepository _paymentRepo = PaymentRepository();

  late final int engagementId;
  final engagement = Rx<RecruitmentEngagementModel?>(null);
  final isLoading = false.obs;
  final isProcessing = false.obs;
  final validatingWorkdayId = Rx<int?>(null);

  String get myUserId => (StorageService.getUserId() ?? 0).toString();
  bool get isArtisan =>
      engagement.value != null &&
      engagement.value!.artisanId == StorageService.getUserId();
  bool get isRecruiter =>
      engagement.value != null &&
      engagement.value!.recruiterId == StorageService.getUserId();

  @override
  void onInit() {
    super.onInit();
    final arg = Get.arguments;
    engagementId = arg is RecruitmentEngagementModel ? arg.id : arg as int;
    if (arg is RecruitmentEngagementModel) engagement.value = arg;
    unawaited(load());
  }

  Future<void> load() async {
    isLoading.value = true;
    try {
      engagement.value = await _repo.showEngagement(engagementId);
    } catch (_) {
      Get.snackbar('Erreur', "Impossible de charger l'engagement.");
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> accept() async {
    isProcessing.value = true;
    try {
      engagement.value = await _repo.acceptEngagement(engagementId);
      Get.snackbar(
        'Engagement accepté',
        'Le recruteur peut maintenant payer le séquestre.',
      );
    } catch (_) {
      Get.snackbar('Erreur', "Impossible d'accepter cet engagement.");
    } finally {
      isProcessing.value = false;
    }
  }

  Future<void> decline() async {
    isProcessing.value = true;
    try {
      await _repo.declineEngagement(engagementId);
      await load();
      Get.snackbar('Engagement refusé', 'Le recruteur en sera informé.');
    } catch (_) {
      Get.snackbar('Erreur', 'Impossible de refuser cet engagement.');
    } finally {
      isProcessing.value = false;
    }
  }

  Future<void> payEscrow() async {
    final phone = StorageService.getPhone();
    if (phone == null || phone.isEmpty) {
      Get.snackbar('Paiement impossible', 'Numéro de téléphone introuvable.');
      return;
    }

    isProcessing.value = true;
    try {
      final result = await _repo.initiateEscrowPayment(
        engagementId,
        provider: 'wave',
        phone: phone,
      );

      final url = (result['wave_launch_url'] as String?) ??
          (result['payment_url'] as String?);
      final transactionId = result['transaction_id'];

      if (url != null && url.isNotEmpty) {
        await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
      }

      if (transactionId == null) return;
      final txId =
          transactionId is int ? transactionId : int.tryParse('$transactionId');
      if (txId == null) return;

      for (var attempt = 0; attempt < 3; attempt++) {
        final status = await _paymentRepo.checkStatus(txId);
        if (status.isConfirmed) {
          engagement.value = await _repo.activateEscrow(engagementId, txId);
          Get.snackbar(
            'Séquestre payé',
            'Les journées sont prêtes à être validées.',
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
      isProcessing.value = false;
    }
  }

  Future<void> validateWorkday(RecruitmentWorkdayModel workday) async {
    validatingWorkdayId.value = workday.id;
    try {
      await _repo.validateWorkday(engagementId, workday.id);
      await load();
    } catch (_) {
      Get.snackbar('Erreur', 'Impossible de valider cette journée.');
    } finally {
      validatingWorkdayId.value = null;
    }
  }

  Future<void> extend(int additionalDays) async {
    isProcessing.value = true;
    try {
      engagement.value = await _repo.extendEngagement(
        engagementId,
        additionalDays,
      );
      Get.snackbar(
        'Contrat prolongé',
        'Complétez le séquestre pour les jours ajoutés.',
      );
    } catch (_) {
      Get.snackbar('Erreur', 'Impossible de prolonger ce contrat.');
    } finally {
      isProcessing.value = false;
    }
  }
}
