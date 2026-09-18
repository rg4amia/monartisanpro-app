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
  final isVoting = false.obs;
  final litige = Rx<Map<String, dynamic>?>(null);

  late int litigeId;

  @override
  void onInit() {
    super.onInit();
    // Accepte un id brut (redirection depuis une notification push) ou une
    // Map {'litigeId': ...} (navigation interne) — un `as Map` strict plantait
    // (grey screen release) dès qu'un int était passé directement.
    final arg = Get.arguments;
    if (arg is int) {
      litigeId = arg;
    } else if (arg is Map) {
      litigeId = (arg['litigeId'] as int?) ?? 0;
    } else {
      litigeId = 0;
    }
    loadLitige();
  }

  bool get canUploadEvidence {
    final data = litige.value;
    if (data == null) return false;
    return data['statut'] != 'resolu' && data['workflowStep'] == 'preuves';
  }

  /// Non nul lorsque l'utilisateur connecte est un des 3 jures anonymes
  /// assignes a ce litige. `verdict` reste null tant qu'il n'a pas vote.
  Map<String, dynamic>? get myJuryReview =>
      litige.value?['myJuryReview'] as Map<String, dynamic>?;

  bool get isJuror => myJuryReview != null;

  bool get hasVoted => myJuryReview?['verdict'] != null;

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

  /// Vote du jure ('CONFORME' ou 'NON_CONFORME'). Le vote est definitif :
  /// une fois accepte par le backend, le litige est recharge pour afficher
  /// le verdict enregistre a la place des boutons.
  Future<void> castJuryVote(String verdict) async {
    if (isVoting.value || hasVoted) return;
    isVoting.value = true;
    try {
      await _client.post(
        ApiEndpoints.litigeJuryVote(litigeId),
        data: {'verdict': verdict},
      );
      await loadLitige();

      final compensation = myJuryReview?['compensation'];
      final compensationText = compensation is num
          ? ' Compensation retenue : ${compensation.toInt()} FCFA.'
          : '';
      Get.snackbar(
        'Vote enregistre',
        'Votre vote a bien ete pris en compte.$compensationText',
        snackPosition: SnackPosition.TOP,
      );
    } on DioException catch (e) {
      final responseData = e.response?.data;
      final message = (responseData is Map
              ? responseData['message'] as String?
              : null) ??
          'Impossible d\'enregistrer votre vote. Verifiez votre connexion et reessayez.';
      Get.snackbar('Erreur', message, snackPosition: SnackPosition.TOP);
    } catch (_) {
      Get.snackbar(
        'Erreur',
        'Une erreur inattendue est survenue. Veuillez reessayer.',
        snackPosition: SnackPosition.TOP,
      );
    } finally {
      isVoting.value = false;
    }
  }
}
