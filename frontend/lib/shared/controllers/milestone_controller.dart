import 'package:get/get.dart';
import '../../core/network/milestone_service.dart';
import '../models/milestone_model.dart';

class MilestoneController extends GetxController {
  final MilestoneService _milestoneService = MilestoneService();

  // Milestone state
  final RxList<Milestone> projectMilestones = <Milestone>[].obs;
  final Rx<ProjectProgress?> projectProgress = Rx<ProjectProgress?>(null);
  final RxList<LaborPayment> laborPayments = <LaborPayment>[].obs;

  // Loading states
  final RxBool isLoading = false.obs;
  final RxBool isCreating = false.obs;
  final RxBool isCompleting = false.obs;
  final RxBool isValidating = false.obs;
  final RxBool isSendingOtp = false.obs;
  final RxString errorMessage = ''.obs;

  /// Fetch milestones for a project
  Future<void> fetchProjectMilestones(int projectId) async {
    try {
      isLoading.value = true;
      errorMessage.value = '';

      final response = await _milestoneService.getProjectMilestones(projectId);

      if (response.success && response.data != null) {
        projectMilestones.value = response.data!;
      } else {
        errorMessage.value = response.message ?? 'Failed to fetch milestones';
      }
    } catch (e) {
      errorMessage.value = 'Network error: ${e.toString()}';
    } finally {
      isLoading.value = false;
    }
  }

  /// Create a new milestone
  Future<bool> createMilestone(CreateMilestoneRequest request) async {
    try {
      isCreating.value = true;
      errorMessage.value = '';

      final response = await _milestoneService.createMilestone(request);

      if (response.success && response.data != null) {
        projectMilestones.add(response.data!);
        projectMilestones.sort((a, b) => a.sequenceOrder.compareTo(b.sequenceOrder));
        return true;
      } else {
        errorMessage.value = response.message ?? 'Failed to create milestone';
        return false;
      }
    } catch (e) {
      errorMessage.value = 'Network error: ${e.toString()}';
      return false;
    } finally {
      isCreating.value = false;
    }
  }

  /// Complete a milestone (artisan)
  Future<bool> completeMilestone(CompleteMilestoneRequest request) async {
    try {
      isCompleting.value = true;
      errorMessage.value = '';

      final response = await _milestoneService.completeMilestone(request);

      if (response.success && response.data != null) {
        // Update milestone in list
        final index = projectMilestones.indexWhere((m) => m.id == request.milestoneId);
        if (index != -1) {
          projectMilestones[index] = response.data!;
        }
        return true;
      } else {
        errorMessage.value = response.message ?? 'Failed to complete milestone';
        return false;
      }
    } catch (e) {
      errorMessage.value = 'Network error: ${e.toString()}';
      return false;
    } finally {
      isCompleting.value = false;
    }
  }

  /// Send OTP for milestone validation
  Future<bool> sendMilestoneOtp(int milestoneId) async {
    try {
      isSendingOtp.value = true;
      errorMessage.value = '';

      final response = await _milestoneService.sendMilestoneOtp(milestoneId);

      if (response.success) {
        return true;
      } else {
        errorMessage.value = response.message ?? 'Failed to send OTP';
        return false;
      }
    } catch (e) {
      errorMessage.value = 'Network error: ${e.toString()}';
      return false;
    } finally {
      isSendingOtp.value = false;
    }
  }

  /// Validate a milestone (client)
  Future<bool> validateMilestone(ValidateMilestoneRequest request) async {
    try {
      isValidating.value = true;
      errorMessage.value = '';

      final response = await _milestoneService.validateMilestone(request);

      if (response.success && response.data != null) {
        // Update milestone in list
        final index = projectMilestones.indexWhere((m) => m.id == request.milestoneId);
        if (index != -1) {
          projectMilestones[index] = response.data!;
        }
        return true;
      } else {
        errorMessage.value = response.message ?? 'Failed to validate milestone';
        return false;
      }
    } catch (e) {
      errorMessage.value = 'Network error: ${e.toString()}';
      return false;
    } finally {
      isValidating.value = false;
    }
  }

  /// Fetch project progress
  Future<void> fetchProjectProgress(int projectId) async {
    try {
      final response = await _milestoneService.getProjectProgress(projectId);

      if (response.success && response.data != null) {
        projectProgress.value = response.data;
      }
    } catch (e) {
      // Silent fail
    }
  }

  /// Fetch labor payments
  Future<void> fetchLaborPayments(int projectId) async {
    try {
      final response = await _milestoneService.getProjectPayments(projectId);

      if (response.success && response.data != null) {
        laborPayments.value = response.data!;
      }
    } catch (e) {
      // Silent fail
      laborPayments.value = [];
    }
  }

  /// Reorder milestones
  Future<bool> reorderMilestones(int projectId, List<int> milestoneIds) async {
    try {
      errorMessage.value = '';

      final response = await _milestoneService.reorderMilestones(
        projectId,
        milestoneIds,
      );

      if (response.success && response.data != null) {
        projectMilestones.value = response.data!;
        return true;
      } else {
        errorMessage.value = response.message ?? 'Failed to reorder milestones';
        return false;
      }
    } catch (e) {
      errorMessage.value = 'Network error: ${e.toString()}';
      return false;
    }
  }

  /// Delete a milestone
  Future<bool> deleteMilestone(int milestoneId) async {
    try {
      errorMessage.value = '';

      final response = await _milestoneService.deleteMilestone(milestoneId);

      if (response.success) {
        projectMilestones.removeWhere((m) => m.id == milestoneId);
        return true;
      } else {
        errorMessage.value = response.message ?? 'Failed to delete milestone';
        return false;
      }
    } catch (e) {
      errorMessage.value = 'Network error: ${e.toString()}';
      return false;
    }
  }

  /// Refresh all milestone data
  Future<void> refreshMilestoneData(int projectId) async {
    await Future.wait([
      fetchProjectMilestones(projectId),
      fetchProjectProgress(projectId),
      fetchLaborPayments(projectId),
    ]);
  }

  // Helper getters
  int get pendingMilestonesCount =>
      projectMilestones.where((m) => m.isPending).length;

  int get completedMilestonesCount =>
      projectMilestones.where((m) => m.isCompleted || m.isValidated || m.isPaid).length;

  int get validatedMilestonesCount =>
      projectMilestones.where((m) => m.isValidated || m.isPaid).length;

  List<Milestone> get pendingMilestones =>
      projectMilestones.where((m) => m.isPending || m.isInProgress).toList();

  List<Milestone> get awaitingValidation =>
      projectMilestones.where((m) => m.isCompleted).toList();

  double get totalLaborAmount =>
      projectMilestones.fold(0.0, (sum, m) => sum + m.laborAmount);

  double get releasedLaborAmount =>
      projectMilestones
          .where((m) => m.isPaid)
          .fold(0.0, (sum, m) => sum + m.laborAmount);

  /// Reset state
  void reset() {
    projectMilestones.clear();
    projectProgress.value = null;
    laborPayments.clear();
    errorMessage.value = '';
  }
}
