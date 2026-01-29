import 'package:get/get.dart';
import 'dart:async';
import '../../domain/entities/user.dart';
import '../../domain/repositories/auth_repository.dart';
import '../../domain/usecases/login_usecase.dart';
import '../../domain/usecases/register_usecase.dart';
import '../../../../core/services/api/api_service.dart';
import '../../../../core/services/storage/offline_storage_service.dart';
import '../../../../core/services/monitoring/sentry_service.dart';
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

          // Set user context in Sentry
          await SentryService.setUser(
            id: currentUser.value!.id,
            email: currentUser.value!.email,
            username: currentUser.value!.userType,
            extra: {
              'user_type': currentUser.value!.userType,
              'account_status': currentUser.value!.accountStatus,
            },
          );
        }

        // Setup token refresh
        _setupTokenRefresh();
      }
    } catch (e, stackTrace) {
      isAuthenticated.value = false;
      currentUser.value = null;

      // Log error to Sentry
      await SentryService.captureException(
        e,
        stackTrace: stackTrace,
        hint: 'Error checking auth status',
      );
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

      // Add breadcrumb for tracking
      SentryService.addBreadcrumb(
        message: 'User attempting login',
        category: 'auth',
        data: {'email': email},
      );

      final result = await _loginUseCase(email: email, password: password);

      currentUser.value = result.user;
      isAuthenticated.value = true;

      // Save user offline
      await _offlineStorage.saveUser(result.user);

      // Set user context in Sentry
      await SentryService.setUser(
        id: result.user.id,
        email: result.user.email,
        username: result.user.userType,
        extra: {
          'user_type': result.user.userType,
          'account_status': result.user.accountStatus,
        },
      );

      // Setup token refresh
      _setupTokenRefresh();

      SentryService.addBreadcrumb(
        message: 'User logged in successfully',
        category: 'auth',
      );

      return true;
    } catch (e, stackTrace) {
      errorMessage.value = e.toString().replaceAll('Exception: ', '');

      // Log error to Sentry
      await SentryService.captureException(
        e,
        stackTrace: stackTrace,
        hint: 'Login failed',
        extra: {'email': email},
      );

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

      // Add breadcrumb
      SentryService.addBreadcrumb(
        message: 'User logging out',
        category: 'auth',
      );

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

      // Clear Sentry user context
      await SentryService.clearUser();

      // Reset state
      currentUser.value = null;
      isAuthenticated.value = false;
    } catch (e, stackTrace) {
      errorMessage.value = e.toString().replaceAll('Exception: ', '');

      // Log error to Sentry
      await SentryService.captureException(
        e,
        stackTrace: stackTrace,
        hint: 'Logout failed',
      );
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

      await _apiService.put('/api/v1/users/${user.id}', profileData);

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
