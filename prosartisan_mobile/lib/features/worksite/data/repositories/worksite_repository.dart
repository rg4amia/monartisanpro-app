import 'dart:io';
import 'package:dio/dio.dart';
import '../../../../core/services/api/api_client.dart';
import '../../../../core/constants/api_constants.dart';
import 'package:prosartisan_mobile/features/worksite/domain/models/chantier.dart';
import 'package:prosartisan_mobile/features/worksite/domain/models/jalon.dart';

/// Repository for worksite-related API operations
///
/// Handles communication with the backend API for chantiers and jalons
/// Requirements: 6.1, 6.2, 6.3
class WorksiteRepository {
  final ApiClient _apiClient;

  WorksiteRepository(this._apiClient);

  /// Get all chantiers for the current user
  Future<List<Chantier>> getChantiers({String? type}) async {
    try {
      final response = await _apiClient.get(
        ApiConstants.chantiers,
        queryParameters: type != null ? {'type': type} : null,
      );

      if (response.data == null) {
        throw Exception('Server returned empty response');
      }

      if (response.data is! Map<String, dynamic>) {
        throw Exception('Invalid response format from server');
      }

      final responseData = response.data as Map<String, dynamic>;
      final data = responseData.containsKey('data')
          ? responseData['data']
          : responseData;

      if (data is! List) {
        throw Exception('Invalid chantiers data format');
      }

      final List<dynamic> chantiersData = data;
      return chantiersData
          .map((json) => Chantier.fromJson(json as Map<String, dynamic>))
          .toList();
    } on DioException catch (e) {
      throw _handleError(e, 'Failed to load chantiers');
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('Failed to load chantiers: ${e.toString()}');
    }
  }

  /// Get chantier details by ID
  Future<Chantier> getChantier(String chantierId) async {
    try {
      final path = ApiConstants.chantierById.replaceAll('{id}', chantierId);
      final response = await _apiClient.get(path);

      if (response.data == null) {
        throw Exception('Server returned empty response');
      }

      if (response.data is! Map<String, dynamic>) {
        throw Exception('Invalid response format from server');
      }

      final responseData = response.data as Map<String, dynamic>;
      final data = responseData.containsKey('data')
          ? responseData['data'] as Map<String, dynamic>
          : responseData;

      return Chantier.fromJson(data);
    } on DioException catch (e) {
      throw _handleError(e, 'Failed to load chantier');
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('Failed to load chantier: ${e.toString()}');
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
      final requestData = {
        'mission_id': missionId,
        'client_id': clientId,
        'artisan_id': artisanId,
        if (milestones != null) 'milestones': milestones,
      };

      final response = await _apiClient.post(
        ApiConstants.chantiers,
        data: requestData,
      );

      if (response.data == null) {
        throw Exception('Server returned empty response');
      }

      if (response.data is! Map<String, dynamic>) {
        throw Exception('Invalid response format from server');
      }

      final responseData = response.data as Map<String, dynamic>;
      final data = responseData.containsKey('data')
          ? responseData['data'] as Map<String, dynamic>
          : responseData;

      return Chantier.fromJson(data);
    } on DioException catch (e) {
      throw _handleError(e, 'Failed to create chantier');
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('Failed to create chantier: ${e.toString()}');
    }
  }

