import 'dart:async';
import 'package:get/get.dart';
import '../../../app/routes/app_routes.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/api_endpoints.dart';
import '../../../core/storage/storage_service.dart';

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
      Get.snackbar(
        'Erreur',
        'Veuillez décrire le problème',
        snackPosition: SnackPosition.TOP,
      );
      return;
    }
    isLoading.value = true;
    try {
      // `type` indique qui déclenche le litige ('client' ou 'artisan')
      final role = StorageService.getRole() ?? 'client';
      final type = (role == 'artisan') ? 'artisan' : 'client';
      final fullDescription =
          '${selectedMotif.value} — ${description.value.trim()}';

      final response = await _client.post(
        ApiEndpoints.litiges,
        data: {
          'mission_id': missionId,
          'type': type,
          'description': fullDescription,
        },
      );

      final body = response.data as Map<String, dynamic>;
      final litigeId = (body['data'] as Map<String, dynamic>?)?['id'];
      if (litigeId is int) {
        unawaited(
          Get.offNamed(
            Routes.litigeDetail,
            arguments: {'litigeId': litigeId},
          ),
        );
      } else {
        unawaited(Get.offAllNamed(Routes.mainTab));
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
