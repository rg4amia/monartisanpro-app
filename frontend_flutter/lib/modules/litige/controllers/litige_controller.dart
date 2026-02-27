import 'package:get/get.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/api_endpoints.dart';
import '../../../app/routes/app_routes.dart';

class LitigeController extends GetxController {
  final ApiClient _client = ApiClient();

  final description = ''.obs;
  final selectedType = 'client'.obs;
  final isLoading = false.obs;

  late int missionId;

  @override
  void onInit() {
    super.onInit();
    final args = Get.arguments as Map?;
    missionId = (args?['missionId'] as int?) ?? 0;
  }

  Future<void> submit() async {
    if (description.value.trim().isEmpty) {
      Get.snackbar('Erreur', 'Veuillez décrire le problème',
          snackPosition: SnackPosition.TOP);
      return;
    }
    isLoading.value = true;
    try {
      await _client.post(ApiEndpoints.litiges, data: {
        'mission_id': missionId,
        'type': selectedType.value,
        'description': description.value.trim(),
      });
      Get.offAllNamed(Routes.mainTab);
      Get.snackbar(
        'Litige signalé',
        'Notre équipe va instruire votre dossier',
        snackPosition: SnackPosition.TOP,
      );
    } finally {
      isLoading.value = false;
    }
  }
}
