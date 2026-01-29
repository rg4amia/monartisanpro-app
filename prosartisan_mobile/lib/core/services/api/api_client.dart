import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import '../../constants/api_constants.dart';

/// API Client for making HTTP requests
class ApiClient {
  late final Dio _dio;
  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  static const String _tokenKey = 'auth_token';
  static const String _csrfTokenKey = 'csrf_token';

  ApiClient() {
    _dio = Dio(
      BaseOptions(
        baseUrl: ApiConstants.baseUrl,
        connectTimeout: ApiConstants.connectTimeout,
        receiveTimeout: ApiConstants.receiveTimeout,
        headers: {
          'Content-Type': ApiConstants.contentType,
          'Accept': ApiConstants.accept,
          'X-Requested-With': 'XMLHttpRequest', // Important for Laravel API
        },
      ),
    );

    // Add interceptor for authentication and CSRF
    _dio.interceptors.add(
      InterceptorsWrapper(
        onRequest: (options, handler) async {
          // Add auth token if available
          final token = await getToken();
          if (token != null) {
            options.headers[ApiConstants.authorization] = 'Bearer $token';
          }

          // For API routes, we don't need CSRF tokens
          // But if you need to call web routes, uncomment the following:
          /*
          final csrfToken = await getCsrfToken();
          if (csrfToken != null) {
            options.headers['X-CSRF-TOKEN'] = csrfToken;
          }
          */

          return handler.next(options);
        },
        onError: (error, handler) async {
          // Handle 401 Unauthorized
          if (error.response?.statusCode == 401) {
            await clearToken();
          }
          return handler.next(error);
        },
      ),
    );
  }

  /// Save authentication token
  Future<void> saveToken(String token) async {
    await _storage.write(key: _tokenKey, value: token);
  }

  /// Get authentication token
  Future<String?> getToken() async {
    return await _storage.read(key: _tokenKey);
  }

  /// Clear authentication token
  Future<void> clearToken() async {
    await _storage.delete(key: _tokenKey);
  }

  /// Save CSRF token (if needed for web routes)
  Future<void> saveCsrfToken(String token) async {
    await _storage.write(key: _csrfTokenKey, value: token);
  }

  /// Get CSRF token
  Future<String?> getCsrfToken() async {
    return await _storage.read(key: _csrfTokenKey);
  }

  /// Clear CSRF token
  Future<void> clearCsrfToken() async {
    await _storage.delete(key: _csrfTokenKey);
  }

  /// Check if user is authenticated
  Future<bool> isAuthenticated() async {
    final token = await getToken();
    return token != null && token.isNotEmpty;
  }

  /// GET request
  Future<Response> get(
    String path, {
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    return await _dio.get(
      path,
      queryParameters: queryParameters,
      options: options,
    );
  }

  /// POST request
  Future<Response> post(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    return await _dio.post(
      path,
      data: data,
      queryParameters: queryParameters,
      options: options,
    );
  }

  /// PUT request
  Future<Response> put(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    return await _dio.put(
      path,
      data: data,
      queryParameters: queryParameters,
      options: options,
    );
  }

  /// DELETE request
  Future<Response> delete(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    return await _dio.delete(
      path,
      data: data,
      queryParameters: queryParameters,
      options: options,
    );
  }

  /// Upload file with multipart/form-data
  Future<Response> uploadFile(String path, Map<String, dynamic> data) async {
    final formData = FormData.fromMap(data);
    return await _dio.post(
      path,
      data: formData,
      options: Options(contentType: 'multipart/form-data'),
    );
  }
}
