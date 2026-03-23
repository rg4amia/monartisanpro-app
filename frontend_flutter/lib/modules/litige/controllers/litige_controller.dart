import 'package:get/get.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/api_endpoints.dart';
import '../../../app/routes/app_routes.dart';

class LitigeController extends GetxController {
  final ApiClient _client = ApiClient();

  final description = ''.obs;
  final selectedMotif = 'Travail inacheve'.obs;
  final isLoading = false.obs;
  final motifs = const [
    'Travail inacheve',
    'Malfacons',
    'Materiaux non conformes',
    'Retard important',
    'Fraude suspectee',
    'Autre',
  ];

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
      final response = await _client.post(ApiEndpoints.litiges, data: {
        'mission_id': missionId,
        'motif': selectedMotif.value,
        'description': description.value.trim(),
      });

      final litigeId = response.data['data']?['id'];
      if (litigeId is int) {
        Get.offNamed(Routes.litigeDetail, arguments: {'litigeId': litigeId});
      } else {
        Get.offAllNamed(Routes.mainTab);
      }

      Get.snackbar(
        'Litige signalé',
        'Le dossier est ouvert et les fonds sont geles jusqu a decision.',
        snackPosition: SnackPosition.TOP,
      );
    } finally {
      isLoading.value = false;
    }
  }
}