  /// Get jalon details by ID
  Future<Jalon> getJalon(String jalonId) async {
    try {
      final path = ApiConstants.jalonById.replaceAll('{id}', jalonId);
      final response = await _apiClient.get(path);

      if (response.data == null) {
        throw Exception('Server returned empty response');
      }

      if (response.data is! Map<String, dynamic>) {
        throw Exception('Invalid response format from server');
      }

      final responseData = response.data as Map<String, dynamic>;
      final data = responseData.containsKey('data')
          ? responseData['data'] as Map<String, dynamic>
          : responseData;

      return Jalon.fromJson(data);
    } on DioException catch (e) {
      throw _handleError(e, 'Failed to load jalon');
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('Failed to load jalon: ${e.toString()}');
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
      final path = ApiConstants.jalonSubmitProof.replaceAll('{id}', jalonId);

      final uploadData = {
        'photo': await MultipartFile.fromFile(
          photo.path,
          filename: 'proof_${DateTime.now().millisecondsSinceEpoch}.jpg',
        ),
        'latitude': latitude,
        'longitude': longitude,
        if (accuracy != null) 'accuracy': accuracy,
        if (capturedAt != null) 'captured_at': capturedAt.toIso8601String(),
        if (exifData != null) 'exif_data': exifData,
      };

      final response = await _apiClient.uploadFile(path, uploadData);

      if (response.data == null) {
        throw Exception('Server returned empty response');
      }

      if (response.data is! Map<String, dynamic>) {
        throw Exception('Invalid response format from server');
      }

      final responseData = response.data as Map<String, dynamic>;
      final data = responseData.containsKey('data')
          ? responseData['data'] as Map<String, dynamic>
          : responseData;

      return Jalon.fromJson(data);
    } on DioException catch (e) {
      throw _handleError(e, 'Failed to submit proof');
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('Failed to submit proof: ${e.toString()}');
    }
  }

  /// Validate a jalon
  Future<Jalon> validateJalon(String jalonId, {String? comment}) async {
    try {
      final path = ApiConstants.jalonValidate.replaceAll('{id}', jalonId);
      final requestData = <String, dynamic>{};
      if (comment != null && comment.isNotEmpty) {
        requestData['comment'] = comment;
      }

      final response = await _apiClient.post(path, data: requestData);

      if (response.data == null) {
        throw Exception('Server returned empty response');
      }

      if (response.data is! Map<String, dynamic>) {
        throw Exception('Invalid response format from server');
      }

      final responseData = response.data as Map<String, dynamic>;
      final data = responseData.containsKey('data')
          ? responseData['data'] as Map<String, dynamic>
          : responseData;

      return Jalon.fromJson(data);
    } on DioException catch (e) {
      throw _handleError(e, 'Failed to validate jalon');
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('Failed to validate jalon: ${e.toString()}');
    }
  }

  /// Contest a jalon
  Future<Jalon> contestJalon(String jalonId, String reason) async {
    try {
      final path = ApiConstants.jalonContest.replaceAll('{id}', jalonId);
      final response = await _apiClient.post(
        path,
        data: {'reason': reason},
      );

      if (response.data == null) {
        throw Exception('Server returned empty response');
      }

      if (response.data is! Map<String, dynamic>) {
        throw Exception('Invalid response format from server');
      }

      final responseData = response.data as Map<String, dynamic>;
      final data = responseData.containsKey('data')
          ? responseData['data'] as Map<String, dynamic>
          : responseData;

      return Jalon.fromJson(data);
    } on DioException catch (e) {
      throw _handleError(e, 'Failed to contest jalon');
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('Failed to contest jalon: ${e.toString()}');
    }
  }

  /// Handle Dio errors
  Exception _handleError(DioException error, String operation) {
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
        return Exception('Unauthorized. Please log in again.');
      }

      if (statusCode == 403) {
        return Exception('Access denied. KYC verification may be required.');
      }

      if (statusCode == 404) {
        return Exception('Resource not found');
      }

      if (statusCode == 422) {
        // Validation error
        if (data is Map<String, dynamic>) {
          if (data.containsKey('message')) {
            return Exception(data['message']);
          }
          if (data.containsKey('errors')) {
            final errors = data['errors'] as Map<String, dynamic>;
            final firstError = errors.values.first;
            if (firstError is List && firstError.isNotEmpty) {
              return Exception(firstError.first.toString());
            }
          }
        }
        return Exception('Validation error');
      }

      if (statusCode == 500) {
        return Exception('Server error. Please try again later.');
      }

      if (data is Map<String, dynamic> && data.containsKey('message')) {
        return Exception(data['message']);
      }

      return Exception('$operation: Server error $statusCode');
    }

    return Exception('$operation: Unknown error occurred');
  }
}
