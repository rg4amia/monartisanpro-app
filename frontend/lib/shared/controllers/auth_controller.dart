import 'package:get/get.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../../core/network/auth_service.dart';
import '../../core/network/dio_client.dart';
import '../../core/constants/api_constants.dart';
import '../models/user_model.dart';

class AuthController extends GetxController {
  final AuthService _authService = AuthService();
  final DioClient _dioClient = DioClient();
  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  // Observable state
  final Rx<User?> currentUser = Rx<User?>(null);
  final RxBool isLoading = false.obs;
  final RxBool isAuthenticated = false.obs;
  final RxString errorMessage = ''.obs;

  @override
  void onInit() {
    super.onInit();
    checkAuthStatus();
  }

  /// Check if user is already authenticated
  Future<void> checkAuthStatus() async {
    try {
      final token = await _dioClient.getAuthToken();

      if (token != null && token.isNotEmpty) {
        print('Token found, fetching user data...');

        // Try to fetch current user with the stored token
        await fetchCurrentUser();

        if (currentUser.value != null) {
          print('User authenticated: ${currentUser.value!.name}');
          isAuthenticated.value = true;
        } else {
          print('Token invalid, clearing session...');
          await _clearSession();
        }
      } else {
        print('No token found, user not authenticated');
        isAuthenticated.value = false;
      }
    } catch (e) {
      print('Error checking auth status: $e');
      await _clearSession();
    }
  }

  /// Clear session data
  Future<void> _clearSession() async {
    currentUser.value = null;
    isAuthenticated.value = false;
    await _dioClient.clearAuthToken();
    await _storage.delete(key: ApiConstants.userDataKey);
  }

  /// Fetch current user
  Future<void> fetchCurrentUser() async {
    try {
      final response = await _authService.getCurrentUser();

      if (response.success && response.data != null) {
        currentUser.value = response.data;
        isAuthenticated.value = true;

        // Update stored user data
        await _storage.write(
          key: ApiConstants.userDataKey,
          value: response.data!.toJson().toString(),
        );
      } else {
        print('Failed to fetch user: ${response.message}');
        await _clearSession();
      }
    } catch (e) {
      print('Error fetching user: $e');
      await _clearSession();
    }
  }

  /// Register user
  Future<bool> register({
    required String name,
    required String email,
    required String phone,
    required String password,
    required String passwordConfirmation,
    required String role,
    int? tradeId,
    int? experienceYears,
    String? bio,
  }) async {
    try {
      isLoading.value = true;
      errorMessage.value = '';

      final response = await _authService.register(
        name: name,
        email: email,
        phone: phone,
        password: password,
        passwordConfirmation: passwordConfirmation,
        role: role,
        tradeId: tradeId,
        experienceYears: experienceYears,
        bio: bio,
      );

      if (response.success && response.data != null) {
        currentUser.value = response.data!.user;
        await _dioClient.setAuthToken(response.data!.token);

        // Save user data
        await _storage.write(
          key: ApiConstants.userDataKey,
          value: response.data!.user.toJson().toString(),
        );

        isAuthenticated.value = true;
        return true;
      } else {
        errorMessage.value = response.message ?? 'Registration failed';
        if (response.errors != null) {
          errorMessage.value = response.errors!.values.first.toString();
        }
        return false;
      }
    } catch (e) {
      errorMessage.value = 'Network error: $e';
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  /// Login user
  Future<bool> login({
    required String email,
    required String password,
    String? role,
  }) async {
    try {
      isLoading.value = true;
      errorMessage.value = '';

      final response = await _authService.login(
        email: email,
        password: password,
        role: role,
      );

      if (response.success && response.data != null) {
        currentUser.value = response.data!.user;
        await _dioClient.setAuthToken(response.data!.token);

        // Save user data
        await _storage.write(
          key: ApiConstants.userDataKey,
          value: response.data!.user.toJson().toString(),
        );

        isAuthenticated.value = true;
        return true;
      } else {
        errorMessage.value = response.message ?? 'Login failed';
        return false;
      }
    } catch (e) {
      errorMessage.value = 'Network error: $e';
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  /// Logout user
  Future<void> logout() async {
    try {
      isLoading.value = true;

      // Call backend logout endpoint
      await _authService.logout();
    } catch (e) {
      print('Logout error: $e');
    } finally {
      // Clear local session regardless of backend response
      await _clearSession();
      isLoading.value = false;
    }
  }

  /// Send OTP
  Future<bool> sendOtp(String phone) async {
    try {
      isLoading.value = true;
      errorMessage.value = '';

      final response = await _authService.sendOtp(phone: phone);

      if (response.success) {
        return true;
      } else {
        errorMessage.value = response.message ?? 'Failed to send OTP';
        return false;
      }
    } catch (e) {
      errorMessage.value = 'Network error: $e';
      return false;
    } finally {
      isLoading.value = false;
    }
  }

  /// Verify OTP
  Future<bool> verifyOtp({required String phone, required String otp}) async {
    try {
      isLoading.value = true;
      errorMessage.value = '';

      final response = await _authService.verifyOtp(phone: phone, otp: otp);

      if (response.success) {
        // Refresh user data
        await fetchCurrentUser();
        return true;
      } else {
        errorMessage.value = response.message ?? 'Invalid OTP';
        return false;
      }
    } catch (e) {
      errorMessage.value = 'Network error: $e';
      return false;
    } finally {
      isLoading.value = false;
    }
  }
}
