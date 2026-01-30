import 'package:dio/dio.dart';
import '../../../../core/services/api/api_client.dart';
import '../../../../core/constants/api_constants.dart';

/// Remote data source for GPS validation operations
///
/// Requirements: 6.3, 5.3
class GPSRemoteDataSource {
  final ApiClient _apiClient;

  GPSRemoteDataSource(this._apiClient);

  /// Validate proximity between two GPS coordinates
  ///
  /// Requirement 6.3: GPS proof of work submission
  Future<Map<String, dynamic>> validateProximity({
    required double latitude1,
    required double longitude1,
    required double latitude2,
    required double longitude2,
    required double maxDistanceMeters,
  }) async {
    try {
      final response = await _apiClient.post(
        ApiConstants.gpsValidateProximity,
        data: {
          'latitude_1': latitude1,
          'longitude_1': longitude1,
          'latitude_2': latitude2,
          'longitude_2': longitude2,
          'max_distance_meters': maxDistanceMeters,
        },
      );

      // Check if response data is null
      if (response.data == null) {
        throw Exception('Server returned empty response');
      }

      // Check if response data is a Map
      if (response.data is! Map<String, dynamic>) {
        throw Exception('Invalid response format from server');
      }

      final responseData = response.data as Map<String, dynamic>;

      // Extract the 'data' field if it exists (Laravel API format)
      final data = responseData.containsKey('data')
          ? responseData['data'] as Map<String, dynamic>
          : responseData;

      return data;
    } on DioException catch (e) {
      throw _handleError(e);
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('GPS proximity validation failed: ${e.toString()}');
    }
  }

  /// Generate OTP for GPS verification
  ///
  /// Requirement 5.3: Generate GPS-based OTP for material token validation
  Future<Map<String, dynamic>> generateOtp({
    required double latitude,
    required double longitude,
    required String purpose,
  }) async {
    try {
      final response = await _apiClient.post(
        ApiConstants.gpsGenerateOtp,
        data: {
          'latitude': latitude,
          'longitude': longitude,
          'purpose': purpose,
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

      return data;
    } on DioException catch (e) {
      throw _handleError(e);
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('OTP generation failed: ${e.toString()}');
    }
  }

  /// Verify OTP for GPS validation
  ///
  /// Requirement 5.3: Verify GPS-based OTP
  Future<Map<String, dynamic>> verifyOtp({
    required String otpCode,
    required double latitude,
    required double longitude,
  }) async {
    try {
      final response = await _apiClient.post(
        ApiConstants.gpsVerifyOtp,
        data: {
          'otp_code': otpCode,
          'latitude': latitude,
          'longitude': longitude,
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

      return data;
    } on DioException catch (e) {
      throw _handleError(e);
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('OTP verification failed: ${e.toString()}');
    }
  }

  /// Calculate distance between two GPS coordinates
  ///
  /// Returns distance in meters
  Future<double> calculateDistance({
    required double latitude1,
    required double longitude1,
    required double latitude2,
    required double longitude2,
  }) async {
    try {
      final response = await _apiClient.post(
        ApiConstants.gpsCalculateDistance,
        data: {
          'latitude_1': latitude1,
          'longitude_1': longitude1,
          'latitude_2': latitude2,
          'longitude_2': longitude2,
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

      // Extract distance from response
      if (!data.containsKey('distance_meters')) {
        throw Exception('Invalid response: missing distance_meters');
      }

      final distanceValue = data['distance_meters'];
      if (distanceValue is int) {
        return distanceValue.toDouble();
      } else if (distanceValue is double) {
        return distanceValue;
      } else {
        throw Exception('Invalid distance format: $distanceValue');
      }
    } on DioException catch (e) {
      throw _handleError(e);
    } catch (e) {
      if (e is Exception) rethrow;
      throw Exception('Distance calculation failed: ${e.toString()}');
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

      if (statusCode == 400) {
        // Bad request - validation error or invalid GPS data
        if (data is Map<String, dynamic> && data.containsKey('message')) {
          return Exception(data['message']);
        }
        return Exception('Invalid GPS data');
      }

      if (statusCode == 403) {
        return Exception('GPS validation failed: Location too far');
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

      return Exception('Server error: $statusCode');
    }

    return Exception('Unknown error occurred. Please try again.');
  }
}
