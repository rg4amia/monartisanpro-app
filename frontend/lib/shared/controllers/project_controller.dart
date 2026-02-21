import 'package:frontend/shared/models/quote_model.dart';
import 'package:get/get.dart';
import '../../core/network/project_service.dart';
import '../models/project_model.dart';
import '../models/escrow_model.dart';

class ProjectController extends GetxController {
  final ProjectService _projectService = ProjectService();

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
  Future<bool> createProject({
    required int? artisanId,
    required int tradeId,
    required String title,
    required String description,
    required double latitude,
    required double longitude,
    required String address,
    double? budgetMin,
    double? budgetMax,
    String? expectedCompletionDate,
  }) async {
    try {
      isCreatingProject.value = true;
      errorMessage.value = '';

      final response = await _projectService.createProject(
        artisanId: artisanId,
        tradeId: tradeId,
        title: title,
        description: description,
        latitude: latitude,
        longitude: longitude,
        address: address,
        budgetMin: budgetMin,
        budgetMax: budgetMax,
        expectedCompletionDate: expectedCompletionDate,
      );

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
        // TODO: Parse quotes from response when API returns them
        projectQuotes.clear();
      } else {
        errorMessage.value = response.message ?? 'Failed to fetch project';
      }
    } catch (e) {
      errorMessage.value = 'Network error: ${e.toString()}';
    } finally {
      isLoading.value = false;
    }
  }

  /// Create a quote (TODO: Implement when QuoteService is ready)
  Future<bool> createQuote({
    required int projectId,
    required double totalAmount,
    required double materialAmount,
    required double laborAmount,
    required List<Map<String, dynamic>> items,
    String? notes,
  }) async {
    try {
      isCreatingQuote.value = true;
      errorMessage.value = '';

      // TODO: Implement quote creation API call
      errorMessage.value = 'Quote creation not yet implemented';
      return false;
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

      // TODO: Implement quote acceptance API call
      errorMessage.value = 'Quote acceptance not yet implemented';
      return false;
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

      // TODO: Implement quote rejection API call
      errorMessage.value = 'Quote rejection not yet implemented';
      return false;
    } catch (e) {
      errorMessage.value = 'Network error: ${e.toString()}';
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  /// Fetch escrow wallet (TODO: Implement when API is ready)
  Future<void> fetchEscrowWallet(int projectId) async {
    try {
      // TODO: Implement escrow wallet API call
      escrowWallet.value = null;
    } catch (e) {
      // Silent fail - escrow may not exist yet
      escrowWallet.value = null;
    }
  }

  /// Fetch material token (TODO: Implement when API is ready)
  Future<void> fetchMaterialToken(int projectId) async {
    try {
      // TODO: Implement material token API call
      materialToken.value = null;
    } catch (e) {
      // Silent fail - token may not exist yet
      materialToken.value = null;
    }
  }

  /// Fetch project transactions (TODO: Implement when API is ready)
  Future<void> fetchProjectTransactions(int projectId) async {
    try {
      // TODO: Implement project transactions API call
      projectTransactions.value = [];
    } catch (e) {
      // Silent fail - transactions may not exist yet
      projectTransactions.value = [];
    }
  }

  /// Cancel a project
  Future<bool> cancelProject(int projectId, String reason) async {
    try {
      isLoading.value = true;
      errorMessage.value = '';

      final response = await _projectService.cancelProject(projectId, reason);

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
  int get pendingProjectsCount => myProjects.where((p) => p.isPending).length;

  /// Get in-progress projects count
  int get inProgressProjectsCount =>
      myProjects.where((p) => p.isInProgress).length;

  /// Get completed projects count
  int get completedProjectsCount =>
      myProjects.where((p) => p.isCompleted).length;
}
