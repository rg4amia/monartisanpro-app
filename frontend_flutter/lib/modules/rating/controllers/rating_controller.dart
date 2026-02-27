import 'package:get/get.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/api_endpoints.dart';
import '../../../app/routes/app_routes.dart';

class RatingController extends GetxController {
  final ApiClient _client = ApiClient();

  final selectedNote = 0.obs;
  final commentaire = ''.obs;
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

  void setNote(int note) => selectedNote.value = note;

  Future<void> submit() async {
    if (selectedNote.value == 0) {
      Get.snackbar('Erreur', 'Veuillez sélectionner une note',
          snackPosition: SnackPosition.TOP);
      return;
    }
    isLoading.value = true;
    try {
      await _client.post(ApiEndpoints.evaluations, data: {
        'mission_id': missionId,
        'evalue_id': evalueId,
        'note': selectedNote.value,
        'commentaire': commentaire.value.trim().isEmpty
            ? null
            : commentaire.value.trim(),
      });
      Get.offAllNamed(Routes.mainTab);
      Get.snackbar('Évaluation envoyée', 'Merci pour votre retour !',
          snackPosition: SnackPosition.TOP);
    } finally {
      isLoading.value = false;
    }
  }
}
