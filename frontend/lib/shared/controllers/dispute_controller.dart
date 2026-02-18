import 'package:get/get.dart';
import '../../core/network/dispute_service.dart';
import '../models/dispute_model.dart';

class DisputeController extends GetxController {
  final DisputeService _disputeService = DisputeService();

  // Disputes
  final RxList<Dispute> disputes = <Dispute>[].obs;
  final RxBool isLoadingDisputes = false.obs;
  final RxString disputesFilter = 'all'.obs;

  // Current dispute
  final Rx<Dispute?> currentDispute = Rx<Dispute?>(null);
  final RxList<DisputeMessage> messages = <DisputeMessage>[].obs;
  final RxBool isLoadingMessages = false.obs;
  final RxBool isSendingMessage = false.obs;

  // Error handling
  final RxString errorMessage = ''.obs;

  /// Load disputes
  Future<void> loadDisputes({String? status}) async {
    try {
      isLoadingDisputes.value = true;
      errorMessage.value = '';

      final response = await _disputeService.getDisputes(status: status);

      if (response.success && response.data != null) {
        disputes.value = response.data!;
      } else {
        errorMessage.value = response.message ?? 'Erreur de chargement';
        disputes.value = [];
      }
    } catch (e) {
      errorMessage.value = 'Erreur: ${e.toString()}';
      disputes.value = [];
    } finally {
      isLoadingDisputes.value = false;
    }
  }

  /// Load dispute details
  Future<void> loadDisputeDetails(int disputeId) async {
    try {
      isLoadingMessages.value = true;
      errorMessage.value = '';

      final response = await _disputeService.getDisputeDetails(disputeId);

      if (response.success && response.data != null) {
        currentDispute.value = response.data;
        // TODO: Load messages separately when endpoint is available
        messages.value = [];
      } else {
        errorMessage.value = response.message ?? 'Erreur de chargement';
      }
    } catch (e) {
      errorMessage.value = 'Erreur: ${e.toString()}';
    } finally {
      isLoadingMessages.value = false;
    }
  }

  /// Send message
  Future<bool> sendMessage(int disputeId, String message) async {
    try {
      isSendingMessage.value = true;
      errorMessage.value = '';

      final response = await _disputeService.sendMessage(disputeId, message);

      if (response.success) {
        // Reload messages
        await loadDisputeDetails(disputeId);
        return true;
      } else {
        errorMessage.value = response.message ?? 'Erreur d\'envoi';
        return false;
      }
    } catch (e) {
      errorMessage.value = 'Erreur: ${e.toString()}';
      return false;
    } finally {
      isSendingMessage.value = false;
    }
  }

  /// Create dispute
  Future<bool> createDispute({
    required int projectId,
    required String subject,
    required String description,
  }) async {
    try {
      errorMessage.value = '';

      final response = await _disputeService.createDispute(
        projectId: projectId,
        subject: subject,
        description: description,
      );

      if (response.success) {
        // Reload disputes
        await loadDisputes();
        return true;
      } else {
        errorMessage.value = response.message ?? 'Erreur de création';
        return false;
      }
    } catch (e) {
      errorMessage.value = 'Erreur: ${e.toString()}';
      return false;
    }
  }

  /// Filter disputes by status
  void filterDisputes(String status) {
    disputesFilter.value = status;
    loadDisputes(status: status == 'all' ? null : status);
  }

  /// Get filtered disputes
  List<Dispute> getFilteredDisputes(String status) {
    if (status == 'all') return disputes;
    return disputes.where((d) => d.status == status).toList();
  }

  /// Refresh disputes
  Future<void> refreshDisputes() async {
    await loadDisputes(
      status: disputesFilter.value == 'all' ? null : disputesFilter.value,
    );
  }
}
