import 'package:dio/dio.dart';
import 'package:geolocator/geolocator.dart';
import 'package:get/get.dart' hide FormData, MultipartFile;
import 'package:image_picker/image_picker.dart';
import 'package:permission_handler/permission_handler.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_endpoints.dart';

class LitigeDetailController extends GetxController {
  final ApiClient _client = ApiClient();
  final ImagePicker _picker = ImagePicker();

  final isLoading = false.obs;
  final isUploadingEvidence = false.obs;
  final litige = Rx<Map<String, dynamic>?>(null);

  late int litigeId;

  @override
  void onInit() {
    super.onInit();
    litigeId = (Get.arguments as Map<String, dynamic>)['litigeId'];
    loadLitige();
  }

  bool get canUploadEvidence {
    final data = litige.value;
    if (data == null) return false;
    return data['statut'] != 'resolu' && data['workflowStep'] == 'preuves';
  }

  Future<void> loadLitige() async {
    isLoading.value = true;
    try {
      final res = await _client.get(ApiEndpoints.litige(litigeId));
      litige.value =
          (res.data as Map<String, dynamic>)['data'] as Map<String, dynamic>?;
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> uploadEvidence() async {
    if (isUploadingEvidence.value) return;

    final locationStatus = await Permission.location.request();
    if (!locationStatus.isGranted) {
      Get.snackbar(
        'Permission requise',
        'La localisation est necessaire pour joindre une preuve.',
      );
      return;
    }

    final cameraStatus = await Permission.camera.request();
    if (!cameraStatus.isGranted) {
      Get.snackbar(
        'Permission requise',
        'La camera est necessaire pour joindre une preuve.',
      );
      return;
    }

    final image = await _picker.pickImage(
      source: ImageSource.camera,
      imageQuality: 80,
      maxWidth: 1920,
    );

    if (image == null) return;

    isUploadingEvidence.value = true;
    try {
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 10),
        ),
      );

      final formData = FormData.fromMap({
        'photos[0][photo]': await MultipartFile.fromFile(
          image.path,
          filename: image.name,
        ),
        'photos[0][latitude]': position.latitude,
        'photos[0][longitude]': position.longitude,
        'photos[0][description]': 'Preuve terrain',
      });

      await _client.postMultipart(
        ApiEndpoints.litigeEvidence(litigeId),
        formData,
      );
      await loadLitige();

      Get.snackbar(
        'Preuve envoyee',
        'La photo geolocalisee a ete ajoutee au dossier.',
        snackPosition: SnackPosition.TOP,
      );
    } finally {
      isUploadingEvidence.value = false;
    }
  }
}
