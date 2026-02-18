import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';
import '../models/dispute_model.dart';
import '../../core/network/dispute_service.dart';

class DisputeController extends GetxController {
  final DisputeService _disputeService = Get.find();
  final ImagePicker _picker = ImagePicker();

  final disputes = <Dispute>[].obs;
  final isLoading = false.obs;
  final filterStatus = Rxn<String>();

  @override
  void onInit() {
    super.onInit();
    fetchDisputes();
  }

  Future<void> fetchDisputes({String? status}) async {
    try {
      isLoading.value = true;
      final result = await _disputeService.getDisputes(status: status);
      disputes.value = result;
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de charger les litiges: ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
      );
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> filterByStatus(String? status) async {
    filterStatus.value = status;
    await fetchDisputes(status: status);
  }

  Future<Dispute?> getDisputeDetails(int id) async {
    try {
      return await _disputeService.getDisputeById(id);
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de charger les détails: ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
      );
      return null;
    }
  }

  Future<bool> createDispute(CreateDisputeRequest request) async {
    try {
      isLoading.value = true;
      final dispute = await _disputeService.createDispute(request);
      disputes.insert(0, dispute);

      Get.snackbar(
        'Succès',
        'Litige créé avec succès',
        snackPosition: SnackPosition.BOTTOM,
      );

      return true;
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de créer le litige: ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
      );
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  Future<bool> sendMessage(int disputeId, String message, {List<XFile>? attachments}) async {
    try {
      // Upload attachments if any
      List<String>? attachmentUrls;
      if (attachments != null && attachments.isNotEmpty) {
        attachmentUrls = await _uploadAttachments(attachments);
      }

      await _disputeService.sendMessage(
        disputeId,
        message,
        attachments: attachmentUrls,
      );

      return true;
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible d\'envoyer le message: ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
      );
      return false;
    }
  }

  Future<List<String>> _uploadAttachments(List<XFile> files) async {
    // TODO: Implement file upload to server
    // For now, return empty list
    return [];
  }

  Future<List<XFile>> pickImages() async {
    try {
      final images = await _picker.pickMultiImage();
      return images;
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de sélectionner les images',
        snackPosition: SnackPosition.BOTTOM,
      );
      return [];
    }
  }

  Future<XFile?> pickImage() async {
    try {
      final image = await _picker.pickImage(source: ImageSource.gallery);
      return image;
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de sélectionner l\'image',
        snackPosition: SnackPosition.BOTTOM,
      );
      return null;
    }
  }

  Future<XFile?> takePhoto() async {
    try {
      final image = await _picker.pickImage(source: ImageSource.camera);
      return image;
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de prendre la photo',
        snackPosition: SnackPosition.BOTTOM,
      );
      return null;
    }
  }
}
