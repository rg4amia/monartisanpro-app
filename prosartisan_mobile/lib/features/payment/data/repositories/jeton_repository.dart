import 'package:dio/dio.dart';
import '../../../../core/services/api/api_client.dart';
import '../../../../core/constants/api_constants.dart';
import '../../domain/entities/jeton.dart';
import '../../domain/entities/jeton_validation_result.dart';

/// Repository interface for jeton operations
///
/// Requirements: 5.1, 5.3
abstract class JetonRepository {
  /// Get jeton by ID
  Future<Jeton?> getJetonById(String jetonId);

  /// Get jeton by code
  Future<Jeton?> getJetonByCode(String code);

  /// Generate new jeton for sequestre
  Future<Jeton?> generateJeton({required String sequestreId});

  /// Validate jeton with GPS verification
  Future<JetonValidationResult> validateJeton({
    required String jetonCode,
    required int amountCentimes,
    required double artisanLatitude,
    required double artisanLongitude,
    required double supplierLatitude,
    required double supplierLongitude,
  });
}

/// Implementation of JetonRepository
class JetonRepositoryImpl implements JetonRepository {
  final ApiClient _apiClient;

  JetonRepositoryImpl(this._apiClient);

  @override
  Future<Jeton?> getJetonById(String jetonId) async {
    try {
      final path = ApiConstants.jetonById.replaceAll('{id}', jetonId);
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

      return Jeton.fromJson(data);
    } on DioException catch (e) {
      if (e.response?.statusCode == 404) {
        return null;
      }
      throw _handleError(e);
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('Failed to get jeton: ${e.toString()}');
    }
  }

  @override
  Future<Jeton?> getJetonByCode(String code) async {
    try {
      final path = ApiConstants.jetonByCode.replaceAll('{code}', code);
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

      return Jeton.fromJson(data);
    } on DioException catch (e) {
      if (e.response?.statusCode == 404) {
        return null;
      }
      throw _handleError(e);
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('Failed to get jeton by code: ${e.toString()}');
    }
  }

  @override
  Future<Jeton?> generateJeton({required String sequestreId}) async {
    try {
      final response = await _apiClient.post(
        ApiConstants.jetonGenerate,
        data: {
          'sequestre_id': sequestreId,
        },
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

      return Jeton.fromJson(data);
    } on DioException catch (e) {
      throw _handleError(e);
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('Failed to generate jeton: ${e.toString()}');
    }
  }

  @override
  Future<JetonValidationResult> validateJeton({
    required String jetonCode,
    required int amountCentimes,
    required double artisanLatitude,
    required double artisanLongitude,
    required double supplierLatitude,
    required double supplierLongitude,
  }) async {
    try {
      final response = await _apiClient.post(
        ApiConstants.jetonValidate,
        data: {
          'jeton_code': jetonCode,
          'amount_centimes': amountCentimes,
          'artisan_latitude': artisanLatitude,
          'artisan_longitude': artisanLongitude,
          'supplier_latitude': supplierLatitude,
          'supplier_longitude': supplierLongitude,
        },
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

      // Extract validation result data
      if (!data.containsKey('validation_id')) {
        throw Exception('Invalid response: missing validation_id');
      }

      return JetonValidationResult.success(
        validationId: data['validation_id'] as String,
        amountUsed: data['amount_used'] as int? ?? amountCentimes,
        remainingAmount: data['remaining_amount'] as int? ?? 0,
        validatedAt: data['validated_at'] as String? ?? DateTime.now().toIso8601String(),
      );
    } on DioException catch (e) {
      final errorMessage = _handleErrorString(e);
      return JetonValidationResult.error(errorMessage);
    } catch (e) {
      return JetonValidationResult.error('Validation failed: ${e.toString()}');
    }
  }

  /// Handle Dio errors
  Exception _handleError(DioException error) {
    return Exception(_handleErrorString(error));
  }

  /// Handle Dio errors and return error message string
  String _handleErrorString(DioException error) {
    if (error.type == DioExceptionType.connectionTimeout ||
        error.type == DioExceptionType.receiveTimeout) {
      return 'Connection timeout. Please check your internet connection.';
    }

    if (error.type == DioExceptionType.connectionError) {
      return 'Network error. Please check your internet connection.';
    }

    if (error.response != null) {
      final statusCode = error.response!.statusCode;
      final data = error.response!.data;

      if (statusCode == 400) {
        if (data is Map<String, dynamic> && data.containsKey('message')) {
          return data['message'];
        }
        return 'Invalid jeton operation';
      }

      if (statusCode == 401) {
        return 'Unauthorized. Please log in again.';
      }

      if (statusCode == 403) {
        return 'Access denied. KYC verification required or insufficient permissions.';
      }

      if (statusCode == 404) {
        return 'Jeton not found';
      }

      if (statusCode == 410) {
        return 'Jeton has expired';
      }

      if (statusCode == 422) {
        // Validation error
        if (data is Map<String, dynamic>) {
          if (data.containsKey('message')) {
            return data['message'];
          }
          if (data.containsKey('errors')) {
            final errors = data['errors'] as Map<String, dynamic>;
            final firstError = errors.values.first;
            if (firstError is List && firstError.isNotEmpty) {
              return firstError.first.toString();
            }
          }
        }
        return 'Validation error';
      }

      if (statusCode == 500) {
        return 'Server error. Please try again later.';
      }

      if (data is Map<String, dynamic> && data.containsKey('message')) {
        return data['message'];
      }

      return 'Server error: $statusCode';
    }

    return 'Unknown error occurred. Please try again.';
  }
}