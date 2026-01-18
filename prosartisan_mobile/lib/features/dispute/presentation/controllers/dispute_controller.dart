import 'dart:io';
import 'package:get/get.dart';
import '../../domain/models/dispute.dart';
import '../../data/repositories/dispute_repository.dart';
import '../../../auth/presentation/controllers/auth_controller.dart';

/// Controller for dispute management
///
/// Requirements: 9.1, 9.5
class DisputeController extends GetxController {
  final DisputeRepository _disputeRepository;
  late final AuthController _authController;

  DisputeController({required DisputeRepository disputeRepository})
    : _disputeRepository = disputeRepository;

  @override
  void onInit() {
    super.onInit();
    _authController = Get.find<AuthController>();
    loadUserDisputes();
  }

  // Observable state
  final RxList<Dispute> disputes = <Dispute>[].obs;
  final Rx<Dispute?> currentDispute = Rx<Dispute?>(null);
  final RxBool isLoading = false.obs;
  final RxBool isSubmitting = false.obs;
  final RxString error = ''.obs;

  // Form state for dispute creation
  final RxString selectedMissionId = ''.obs;
  final RxString selectedDefendantId = ''.obs;
  final Rx<DisputeType?> selectedType = Rx<DisputeType?>(null);
  final RxString description = ''.obs;
  final RxList<String> evidenceUrls = <String>[].obs;
  final RxList<File> evidenceFiles = <File>[].obs;

  // Mediation chat state
  final RxString messageText = ''.obs;
  final RxList<Communication> messages = <Communication>[].obs;

  /// Load user's disputes
  Future<void> loadUserDisputes() async {
    try {
      isLoading.value = true;
      error.value = '';

      final userDisputes = await _disputeRepository.getUserDisputes();
      disputes.value = userDisputes;
    } catch (e) {
      error.value = e.toString();
      Get.snackbar(
        'Erreur',
        'Impossible de charger les litiges: ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
      );
    } finally {
      isLoading.value = false;
    }
  }

  /// Load specific dispute details
  Future<void> loadDispute(String disputeId) async {
    try {
      isLoading.value = true;
      error.value = '';

      final dispute = await _disputeRepository.getDispute(disputeId);
      currentDispute.value = dispute;

      // Load mediation messages if available
      if (dispute.mediation != null) {
        messages.value = dispute.mediation!.communications;
      }
    } catch (e) {
      error.value = e.toString();
      Get.snackbar(
        'Erreur',
        'Impossible de charger le litige: ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
      );
    } finally {
      isLoading.value = false;
    }
  }

  /// Report a new dispute
  ///
  /// Requirement 9.1: Create dispute record
  Future<bool> reportDispute() async {
    if (!_validateDisputeForm()) {
      return false;
    }

    try {
      isSubmitting.value = true;
      error.value = '';

      // Upload evidence files first
      final uploadedUrls = <String>[];
      for (final file in evidenceFiles) {
        final url = await _disputeRepository.uploadEvidence(file);
        uploadedUrls.add(url);
      }

      // Add existing URLs
      uploadedUrls.addAll(evidenceUrls);

      final dispute = await _disputeRepository.reportDispute(
        missionId: selectedMissionId.value,
        defendantId: selectedDefendantId.value,
        type: selectedType.value!.value,
        description: description.value,
        evidence: uploadedUrls,
      );

      // Add to disputes list
      disputes.insert(0, dispute);

      // Clear form
      _clearDisputeForm();

      Get.snackbar(
        'Succès',
        'Litige signalé avec succès',
        snackPosition: SnackPosition.BOTTOM,
      );

      return true;
    } catch (e) {
      error.value = e.toString();
      Get.snackbar(
        'Erreur',
        'Impossible de signaler le litige: ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
      );
      return false;
    } finally {
      isSubmitting.value = false;
    }
  }

