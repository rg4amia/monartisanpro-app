import 'dart:async';

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:get/get.dart' hide Response, FormData, MultipartFile;
import 'package:pretty_dio_logger/pretty_dio_logger.dart';
import '../../app/routes/app_routes.dart';
import '../storage/storage_service.dart';
import '../utils/error_handler.dart';
import 'api_endpoints.dart';

class ApiClient {
  static ApiClient? _instance;
  late final Dio _dio;

  ApiClient._() {
    _dio = Dio(
      BaseOptions(
        baseUrl: ApiEndpoints.baseUrl,
        connectTimeout: const Duration(seconds: 15),
        receiveTimeout: const Duration(seconds: 15),
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      ),
    );

    _dio.interceptors.addAll([
      _AuthInterceptor(),
      _ErrorLoggerInterceptor(),
    ]);

    if (!kReleaseMode) {
      _dio.interceptors.add(
        PrettyDioLogger(
          requestHeader: true,
          requestBody: true,
          responseBody: true,
          responseHeader: false,
          error: true,
          compact: true,
        ),
      );
    }
  }

  factory ApiClient() => _instance ??= ApiClient._();

  Dio get dio => _dio;

  Future<Response> get(String path, {Map<String, dynamic>? params}) =>
      _dio.get(path, queryParameters: params);

  Future<Response> post(String path, {dynamic data}) =>
      _dio.post(path, data: data);

  Future<Response> put(String path, {dynamic data}) =>
      _dio.put(path, data: data);

  Future<Response> delete(String path) => _dio.delete(path);

  Future<Response> postMultipart(String path, FormData formData) =>
      _dio.post(path, data: formData);
}

class _AuthInterceptor extends Interceptor {
  @override
  Future<void> onRequest(
      RequestOptions options, RequestInterceptorHandler handler) async {
    // Use StorageService instead of direct FlutterSecureStorage
    final token = await StorageService.getToken();
    if (token != null) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    handler.next(options);
  }

  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    if (err.response?.statusCode == 401) {
      StorageService.clearToken();

      // Redirect to login if not already there
      if (Get.currentRoute != Routes.login) {
        Get.offAllNamed(Routes.login);
      }
    }
    handler.next(err);
  }
}

/// Interceptor pour logger automatiquement les erreurs HTTP vers Telegram
class _ErrorLoggerInterceptor extends Interceptor {
  @override
  void onError(DioException err, ErrorInterceptorHandler handler) {
    // Log automatiquement vers Telegram (sans bloquer la requête).
    unawaited(
      ErrorHandler.handleDioError(
        err,
        context: '${err.requestOptions.method} ${err.requestOptions.path}',
        showSnackbar: false, // Le controller gérera l'affichage
      ),
    );
    
    handler.next(err);
  }
}
