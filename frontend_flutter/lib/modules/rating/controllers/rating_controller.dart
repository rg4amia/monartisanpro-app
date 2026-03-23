import 'package:dio/dio.dart';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../data/repositories/evaluation_repository.dart';

class RatingController extends GetxController {
  final EvaluationRepository _repo = EvaluationRepository();

  final selectedNote = 0.obs;
  final commentaire = ''.obs;
  final fiabilite = 0.obs;
  final integrite = 0.obs;
  final qualite = 0.obs;
  final reactivite = 0.obs;
  final isLoading = false.obs;

  late int missionId;
  late int evalueId;

  @override
  void onInit() {
    super.onInit();
    final args = Get.arguments as Map?;
    missionId = (args?['missionId'] as int?) ?? 0;
    evalueId = (args?['evalueId'] as int?) ?? 0;
  }

  void setNote(int note) {
    selectedNote.value = note;

    if (fiabilite.value == 0) fiabilite.value = note;
    if (integrite.value == 0) integrite.value = note;
    if (qualite.value == 0) qualite.value = note;
    if (reactivite.value == 0) reactivite.value = note;
  }

  void setCriterion(String criterion, int note) {
    switch (criterion) {
      case 'fiabilite':
        fiabilite.value = note;
        break;
      case 'integrite':
        integrite.value = note;
        break;
      case 'qualite':
        qualite.value = note;
        break;
      case 'reactivite':
        reactivite.value = note;
        break;
    }
  }

  Future<void> submit() async {
    if (selectedNote.value == 0) {
      Get.snackbar(
        'Erreur',
        'Veuillez sélectionner une note globale.',
        snackPosition: SnackPosition.TOP,
      );
      return;
    }

    final resolvedFiabilite =
        fiabilite.value == 0 ? selectedNote.value : fiabilite.value;
    final resolvedIntegrite =
        integrite.value == 0 ? selectedNote.value : integrite.value;
    final resolvedQualite =
        qualite.value == 0 ? selectedNote.value : qualite.value;
    final resolvedReactivite =
        reactivite.value == 0 ? selectedNote.value : reactivite.value;

    isLoading.value = true;
    try {
      await _repo.submit(
        missionId: missionId,
        evalueId: evalueId,
        note: selectedNote.value,
        commentaire: commentaire.value,
        fiabilite: resolvedFiabilite,
        integrite: resolvedIntegrite,
        qualite: resolvedQualite,
        reactivite: resolvedReactivite,
      );

      Get.offAllNamed(Routes.mainTab);
      Get.snackbar(
        'Évaluation envoyée',
        'Merci pour votre retour.',
        snackPosition: SnackPosition.TOP,
      );
    } on DioException catch (e) {
      Get.snackbar(
        'Erreur',
        _extractMessage(e),
        snackPosition: SnackPosition.TOP,
      );
    } finally {
      isLoading.value = false;
    }
  }

  String _extractMessage(DioException e) {
    final data = e.response?.data;
    if (data is Map && data['message'] is String) {
      return data['message'] as String;
    }
    return 'Impossible d\'envoyer l\'évaluation.';
  }
}
