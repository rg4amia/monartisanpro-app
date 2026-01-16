import 'dart:io';
import 'package:dio/dio.dart';
import '../../../../core/services/api/api_client.dart';
import '../../../../core/constants/api_constants.dart';
import '../models/auth_result_model.dart';
import '../models/user_model.dart';

/// Remote data source for authentication
class AuthRemoteDataSource {
  final ApiClient _apiClient;

  AuthRemoteDataSource(this._apiClient);

  /// Login with email and password
  Future<AuthResultModel> login({
    required String email,
    required String password,
  }) async {
    try {
      final response = await _apiClient.post(
        ApiConstants.login,
        data: {'email': email, 'password': password},
      );

      return AuthResultModel.fromJson(response.data as Map<String, dynamic>);
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  /// Register a new user
  Future<AuthResultModel> register({
    required String email,
    required String password,
    required String userType,
    String? phoneNumber,
    String? tradeCategory,
    String? businessName,
  }) async {
    try {
      final data = {
        'email': email,
        'password': password,
        'user_type': userType,
        if (phoneNumber != null) 'phone_number': phoneNumber,
        if (tradeCategory != null) 'trade_category': tradeCategory,
        if (businessName != null) 'business_name': businessName,
      };

      final response = await _apiClient.post(ApiConstants.register, data: data);

      return AuthResultModel.fromJson(response.data as Map<String, dynamic>);
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  /// Generate OTP for phone verification
  Future<void> generateOtp({required String phoneNumber}) async {
    try {
      await _apiClient.post(
        ApiConstants.otpGenerate,
        data: {'phone_number': phoneNumber},
      );
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  /// Verify OTP code
  Future<bool> verifyOtp({
    required String phoneNumber,
    required String code,
  }) async {
    try {
      final response = await _apiClient.post(
        ApiConstants.otpVerify,
        data: {'phone_number': phoneNumber, 'code': code},
      );

      return response.data['verified'] as bool? ?? false;
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  /// Upload KYC documents
  Future<void> uploadKyc({
    required String userId,
    required String idType,
    required String idNumber,
    required File idDocument,
    required File selfie,
  }) async {
    try {
      final path = ApiConstants.kycUpload.replaceAll('{id}', userId);

      await _apiClient.uploadFile(path, {
        'id_type': idType,
        'id_number': idNumber,
        'id_document': await MultipartFile.fromFile(
          idDocument.path,
          filename: 'id_document.jpg',
        ),
        'selfie': await MultipartFile.fromFile(
          selfie.path,
          filename: 'selfie.jpg',
        ),
      });
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  /// Get current user
  Future<UserModel?> getCurrentUser() async {
    try {
      // This would typically call a /me endpoint
      // For now, we'll return null if not implemented
      return null;
    } on DioException catch (e) {
      throw _handleError(e);
    }
  }

  /// Handle Dio errors
  Exception _handleError(DioException error) {
    if (error.type == DioExceptionType.connectionTimeout ||
        error.type == DioExceptionType.receiveTimeout) {
      return Exception(
        'Connection timeout. Please check your internet connection.',
      );
    }

    if (error.type == DioExceptionType.connectionError) {
      return Exception('Network error. Please check your internet connection.');
    }

    if (error.response != null) {
      final statusCode = error.response!.statusCode;
      final data = error.response!.data;

      if (statusCode == 401) {
        return Exception('Invalid credentials');
      }

      if (statusCode == 403) {
        return Exception('Account locked. Please try again later.');
      }

      if (statusCode == 422) {
        // Validation error
        if (data is Map<String, dynamic> && data.containsKey('message')) {
          return Exception(data['message']);
        }
        return Exception('Validation error');
      }

      if (data is Map<String, dynamic> && data.containsKey('message')) {
        return Exception(data['message']);
      }

      return Exception('Server error: $statusCode');
    }

    return Exception('Unknown error occurred');
  }
}
