import 'package:dio/dio.dart';

import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/storage/storage_service.dart';
import '../models/user_model.dart';

class AuthRepository {
  final ApiClient _client = ApiClient();

  Future<void> sendOtp(String phone) async {
    await _client.post(ApiEndpoints.sendOtp, data: {'phone': phone});
  }

  Future<Map<String, dynamic>> verifyOtp(String phone, String otp) async {
    final res = await _client.post(ApiEndpoints.verifyOtp, data: {
      'phone': phone,
      'otp': otp,
      'device_fingerprint': StorageService.getDeviceFingerprint(),
    });

    final hasCompletedProfile = res.data['has_completed_profile'] as bool? ?? false;

    // Si le profil est complet, on sauvegarde le token
    if (hasCompletedProfile) {
      final token = res.data['token'] as String?;
      if (token != null) await StorageService.saveToken(token);
    }

    return {
      'has_completed_profile': hasCompletedProfile,
      'token': res.data['token'] as String?,
      'user': res.data['user'],
      'phone': res.data['phone'] as String?,
    };
  }

  Future<Map<String, dynamic>> register({
    required String phone,
    required String role,
    required String name,
  }) async {
    final res = await _client.post(ApiEndpoints.register, data: {
      'phone': phone,
      'role': role,
      'name': name,
    });

    final token = res.data['token'] as String?;
    if (token != null) {
      await StorageService.saveToken(token);
    }

    return {
      'token': token,
      'user': UserModel.fromJson(res.data['user'] as Map<String, dynamic>),
    };
  }

  Future<UserModel> me() async {
    final res = await _client.get(ApiEndpoints.me);
    final payload =
        (res.data['data'] ?? res.data['user']) as Map<String, dynamic>;
    return UserModel.fromJson(payload);
  }

  Future<void> logout() async {
    try {
      await _client.post(ApiEndpoints.logout);
    } on DioException {
      // ignore errors on logout
    } finally {
      await StorageService.clearAll();
    }
  }

  Future<String> kycStatus() async {
    final res = await _client.get(ApiEndpoints.kycStatus);
    return res.data['kycStatus'] as String;
  }

  Future<void> uploadCni(String filePath) async {
    final formData = FormData.fromMap({
      'file': await MultipartFile.fromFile(filePath, filename: 'cni.jpg'),
    });
    await _client.postMultipart(ApiEndpoints.kycUploadCni, formData);
  }

  Future<void> uploadSelfie(String filePath) async {
    final formData = FormData.fromMap({
      'file': await MultipartFile.fromFile(filePath, filename: 'selfie.jpg'),
    });
    await _client.postMultipart(ApiEndpoints.kycUploadSelfie, formData);
  }

  Future<void> updateLocation(int userId, double lat, double lng) async {
    await _client.put(
      ApiEndpoints.updateLocation(userId),
      data: {'lat': lat, 'lng': lng},
    );
  }

  Future<void> setRole(int userId, String role) async {
    await _client.put(
      ApiEndpoints.setRole(userId),
      data: {'role': role},
    );
  }

  Future<void> requestResetPhoneLost({
    required String oldPhone,
    required String newPhone,
    required String name,
    required String role,
  }) async {
    await _client.post('/auth/reset-phone-request', data: {
      'old_phone': oldPhone,
      'new_phone': newPhone,
      'name': name,
      'role': role,
    });
  }

  Future<Map<String, dynamic>> confirmResetPhoneLost({
    required String oldPhone,
    required String newPhone,
    required String name,
    required String role,
    required String otp,
  }) async {
    final res = await _client.post('/auth/reset-phone-confirm', data: {
      'old_phone': oldPhone,
      'new_phone': newPhone,
      'name': name,
      'role': role,
      'otp': otp,
    });

    final token = res.data['token'] as String?;
    if (token != null) {
      await StorageService.saveToken(token);
    }

    return {
      'token': token,
      'user': res.data['user'],
    };
  }

  Future<Map<String, dynamic>> changePhoneConnected({
    required String newPhone,
    String? otp,
  }) async {
    final res = await _client.post('/auth/change-phone', data: {
      'new_phone': newPhone,
      if (otp != null) 'otp': otp,
    });

    return res.data as Map<String, dynamic>;
  }
}
