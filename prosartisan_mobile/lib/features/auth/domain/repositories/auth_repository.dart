import 'dart:io';
import '../entities/auth_result.dart';
import '../entities/user.dart';

/// Authentication repository interface
abstract class AuthRepository {
  /// Login with email and password
  Future<AuthResult> login({required String email, required String password});

  /// Register a new user
  Future<AuthResult> register({
    required String email,
    required String password,
    required String userType,
    String? phoneNumber,
    String? tradeCategory,
    String? businessName,
    String? tradeName,
    int? sectorId,
    String? sectorName,
  });

  /// Generate OTP for phone verification
  Future<void> generateOtp({required String phoneNumber});

  /// Verify OTP code
  Future<bool> verifyOtp({required String phoneNumber, required String code});

  /// Upload KYC documents
  Future<void> uploadKyc({
    required String userId,
    required String idType,
    required String idNumber,
    required File idDocument,
    required File selfie,
  });

  /// Get current user
  Future<User?> getCurrentUser();

  /// Logout
  Future<void> logout();

  /// Check if user is authenticated
  Future<bool> isAuthenticated();
}
