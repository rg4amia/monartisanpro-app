import 'package:dio/dio.dart';
import 'package:frontend_flutter/core/utils/json_readers.dart';

import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/services/session_end.dart';
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

    final token = readString(readMap(res.data)?['token']);
    if (token != null) {
      await StorageService.saveToken(token);
    }

    return {
      'token': token,
      'user': UserModel.fromJson(
        requireMap(readMap(res.data)?['user']),
      ),
    };
  }

  Future<UserModel> me() async {
    final res = await _client.get(ApiEndpoints.me);
    final body = readMap(res.data);

    return UserModel.fromJson(requireMap(body?['data'] ?? body?['user']));
  }

  Future<void> logout() async {
    try {
      await _client.post(ApiEndpoints.logout);
    } on DioException {
      // ignore errors on logout
    } finally {
      // Ne jamais exposer les données d'un compte au compte suivant sur le
      // même appareil, ni lui laisser ses notifications.
      await SessionEnd.wipeLocalData();
    }
  }

  Future<Map<String, dynamic>> acceptCgu() async {
    final res = await _client.post('/auth/accept-cgu');
    return requireMap(res.data);
  }

  Future<String> kycStatus() async {
    final res = await _client.get(ApiEndpoints.kycStatus);
    final status = readString(_kycStatusData(res.data)?['kyc_status']);
    if (status == null) {
      throw const FormatException('Statut KYC absent de la réponse.');
    }
    return status;
  }

  /// Motif saisi par l'administrateur quand le dossier est rejeté, sinon null.
  Future<String?> kycRejectionReason() async {
    final res = await _client.get(ApiEndpoints.kycStatus);
    final reason = readString(_kycStatusData(res.data)?['rejection_reason']);
    return reason == null || reason.trim().isEmpty ? null : reason.trim();
  }

  Map<String, dynamic>? _kycStatusData(dynamic body) =>
      readMap(readMap(body)?['data']);

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

    return requireMap(res.data);
  }
}
