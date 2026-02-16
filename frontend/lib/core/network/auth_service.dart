import 'package:dio/dio.dart';
import '../../shared/models/auth_response.dart';
import '../../shared/models/user_model.dart';
import 'dio_client.dart';

class AuthService {
  final Dio _dio = DioClient().dio;

  /// Register a new user
  Future<AuthResponse> register({
    required String name,
    required String email,
    required String phone,
    required String password,
    required String passwordConfirmation,
    required String role,
    int? tradeId,
    int? experienceYears,
    String? bio,
  }) async {
    try {
      final response = await _dio.post(
        '/auth/register',
        data: {
          'name': name,
          'email': email,
          'phone': phone,
          'password': password,
          'password_confirmation': passwordConfirmation,
          'role': role,
          if (tradeId != null) 'trade_id': tradeId,
          if (experienceYears != null) 'experience_years': experienceYears,
          if (bio != null) 'bio': bio,
        },
      );

      return AuthResponse.fromJson(response.data);
    } on DioException catch (e) {
      if (e.response != null) {
        return AuthResponse.fromJson(e.response!.data);
      }
      rethrow;
    }
  }

  /// Login user
  Future<AuthResponse> login({
    required String email,
    required String password,
  }) async {
    try {
      final response = await _dio.post(
        '/auth/login',
        data: {
          'email': email,
          'password': password,
        },
      );

      return AuthResponse.fromJson(response.data);
    } on DioException catch (e) {
      if (e.response != null) {
        return AuthResponse.fromJson(e.response!.data);
      }
      rethrow;
    }
  }

  /// Logout user
  Future<ApiResponse<void>> logout() async {
    try {
      final response = await _dio.post('/auth/logout');
      return ApiResponse<void>.fromJson(response.data, (_) => null);
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<void>.fromJson(e.response!.data, (_) => null);
      }
      rethrow;
    }
  }

  /// Get current user
  Future<ApiResponse<User>> getCurrentUser() async {
    try {
      final response = await _dio.get('/auth/me');
      return ApiResponse<User>.fromJson(
        response.data,
        (json) => User.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<User>.fromJson(
          e.response!.data,
          (json) => User.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }

  /// Send OTP to phone
  Future<ApiResponse<void>> sendOtp({required String phone}) async {
    try {
      final response = await _dio.post(
        '/auth/send-otp',
        data: {'phone': phone},
      );
      return ApiResponse<void>.fromJson(response.data, (_) => null);
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<void>.fromJson(e.response!.data, (_) => null);
      }
      rethrow;
    }
  }

  /// Verify OTP
  Future<ApiResponse<void>> verifyOtp({
    required String phone,
    required String otp,
  }) async {
    try {
      final response = await _dio.post(
        '/auth/verify-otp',
        data: {
          'phone': phone,
          'otp': otp,
        },
      );
      return ApiResponse<void>.fromJson(response.data, (_) => null);
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<void>.fromJson(e.response!.data, (_) => null);
      }
      rethrow;
    }
  }

  /// Update profile
  Future<ApiResponse<User>> updateProfile({
    String? name,
    String? phone,
    String? avatarPath,
    double? latitude,
    double? longitude,
    String? zoneName,
    String? bio,
    int? experienceYears,
    bool? available,
  }) async {
    try {
      final formData = FormData();

      if (name != null) formData.fields.add(MapEntry('name', name));
      if (phone != null) formData.fields.add(MapEntry('phone', phone));
      if (latitude != null) {
        formData.fields.add(MapEntry('latitude', latitude.toString()));
      }
      if (longitude != null) {
        formData.fields.add(MapEntry('longitude', longitude.toString()));
      }
      if (zoneName != null) formData.fields.add(MapEntry('zone_name', zoneName));
      if (bio != null) formData.fields.add(MapEntry('bio', bio));
      if (experienceYears != null) {
        formData.fields.add(MapEntry('experience_years', experienceYears.toString()));
      }
      if (available != null) {
        formData.fields.add(MapEntry('available', available.toString()));
      }
      if (avatarPath != null) {
        formData.files.add(MapEntry(
          'avatar',
          await MultipartFile.fromFile(avatarPath),
        ));
      }

      final response = await _dio.put('/auth/profile', data: formData);
      return ApiResponse<User>.fromJson(
        response.data,
        (json) => User.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<User>.fromJson(
          e.response!.data,
          (json) => User.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }
}
