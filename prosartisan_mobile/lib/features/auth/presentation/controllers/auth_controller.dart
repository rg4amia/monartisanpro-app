import 'package:get/get.dart';
import 'dart:async';
import '../../domain/entities/user.dart';
import '../../domain/repositories/auth_repository.dart';
import '../../domain/usecases/login_usecase.dart';
import '../../domain/usecases/register_usecase.dart';
import '../../../../core/services/api/api_service.dart';
import '../../../../core/services/storage/offline_storage_service.dart';
import '../../../../core/constants/api_constants.dart';

/// Controller for authentication state management
class AuthController extends GetxController {
  final LoginUseCase _loginUseCase;
  final RegisterUseCase _registerUseCase;
  final AuthRepository _authRepository;
  final ApiService _apiService = Get.find<ApiService>();
  final OfflineStorageService _offlineStorage = OfflineStorageService();

  AuthController(
    this._loginUseCase,
    this._registerUseCase,
    this._authRepository,
  );

  // Observable state
  final Rx<User?> currentUser = Rx<User?>(null);
  final RxBool isLoading = false.obs;
  final RxString errorMessage = ''.obs;
  final RxBool isAuthenticated = false.obs;

  Timer? _tokenRefreshTimer;

  @override
  void onInit() {
    super.onInit();
    checkAuthStatus();
  }

  @override
  void onClose() {
    _tokenRefreshTimer?.cancel();
    super.onClose();
  }

  /// Check if user is authenticated and setup token refresh
  Future<void> checkAuthStatus() async {
    try {
      isAuthenticated.value = await _authRepository.isAuthenticated();
      if (isAuthenticated.value) {
        currentUser.value = await _authRepository.getCurrentUser();

        // Save user offline for offline access
        if (currentUser.value != null) {
          await _offlineStorage.saveUser(currentUser.value!);
        }

        // Setup token refresh
        _setupTokenRefresh();
      }
    } catch (e) {
      isAuthenticated.value = false;
      currentUser.value = null;
    }
  }

  /// Setup automatic token refresh
  void _setupTokenRefresh() {
    // Refresh token every 20 hours (tokens expire in 24 hours)
    _tokenRefreshTimer?.cancel();
    _tokenRefreshTimer = Timer.periodic(
      const Duration(hours: 20),
      (_) => _refreshToken(),
    );
  }

  /// Refresh authentication token
  Future<void> _refreshToken() async {
    try {
      if (!await _apiService.isAuthenticated()) return;

      final response = await _apiService.post(ApiConstants.refresh, {});
      final newToken = response.data['token'];

      if (newToken != null) {
        await _apiService.saveToken(newToken);
      }
    } catch (e) {
      // If refresh fails, logout user
      await logout();
    }
  }

  /// Login with email and password
  Future<bool> login({required String email, required String password}) async {
    try {
      isLoading.value = true;
      errorMessage.value = '';

      final result = await _loginUseCase(email: email, password: password);

      currentUser.value = result.user;
      isAuthenticated.value = true;

      // Save user offline
      await _offlineStorage.saveUser(result.user);

      // Setup token refresh
      _setupTokenRefresh();

      return true;
    } catch (e) {
      errorMessage.value = e.toString().replaceAll('Exception: ', '');
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  /// Register a new user
  Future<bool> register({
    required String email,
    required String password,
    required String userType,
    String? phoneNumber,
    String? tradeCategory,
    String? businessName,
  }) async {
    try {
      isLoading.value = true;
      errorMessage.value = '';

      final result = await _registerUseCase(
        email: email,
        password: password,
        userType: userType,
        phoneNumber: phoneNumber,
        tradeCategory: tradeCategory,
        businessName: businessName,
      );

      currentUser.value = result.user;
      isAuthenticated.value = true;

      // Save user offline
      await _offlineStorage.saveUser(result.user);

      // Setup token refresh
      _setupTokenRefresh();

      return true;
    } catch (e) {
      errorMessage.value = e.toString().replaceAll('Exception: ', '');
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  /// Logout
  Future<void> logout() async {
    try {
      // Cancel token refresh timer
      _tokenRefreshTimer?.cancel();

      // Clear server session
      try {
        await _apiService.post(ApiConstants.logout, {});
      } catch (e) {
        // Ignore logout API errors
      }

      // Clear local auth data
      await _authRepository.logout();
      await _apiService.clearToken();

      // Clear offline data
      await _offlineStorage.clearAllData();

      // Reset state
      currentUser.value = null;
      isAuthenticated.value = false;
    } catch (e) {
      errorMessage.value = e.toString().replaceAll('Exception: ', '');
    }
  }

  /// Check if current user needs KYC verification
  bool get needsKYCVerification {
    final user = currentUser.value;
    if (user == null) return false;

    return (user.isArtisan || user.isFournisseur) &&
        (user.isKycVerified != true);
  }

  /// Check if current user account is active
  bool get isAccountActive {
    final user = currentUser.value;
    if (user == null) return false;

    return user.isActive;
  }

  /// Get user from offline storage (for offline mode)
  Future<User?> getOfflineUser() async {
    final user = currentUser.value;
    if (user != null) {
      return await _offlineStorage.getUser(user.id);
    }
    return null;
  }

  /// Update user profile
  Future<bool> updateProfile(Map<String, dynamic> profileData) async {
    try {
      isLoading.value = true;
      errorMessage.value = '';

      final user = currentUser.value;
      if (user == null) return false;

      final response = await _apiService.put(
        '/api/v1/users/${user.id}',
        profileData,
      );

      // Update current user with new data
      // This would need to be implemented based on the actual response structure

      return true;
    } catch (e) {
      errorMessage.value = e.toString().replaceAll('Exception: ', '');
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  /// Clear error message
  void clearError() {
    errorMessage.value = '';
  }

  /// Force token refresh (manual trigger)
  Future<void> forceTokenRefresh() async {
    await _refreshToken();
  }
}
