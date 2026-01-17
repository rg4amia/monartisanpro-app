import 'package:dio/dio.dart';
import 'api_client.dart';

/// Service wrapper for API operations
class ApiService {
  final ApiClient _apiClient;

  ApiService(this._apiClient);

  /// GET request
  Future<Response> get(
    String path, {
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    return await _apiClient.get(path, queryParameters: queryParameters, options: options);
  }

  /// POST request
  Future<Response> post(
    String path,
    dynamic data, {
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    return await _apiClient.post(path, data: data, queryParameters: queryParameters, options: options);
  }

  /// PUT request
  Future<Response> put(
    String path,
    dynamic data, {
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    return await _apiClient.put(path, data: data, queryParameters: queryParameters, options: options);
  }

  /// DELETE request
  Future<Response> delete(
    String path, {
    dynamic data,
    Map<String, dynamic>? queryParameters,
    Options? options,
  }) async {
    return await _apiClient.delete(path, data: data, queryParameters: queryParameters, options: options);
  }

  /// Upload file
  Future<Response> uploadFile(String path, Map<String, dynamic> data) async {
    return await _apiClient.uploadFile(path, data);
  }

  /// Check if authenticated
  Future<bool> isAuthenticated() async {
    return await _apiClient.isAuthenticated();
  }

  /// Save token
  Future<void> saveToken(S