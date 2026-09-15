import 'dart:async';

import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../data/models/recruitment_application_model.dart';
import '../../../data/models/recruitment_offer_model.dart';
import '../../../data/repositories/recruitment_repository.dart';

/// Consultation et suivi des candidatures reçues sur une offre publiée par
/// le client ou le fournisseur connecté.
class RecruitmentApplicantsController extends GetxController {
  final RecruitmentRepository _repo = RecruitmentRepository();

  late final RecruitmentOfferModel offer;
  final applications = <RecruitmentApplicationModel>[].obs;
  final isLoading = false.obs;
  final updatingId = Rx<int?>(null);

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
    } catch (_) {
      Get.snackbar('Erreur', 'Impossible de charger les candidatures.');
    } finally {
      isLoading.value = false;
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

  Future<void> call(RecruitmentApplicantModel artisan) async {
    await launchUrl(
      Uri.parse('tel:${artisan.phone}'),
      mode: LaunchMode.externalApplication,
    );
  }

  Future<void> whatsapp(RecruitmentApplicantModel artisan) async {
    final digits = artisan.phone.replaceAll(RegExp(r'[^0-9]'), '');
    final uri = Uri.parse(
      'https://api.whatsapp.com/send/?phone=$digits&type=phone_number&app_absent=0',
    );
    await launchUrl(uri, mode: LaunchMode.externalApplication);
  }
}
