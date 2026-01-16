import 'package:get/get.dart';
import '../../domain/entities/user.dart';
import '../../domain/repositories/auth_repository.dart';
import '../../domain/usecases/login_usecase.dart';
import '../../domain/usecases/register_usecase.dart';

/// Controller for authentication state management
class AuthController extends GetxController {
  final LoginUseCase _loginUseCase;
  final RegisterUseCase _registerUseCase;
  final AuthRepository _authRepository;

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

  @override
  void onInit() {
    super.onInit();
    checkAuthStatus();
  }

  /// Check if user is authenticated
  Future<void> checkAuthStatus() async {
    try {
      isAuthenticated.value = await _authRepository.isAuthenticated();
      if (isAuthenticated.value) {
        currentUser.value = await _authRepository.getCurrentUser();
      }
    } catch (e) {
      isAuthenticated.value = false;
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
      await _authRepository.logout();
      currentUser.value = null;
      isAuthenticated.value = false;
    } catch (e) {
      errorMessage.value = e.toString().replaceAll('Exception: ', '');
    }
  }

  /// Clear error message
  void clearError() {
    errorMessage.value = '';
  }
}
