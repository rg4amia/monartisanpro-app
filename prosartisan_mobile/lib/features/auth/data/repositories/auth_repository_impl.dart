import 'dart:io';
import '../../../../core/services/api/api_client.dart';
import '../../domain/entities/auth_result.dart';
import '../../domain/entities/user.dart';
import '../../domain/repositories/auth_repository.dart';
import '../datasources/auth_remote_datasource.dart';

/// Implementation of AuthRepository
class AuthRepositoryImpl implements AuthRepository {
  final AuthRemoteDataSource _remoteDataSource;
  final ApiClient _apiClient;

  AuthRepositoryImpl(this._remoteDataSource, this._apiClient);

  @override
  Future<AuthResult> login({
    required String email,
    required String password,
  }) async {
    final result = await _remoteDataSource.login(
      email: email,
      password: password,
    );

    // Save token
    await _apiClient.saveToken(result.token);

    return result.toEntity();
  }

  @override
  Future<AuthResult> register({
    required String email,
    required String password,
    required String userType,
    String? phoneNumber,
    String? tradeCategory,
    String? businessName,
  }) async {
    final result = await _remoteDataSource.register(
      email: email,
      password: password,
      userType: userType,
      phoneNumber: phoneNumber,
      tradeCategory: tradeCategory,
      businessName: businessName,
    );

    // Save token
    await _apiClient.saveToken(result.token);

    return result.toEntity();
  }

  @override
  Future<void> generateOtp({required String phoneNumber}) async {
    await _remoteDataSource.generateOtp(phoneNumber: phoneNumber);
  }

  @override
  Future<bool> verifyOtp({
    required String phoneNumber,
    required String code,
  }) async {
    return await _remoteDataSource.verifyOtp(
      phoneNumber: phoneNumber,
      code: code,
    );
  }

  @override
  Future<void> uploadKyc({
    required String userId,
    required String idType,
    required String idNumber,
    required File idDocument,
    required File selfie,
  }) async {
    await _remoteDataSource.uploadKyc(
      userId: userId,
      idType: idType,
      idNumber: idNumber,
      idDocument: idDocument,
      selfie: selfie,
    );
  }

  @override
  Future<User?> getCurrentUser() async {
    final userModel = await _remoteDataSource.getCurrentUser();
    return userModel?.toEntity();
  }

  @override
  Future<void> logout() async {
    await _apiClient.clearToken();
  }

  @override
  Future<bool> isAuthenticated() async {
    return await _apiClient.isAuthenticated();
  }
}
