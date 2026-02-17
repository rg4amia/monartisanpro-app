import 'package:dio/dio.dart';
import '../../shared/models/auth_response.dart';
import '../../shared/models/escrow_model.dart';
import 'dio_client.dart';

class TokenService {
  final Dio _dio = DioClient().dio;

  /// Validate token code and get details
  Future<ApiResponse<MaterialToken>> validateToken(String tokenCode) async {
    try {
      final response = await _dio.post('/tokens/validate', data: {
        'token_code': tokenCode,
      });

      return ApiResponse<MaterialToken>.fromJson(
        response.data,
        (json) => MaterialToken.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<MaterialToken>.fromJson(
          e.response!.data,
          (json) => MaterialToken.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }

  /// Redeem token with GPS validation
  Future<ApiResponse<TokenRedemption>> redeemToken(
    RedeemTokenRequest request,
  ) async {
    try {
      final response = await _dio.post('/tokens/redeem', data: request.toJson());

      return ApiResponse<TokenRedemption>.fromJson(
        response.data,
        (json) => TokenRedemption.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<TokenRedemption>.fromJson(
          e.response!.data,
          (json) => TokenRedemption.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }

  /// Get redemption history for a token
  Future<ApiResponse<List<TokenRedemption>>> getRedemptionHistory(
    int tokenId,
  ) async {
    try {
      final response = await _dio.get('/tokens/$tokenId/redemptions');

      return ApiResponse<List<TokenRedemption>>.fromJson(
        response.data,
        (json) => (json as List)
            .map((item) => TokenRedemption.fromJson(item as Map<String, dynamic>))
            .toList(),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<List<TokenRedemption>>.fromJson(
          e.response!.data,
          (json) => [],
        );
      }
      rethrow;
    }
  }

  /// Upload receipt photo with geolocation
  Future<ApiResponse<String>> uploadReceipt({
    required String filePath,
    required double latitude,
    required double longitude,
  }) async {
    try {
      final formData = FormData.fromMap({
        'receipt': await MultipartFile.fromFile(filePath),
        'latitude': latitude,
        'longitude': longitude,
      });

      final response = await _dio.post('/tokens/upload-receipt', data: formData);

      return ApiResponse<String>.fromJson(
        response.data,
        (json) => (json as Map<String, dynamic>)['file_path'] as String,
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<String>.fromJson(
          e.response!.data,
          (json) => '',
        );
      }
      rethrow;
    }
  }

  /// Check GPS proximity validation
  Future<ApiResponse<GpsValidationResult>> checkGpsProximity({
    required double vendorLatitude,
    required double vendorLongitude,
    required double artisanLatitude,
    required double artisanLongitude,
  }) async {
    try {
      final response = await _dio.post('/tokens/check-proximity', data: {
        'vendor_latitude': vendorLatitude,
        'vendor_longitude': vendorLongitude,
        'artisan_latitude': artisanLatitude,
        'artisan_longitude': artisanLongitude,
      });

      return ApiResponse<GpsValidationResult>.fromJson(
        response.data,
        (json) => GpsValidationResult.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<GpsValidationResult>.fromJson(
          e.response!.data,
          (json) => GpsValidationResult.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }
}

class GpsValidationResult {
  final bool isValid;
  final double distanceMeters;
  final double maxDistanceMeters;
  final String message;

  GpsValidationResult({
    required this.isValid,
    required this.distanceMeters,
    required this.maxDistanceMeters,
    required this.message,
  });

  factory GpsValidationResult.fromJson(Map<String, dynamic> json) {
    return GpsValidationResult(
      isValid: json['is_valid'] as bool,
      distanceMeters: (json['distance_meters'] as num).toDouble(),
      maxDistanceMeters: (json['max_distance_meters'] as num).toDouble(),
      message: json['message'] as String,
    );
  }

  String get distanceText {
    if (distanceMeters < 1000) {
      return '${distanceMeters.round()}m';
    }
    return '${(distanceMeters / 1000).toStringAsFixed(1)}km';
  }
}
