import 'package:get/get.dart';
import '../../core/network/project_service.dart';
import '../../core/network/payment_service.dart';
import '../models/project_model.dart';
import '../models/escrow_model.dart';

class ProjectController extends GetxController {
  final ProjectService _projectService = ProjectService();
  final PaymentService _paymentService = PaymentService();

  // Projects
  final RxList<Project> myProjects = <Project>[].obs;
  final Rx<Project?> currentProject = Rx<Project?>(null);

  // Quotes
  final RxList<Quote> projectQuotes = <Quote>[].obs;
  final Rx<Quote?> currentQuote = Rx<Quote?>(null);

  // Escrow & Token
  final Rx<EscrowWallet?> escrowWallet = Rx<EscrowWallet?>(null);
  final Rx<MaterialToken?> materialToken = Rx<MaterialToken?>(null);
  final RxList<Transaction> projectTransactions = <Transaction>[].obs;

  // Loading states
  final RxBool isLoading = false.obs;
  final RxBool isCreatingProject = false.obs;
  final RxBool isCreatingQuote = false.obs;
  final RxString errorMessage = ''.obs;

  /// Fetch user's projects
  Future<void> fetchMyProjects({String? status}) async {
    try {
      isLoading.value = true;
      errorMessage.value = '';

      final response = await _projectService.getMyProjects(status: status);

      if (response.success && response.data != null) {
        myProjects.value = response.data!;
      } else {
        errorMessage.value = response.message ?? 'Failed to fetch projects';
      }
    } catch (e) {
      errorMessage.value = 'Network error: ${e.toString()}';
    } finally {
      isLoading.value = false;
    }
  }

  /// Create a new project
  Future<bool> createProject(CreateProjectRequest request) async {
    try {
      isCreatingProject.value = true;
      errorMessage.value = '';

      final response = await _projectService.createProject(request);

      if (response.success && response.data != null) {
        currentProject.value = response.data;
        myProjects.insert(0, response.data!);
        return true;
      } else {
        errorMessage.value = response.message ?? 'Failed to create project';
        return false;
      }
    } catch (e) {
      errorMessage.value = 'Network error: ${e.toString()}';
      return false;
    } finally {
      isCreatingProject.value = false;
    }
  }

  /// Get project details
  Future<void> fetchProjectDetails(int projectId) async {
    try {
      isLoading.value = true;
      errorMessage.value = '';

      final response = await _projectService.getProject(projectId);

      if (response.success && response.data != null) {
        currentProject.value = response.data;
        projectQuotes.value = response.data!.quotes ?? [];
      } else {
        errorMessage.value = response.message ?? 'Failed to fetch project';
      }
    } catch (e) {
      errorMessage.value = 'Network error: ${e.toString()}';
    } finally {
      isLoading.value = false;
    }
  }

  /// Create a quote
  Future<bool> createQuote(CreateQuoteRequest request) async {
    try {
      isCreatingQuote.value = true;
      errorMessage.value = '';

      final response = await _projectService.createQuote(request);

      if (response.success && response.data != null) {
        currentQuote.value = response.data;
        projectQuotes.add(response.data!);
        return true;
      } else {
        errorMessage.value = response.message ?? 'Failed to create quote';
        return false;
      }
    } catch (e) {
      errorMessage.value = 'Network error: ${e.toString()}';
      return false;
    } finally {
      isCreatingQuote.value = false;
    }
  }

  /// Accept a quote
  Future<bool> acceptQuote(int quoteId) async {
    try {
      isLoading.value = true;
      errorMessage.value = '';

      final response = await _projectService.acceptQuote(quoteId);

      if (response.success && response.data != null) {
        currentQuote.value = response.data;
        // Refresh project to get updated status
        if (currentProject.value != null) {
          await fetchProjectDetails(currentProject.value!.id);
        }
        return true;
      } else {
        errorMessage.value = response.message ?? 'Failed to accept quote';
        return false;
      }
    } catch (e) {
      errorMessage.value = 'Network error: ${e.toString()}';
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  /// Reject a quote
  Future<bool> rejectQuote(int quoteId, String? reason) async {
    try {
      isLoading.value = true;
      errorMessage.value = '';

      final response = await _projectService.rejectQuote(quoteId, reason);

      if (response.success) {
        // Remove from quotes list
        projectQuotes.removeWhere((q) => q.id == quoteId);
        if (currentQuote.value?.id == quoteId) {
          currentQuote.value = null;
        }
        return true;
      } else {
        errorMessage.value = response.message ?? 'Failed to reject quote';
        return false;
      }
    } catch (e) {
      errorMessage.value = 'Network error: ${e.toString()}';
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  /// Fetch escrow wallet
  Future<void> fetchEscrowWallet(int projectId) async {
    try {
      final response = await _projectService.getEscrowWallet(projectId);

      if (response.success && response.data != null) {
        escrowWallet.value = response.data;
      }
    } catch (e) {
      // Silent fail - escrow may not exist yet
    }
  }

  /// Fetch material token
  Future<void> fetchMaterialToken(int projectId) async {
    try {
      final response = await _projectService.getMaterialToken(projectId);

      if (response.success && response.data != null) {
        materialToken.value = response.data;
      }
    } catch (e) {
      // Silent fail - token may not exist yet
    }
  }

  /// Fetch project transactions
  Future<void> fetchProjectTransactions(int projectId) async {
    try {
      final response = await _paymentService.getProjectTransactions(projectId);

      if (response.success && response.data != null) {
        projectTransactions.value = response.data!;
      }
    } catch (e) {
      // Silent fail - transactions may not exist yet
      projectTransactions.value = [];
    }
  }

  /// Cancel a project
  Future<bool> cancelProject(int projectId) async {
    try {
      isLoading.value = true;
      errorMessage.value = '';

      final response = await _projectService.cancelProject(projectId);

      if (response.success) {
        myProjects.removeWhere((p) => p.id == projectId);
        if (currentProject.value?.id == projectId) {
          currentProject.value = null;
        }
        return true;
      } else {
        errorMessage.value = response.message ?? 'Failed to cancel project';
        return false;
      }
    } catch (e) {
      errorMessage.value = 'Network error: ${e.toString()}';
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  /// Clear current project
  void clearCurrentProject() {
    currentProject.value = null;
    projectQuotes.clear();
    currentQuote.value = null;
    escrowWallet.value = null;
    materialToken.value = null;
  }

  /// Get projects by status
  List<Project> getProjectsByStatus(String status) {
    return myProjects.where((p) => p.status == status).toList();
  }

  /// Get pending projects count
  int get pendingProjectsCount =>
      myProjects.where((p) => p.isPending).length;

  /// Get in-progress projects count
  int get inProgressProjectsCount =>
      myProjects.where((p) => p.isInProgress).length;

  /// Get completed projects count
  int get completedProjectsCount =>
      myProjects.where((p) => p.isCompleted).length;
}
