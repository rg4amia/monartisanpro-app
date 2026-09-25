import 'package:dio/dio.dart';
import 'package:frontend_flutter/core/utils/json_readers.dart';

import '../../core/cache/cache_store.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/storage/storage_service.dart';
import '../models/user_model.dart';

class AuthRepository {
  final ApiClient _client = ApiClient();

  Future<Map<String, dynamic>?> getSecurityChallenge([String action = 'send_otp']) async {
    try {
      final res = await _client.get(
        ApiEndpoints.securityChallenge,
        params: {'action': action},
      );
      final body = readMap(res.data);
      if (body != null && body['success'] == true) {
        return readMap(body['data']) ?? readMap(body['challenge']);
      }
    } catch (_) {
      // Repli gracieux si indisponible
    }
    return null;
  }

  Future<void> sendOtp(
    String phone, {
    String? role,
    String? botToken,
    String? botAnswer,
    String? botTrap,
  }) async {
    final data = <String, dynamic>{
      'phone': phone,
      'bot_trap': botTrap ?? '',
    };
    if (role != null) data['role'] = role;
    if (botToken != null) data['bot_token'] = botToken;
    if (botAnswer != null) data['bot_answer'] = botAnswer;

    await _client.post(ApiEndpoints.sendOtp, data: data);
  }

  Future<Map<String, dynamic>> verifyOtp(String phone, String otp) async {
    final res = await _client.post(
      ApiEndpoints.verifyOtp,
      data: {
        'phone': phone,
        'otp': otp,
        'device_fingerprint': StorageService.getDeviceFingerprint(),
      },
    );

    final body = readMap(res.data) ?? const <String, dynamic>{};
    final hasCompletedProfile =
        readBool(body['has_completed_profile']) ?? false;
    final token = readString(body['token']);

    // Si le profil est complet, on sauvegarde le token
    if (hasCompletedProfile && token != null) {
      await StorageService.saveToken(token);
    }

    return {
      'has_completed_profile': hasCompletedProfile,
      'token': token,
      'user': body['user'],
      'phone': readString(body['phone']),
    };
  }

  Future<Map<String, dynamic>> register({
    required String phone,
    required String role,
    required String name,
    required bool cguAccepted,
  }) async {
    final res = await _client.post(
      ApiEndpoints.register,
      data: {
        'phone': phone,
        'role': role,
        'name': name,
        'cgu_accepted': cguAccepted,
      },
    );

    final token = (res.data as Map<String, dynamic>)['token'] as String?;
    if (token != null) {
      await StorageService.saveToken(token);
    }

    return {
      'token': token,
      'user': UserModel.fromJson(
        (res.data as Map<String, dynamic>)['user'] as Map<String, dynamic>,
      ),
    };
  }

  Future<UserModel> me() async {
    final res = await _client.get(ApiEndpoints.me);
    final payload = ((res.data as Map<String, dynamic>)['data'] ??
        (res.data as Map<String, dynamic>)['user']) as Map<String, dynamic>;
    return UserModel.fromJson(payload);
  }

  Future<void> logout() async {
    try {
      await _client.post(ApiEndpoints.logout);
    } on DioException {
      // ignore errors on logout
    } finally {
      await StorageService.clearAll();
      // Purge des caches locaux : ne jamais exposer les données d'un compte
      // au compte suivant sur le même appareil.
      await CacheStore.wipeAll();
    }
  }

  Future<Map<String, dynamic>> acceptCgu() async {
    final res = await _client.post('/auth/accept-cgu');
    return res.data as Map<String, dynamic>;
  }

  Future<String> kycStatus() async {
    final res = await _client.get(ApiEndpoints.kycStatus);
    final status = readString(readMap(res.data)?['kycStatus']);
    if (status == null) {
      throw const FormatException('Statut KYC absent de la réponse.');
    }
    return status;
  }

  /// Téléverse la pièce d'identité. Renvoie le statut KYC du compte après
  /// l'analyse IA (`actif` si le dossier a été validé automatiquement), ou
  /// null si la réponse ne le précise pas.
  Future<String?> uploadCni(String filePath) async {
    final formData = FormData.fromMap({
      'file': await MultipartFile.fromFile(filePath, filename: 'cni.jpg'),
    });
    final res = await _client.postMultipart(ApiEndpoints.kycUploadCni, formData);
    return _kycStatusFromUpload(res.data);
  }

  /// Téléverse le selfie ; même contrat de retour que [uploadCni].
  Future<String?> uploadSelfie(String filePath) async {
    final formData = FormData.fromMap({
      'file': await MultipartFile.fromFile(filePath, filename: 'selfie.jpg'),
    });
    final res = await _client.postMultipart(ApiEndpoints.kycUploadSelfie, formData);
    return _kycStatusFromUpload(res.data);
  }

  String? _kycStatusFromUpload(dynamic body) =>
      readString(readMap(readMap(body)?['data'])?['kyc_status']);

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
    await _client.post(
      '/auth/reset-phone-request',
      data: {
        'old_phone': oldPhone,
        'new_phone': newPhone,
        'name': name,
        'role': role,
      },
    );
  }

  Future<Map<String, dynamic>> confirmResetPhoneLost({
    required String oldPhone,
    required String newPhone,
    required String name,
    required String role,
    required String otp,
  }) async {
    final res = await _client.post(
      '/auth/reset-phone-confirm',
      data: {
        'old_phone': oldPhone,
        'new_phone': newPhone,
        'name': name,
        'role': role,
        'otp': otp,
      },
    );

    final token = (res.data as Map<String, dynamic>)['token'] as String?;
    if (token != null) {
      await StorageService.saveToken(token);
    }

    return {
      'token': token,
      'user': (res.data as Map<String, dynamic>)['user'],
    };
  }

  Future<Map<String, dynamic>> changePhoneConnected({
    required String newPhone,
    String? otp,
  }) async {
    final res = await _client.post(
      '/auth/change-phone',
      data: {
        'new_phone': newPhone,
        if (otp != null) 'otp': otp,
      },
    );

    return res.data as Map<String, dynamic>;
  }
}
