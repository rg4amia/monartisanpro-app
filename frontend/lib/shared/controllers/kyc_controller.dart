import 'package:get/get.dart';
import '../../core/network/kyc_service.dart';

class KycController extends GetxController {
  final KycService _kycService = KycService();

  final Rx<KycStatus?> kycStatus = Rx<KycStatus?>(null);
  final RxList<KycDocument> documents = <KycDocument>[].obs;
  final RxBool isLoading = false.obs;
  final RxBool isUploading = false.obs;
  final RxString errorMessage = ''.obs;

  // Selected files for upload
  final RxString documentPath = ''.obs;
  final RxString selfiePath = ''.obs;
  final RxString documentType = 'cni'.obs; // cni, passport, attestation

  @override
  void onInit() {
    super.onInit();
    fetchKycStatus();
  }

  /// Fetch current KYC status
  Future<void> fetchKycStatus() async {
    try {
      isLoading.value = true;
      errorMessage.value = '';

      final response = await _kycService.getStatus();

      if (response.success && response.data != null) {
        kycStatus.value = response.data;
      } else {
        errorMessage.value = response.message ?? 'Failed to fetch KYC status';
      }
    } catch (e) {
      errorMessage.value = 'Network error: ${e.toString()}';
    } finally {
      isLoading.value = false;
    }
  }

  /// Upload KYC documents
  Future<bool> uploadDocuments() async {
    if (documentPath.value.isEmpty) {
      errorMessage.value = 'Please select a document';
      return false;
    }

    try {
      isUploading.value = true;
      errorMessage.value = '';

      final response = await _kycService.uploadDocuments(
        documentType: documentType.value,
        documentPath: documentPath.value,
        selfiePath: selfiePath.value.isNotEmpty ? selfiePath.value : null,
      );

      if (response.success) {
        // Refresh KYC status after upload
        await fetchKycStatus();
        // Clear selected files
        documentPath.value = '';
        selfiePath.value = '';
        return true;
      } else {
        errorMessage.value = response.message ?? 'Upload failed';
        return false;
      }
    } catch (e) {
      errorMessage.value = 'Network error: ${e.toString()}';
      return false;
    } finally {
      isUploading.value = false;
    }
  }

  /// Fetch KYC documents
  Future<void> fetchDocuments() async {
    try {
      isLoading.value = true;
      errorMessage.value = '';

      final response = await _kycService.getDocuments();

      if (response.success && response.data != null) {
        documents.value = response.data!;
      } else {
        errorMessage.value = response.message ?? 'Failed to fetch documents';
      }
    } catch (e) {
      errorMessage.value = 'Network error: ${e.toString()}';
    } finally {
      isLoading.value = false;
    }
  }

  /// Set document file path
  void setDocumentPath(String path) {
    documentPath.value = path;
  }

  /// Set selfie file path
  void setSelfiePath(String path) {
    selfiePath.value = path;
  }

  /// Set document type
  void setDocumentType(String type) {
    documentType.value = type;
  }

  /// Clear selected files
  void clearFiles() {
    documentPath.value = '';
    selfiePath.value = '';
  }
}
