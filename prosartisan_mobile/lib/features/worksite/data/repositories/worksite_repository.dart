import 'dart:io';
import 'package:dio/dio.dart';
import 'package:prosartisan_mobile/features/worksite/domain/models/chantier.dart';
import 'package:prosartisan_mobile/features/worksite/domain/models/jalon.dart';

/// Repository for worksite-related API operations
///
/// Handles communication with the backend API for chantiers and jalons
/// Requirements: 6.1, 6.2, 6.3
class WorksiteRepository {
  final Dio _dio;

  WorksiteRepository(this._dio);

  /// Get all chantiers for the current user
  Future<List<Chantier>> getChantiers({String? type}) async {
    try {
      final response = await _dio.get(
        '/chantiers',
        queryParameters: type != null ? {'type': type} : null,
      );

      if (response.statusCode == 200) {
        final List<dynamic> data = response.data['data'] as List<dynamic>;
        return data
            .map((json) => Chantier.fromJson(json as Map<String, dynamic>))
            .toList();
      } else {
        throw Exception('Failed to load chantiers: ${response.statusCode}');
      }
    } on DioException catch (e) {
      throw Exception('Network error loading chantiers: ${e.message}');
    }
  }

  /// Get chantier details by ID
  Future<Chantier> getChantier(String chantierId) async {
    try {
      final response = await _dio.get('/chantiers/$chantierId');

      if (response.statusCode == 200) {
        return Chantier.fromJson(response.data['data'] as Map<String, dynamic>);
      } else {
        throw Exception('Failed to load chantier: ${response.statusCode}');
      }
    } on DioException catch (e) {
      throw Exception('Network error loading chantier: ${e.message}');
    }
  }

  /// Create a new chantier
  Future<Chantier> createChantier({
    required String missionId,
    required String clientId,
    required String artisanId,
    List<Map<String, dynamic>>? milestones,
  }) async {
    try {
      final data = {
        'mission_id': missionId,
        'client_id': clientId,
        'artisan_id': artisanId,
        if (milestones != null) 'milestones': milestones,
      };

      final response = await _dio.post('/chantiers', data: data);

      if (response.statusCode == 201) {
        return Chantier.fromJson(response.data['data'] as Map<String, dynamic>);
      } else {
        throw Exception('Failed to create chantier: ${response.statusCode}');
      }
    } on DioException catch (e) {
      throw Exception('Network error creating chantier: ${e.message}');
    }
  }

  /// Get jalon details by ID
  Future<Jalon> getJalon(String jalonId) async {
    try {
      final response = await _dio.get('/jalons/$jalonId');

      if (response.statusCode == 200) {
        return Jalon.fromJson(response.data['data'] as Map<String, dynamic>);
      } else {
        throw Exception('Failed to load jalon: ${response.statusCode}');
      }
    } on DioException catch (e) {
      throw Exception('Network error loading jalon: ${e.message}');
    }
  }

  /// Submit proof for a jalon
  Future<Jalon> submitProof({
    required String jalonId,
    required File photo,
    required double latitude,
    required double longitude,
    double? accuracy,
    DateTime? capturedAt,
    Map<String, dynamic>? exifData,
  }) async {
    try {
      final formData = FormData.fromMap({
        'photo': await MultipartFile.fromFile(
          photo.path,
          filename: 'proof_${DateTime.now().millisecondsSinceEpoch}.jpg',
        ),
        'latitude': latitude,
        'longitude': longitude,
        if (accuracy != null) 'accuracy': accuracy,
        if (capturedAt != null) 'captured_at': capturedAt.toIso8601String(),
        if (exifData != null) 'exif_data': exifData,
      });

      final response = await _dio.post(
        '/jalons/$jalonId/submit-proof',
        data: formData,
      );

      if (response.statusCode == 200) {
        return Jalon.fromJson(response.data['data'] as Map<String, dynamic>);
      } else {
        throw Exception('Failed to submit proof: ${response.statusCode}');
      }
    } on DioException catch (e) {
      throw Exception('Network error submitting proof: ${e.message}');
    }
  }

  /// Validate a jalon
  Future<Jalon> validateJalon(String jalonId, {String? comment}) async {
    try {
      final data = <String, dynamic>{};
      if (comment != null && comment.isNotEmpty) {
        data['comment'] = comment;
      }

      final response = await _dio.post('/jalons/$jalonId/validate', data: data);

      if (response.statusCode == 200) {
        return Jalon.fromJson(response.data['data'] as Map<String, dynamic>);
      } else {
        throw Exception('Failed to validate jalon: ${response.statusCode}');
      }
    } on DioException catch (e) {
      throw Exception('Network error validating jalon: ${e.message}');
    }
  }

  /// Contest a jalon
  Future<Jalon> contestJalon(String jalonId, String reason) async {
    try {
      final response = await _dio.post(
        '/jalons/$jalonId/contest',
        data: {'reason': reason},
      );

      if (response.statusCode == 200) {
        return Jalon.fromJson(response.data['data'] as Map<String, dynamic>);
      } else {
        throw Exception('Failed to contest jalon: ${response.statusCode}');
      }
    } on DioException catch (e) {
      throw Exception('Network error contesting jalon: ${e.message}');
    }
  }
}
