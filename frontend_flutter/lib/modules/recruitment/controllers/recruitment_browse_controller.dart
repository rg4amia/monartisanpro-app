import 'dart:async';

import 'package:dio/dio.dart';
import 'package:get/get.dart';

import '../../../data/models/recruitment_application_model.dart';
import '../../../data/models/recruitment_engagement_model.dart';
import '../../../data/models/recruitment_offer_model.dart';
import '../../../data/repositories/recruitment_repository.dart';

/// Espace artisan : parcourir les offres actives, postuler et suivre les
/// engagements (séquestre journalier) reçus des recruteurs.
class RecruitmentBrowseController extends GetxController {
  final RecruitmentRepository _repo = RecruitmentRepository();

  final offers = <RecruitmentOfferModel>[].obs;
  final myApplications = <RecruitmentApplicationModel>[].obs;
  final myEngagements = <RecruitmentEngagementModel>[].obs;
  final isLoading = false.obs;
  final applyingOfferId = Rx<int?>(null);

  @override
  void onInit() {
    super.onInit();
    unawaited(load());
  }

  Future<void> load() async {
    isLoading.value = true;
    try {
      final results = await Future.wait([
        _repo.listOffers(),
        _repo.myApplications(),
        _repo.myEngagements(),
      ]);
      offers.value = results[0] as List<RecruitmentOfferModel>;
      myApplications.value = results[1] as List<RecruitmentApplicationModel>;
      myEngagements.value = results[2] as List<RecruitmentEngagementModel>;
    } catch (_) {
      Get.snackbar(
        'Erreur',
        'Impossible de charger les offres de recrutement.',
      );
    } finally {
      isLoading.value = false;
    }
  }

  bool hasAppliedTo(int offerId) =>
      myApplications.any((a) => a.offerId == offerId);

  Future<void> apply(RecruitmentOfferModel offer) async {
    if (hasAppliedTo(offer.id)) return;
    applyingOfferId.value = offer.id;
    try {
      await _repo.applyToOffer(offer.id);
      Get.snackbar(
        'Candidature envoyée',
        'Votre candidature a bien été transmise.',
      );
      await load();
    } on DioException catch (e) {
      final responseData = e.response?.data;
      final message = responseData is Map<String, dynamic>
          ? responseData['message'] as String?
          : null;
      Get.snackbar(
        'Candidature impossible',
        message ?? 'Une erreur est survenue.',
      );
    } catch (_) {
      Get.snackbar('Candidature impossible', 'Une erreur est survenue.');
    } finally {
      applyingOfferId.value = null;
    }
  }
}