  /// Send message in mediation
  ///
  /// Requirement 9.5: Provide communication channel
  Future<bool> sendMediationMessage(String disputeId) async {
    if (messageText.value.trim().isEmpty) {
      Get.snackbar(
        'Erreur',
        'Le message ne peut pas être vide',
        snackPosition: SnackPosition.BOTTOM,
      );
      return false;
    }

    try {
      isSubmitting.value = true;
      error.value = '';

      final updatedDispute = await _disputeRepository.sendMediationMessage(
        disputeId: disputeId,
        message: messageText.value.trim(),
      );

      // Update current dispute
      currentDispute.value = updatedDispute;

      // Update messages
      if (updatedDispute.mediation != null) {
        messages.value = updatedDispute.mediation!.communications;
      }

      // Clear message input
      messageText.value = '';

      return true;
    } catch (e) {
      error.value = e.toString();
      Get.snackbar(
        'Erreur',
        'Impossible d\'envoyer le message: ${e.toString()}',
        snackPosition: SnackPosition.BOTTOM,
      );
      return false;
    } finally {
      isSubmitting.value = false;
    }
  }

  /// Add evidence file
  void addEvidenceFile(File file) {
    evidenceFiles.add(file);
  }

  /// Remove evidence file
  void removeEvidenceFile(int index) {
    if (index < evidenceFiles.length) {
      evidenceFiles.removeAt(index);
    }
  }

  /// Add evidence URL
  void addEvidenceUrl(String url) {
    if (url.isNotEmpty && Uri.tryParse(url) != null) {
      evidenceUrls.add(url);
    }
  }

  /// Remove evidence URL
  void removeEvidenceUrl(int index) {
    if (index < evidenceUrls.length) {
      evidenceUrls.removeAt(index);
    }
  }

  /// Set dispute form data
  void setDisputeData({
    required String missionId,
    required String defendantId,
  }) {
    selectedMissionId.value = missionId;
    selectedDefendantId.value = defendantId;
  }

  /// Set selected dispute type
  void setDisputeType(DisputeType type) {
    selectedType.value = type;
  }

  /// Set description
  void setDescription(String desc) {
    description.value = desc;
  }

  /// Set message text
  void setMessageText(String text) {
    messageText.value = text;
  }

  /// Check if current user is involved in dispute
  bool isUserInvolved(Dispute dispute) {
    final currentUserId = _authController.currentUser.value?.id;
    return currentUserId != null && dispute.involvesUser(currentUserId);
  }

  /// Check if current user is admin
  bool get isAdmin {
    return _authController.currentUser.value?.userType == 'ADMIN';
  }

  /// Get dispute status color
  String getStatusColor(DisputeStatus status) {
    switch (status.value) {
      case 'OPEN':
        return '#FF9800'; // Orange
      case 'IN_MEDIATION':
        return '#2196F3'; // Blue
      case 'IN_ARBITRATION':
        return '#9C27B0'; // Purple
      case 'RESOLVED':
        return '#4CAF50'; // Green
      case 'CLOSED':
        return '#757575'; // Grey
      default:
        return '#757575';
    }
  }

  /// Get dispute type icon
  String getTypeIcon(DisputeType type) {
    switch (type.value) {
      case 'QUALITY':
        return '🔧';
      case 'PAYMENT':
        return '💰';
      case 'DELAY':
        return '⏰';
      case 'OTHER':
        return '❓';
      default:
        return '❓';
    }
  }

  /// Validate dispute form
  bool _validateDisputeForm() {
    if (selectedMissionId.value.isEmpty) {
      Get.snackbar(
        'Erreur',
        'Veuillez sélectionner une mission',
        snackPosition: SnackPosition.BOTTOM,
      );
      return false;
    }

    if (selectedDefendantId.value.isEmpty) {
      Get.snackbar(
        'Erreur',
        'Veuillez sélectionner un défendeur',
        snackPosition: SnackPosition.BOTTOM,
      );
      return false;
    }

    if (selectedType.value == null) {
      Get.snackbar(
        'Erreur',
        'Veuillez sélectionner un type de litige',
        snackPosition: SnackPosition.BOTTOM,
      );
      return false;
    }

    if (description.value.trim().length < 10) {
      Get.snackbar(
        'Erreur',
        'La description doit contenir au moins 10 caractères',
        snackPosition: SnackPosition.BOTTOM,
      );
      return false;
    }

    return true;
  }

  /// Clear dispute form
  void _clearDisputeForm() {
    selectedMissionId.value = '';
    selectedDefendantId.value = '';
    selectedType.value = null;
    description.value = '';
    evidenceUrls.clear();
    evidenceFiles.clear();
  }

  /// Refresh disputes
  @override
  Future<void> refresh() async {
    await loadUserDisputes();
  }
}
