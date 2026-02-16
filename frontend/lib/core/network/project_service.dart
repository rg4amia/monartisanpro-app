import 'package:dio/dio.dart';
import '../../shared/models/auth_response.dart';
import '../../shared/models/project_model.dart';
import '../../shared/models/escrow_model.dart';
import 'dio_client.dart';

class ProjectService {
  final Dio _dio = DioClient().dio;

  /// Create a new project
  Future<ApiResponse<Project>> createProject(CreateProjectRequest request) async {
    try {
      final response = await _dio.post('/projects', data: request.toJson());
      return ApiResponse<Project>.fromJson(
        response.data,
        (json) => Project.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<Project>.fromJson(
          e.response!.data,
          (json) => Project.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }

  /// Get user's projects (client or artisan)
  Future<ApiResponse<List<Project>>> getMyProjects({String? status}) async {
    try {
      final response = await _dio.get(
        '/projects/my',
        queryParameters: status != null ? {'status': status} : null,
      );

      return ApiResponse<List<Project>>.fromJson(
        response.data,
        (json) => (json as List)
            .map((project) => Project.fromJson(project as Map<String, dynamic>))
            .toList(),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<List<Project>>.fromJson(
          e.response!.data,
          (json) => [],
        );
      }
      rethrow;
    }
  }

  /// Get project details
  Future<ApiResponse<Project>> getProject(int projectId) async {
    try {
      final response = await _dio.get('/projects/$projectId');
      return ApiResponse<Project>.fromJson(
        response.data,
        (json) => Project.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<Project>.fromJson(
          e.response!.data,
          (json) => Project.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }

  /// Update project
  Future<ApiResponse<Project>> updateProject(
    int projectId,
    Map<String, dynamic> data,
  ) async {
    try {
      final response = await _dio.put('/projects/$projectId', data: data);
      return ApiResponse<Project>.fromJson(
        response.data,
        (json) => Project.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<Project>.fromJson(
          e.response!.data,
          (json) => Project.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }

  /// Cancel project
  Future<ApiResponse<void>> cancelProject(int projectId) async {
    try {
      final response = await _dio.delete('/projects/$projectId');
      return ApiResponse<void>.fromJson(response.data, (_) {});
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<void>.fromJson(e.response!.data, (_) {});
      }
      rethrow;
    }
  }

  /// Create a quote for a project
  Future<ApiResponse<Quote>> createQuote(CreateQuoteRequest request) async {
    try {
      final response = await _dio.post('/quotes', data: request.toJson());
      return ApiResponse<Quote>.fromJson(
        response.data,
        (json) => Quote.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<Quote>.fromJson(
          e.response!.data,
          (json) => Quote.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }

  /// Get quote details
  Future<ApiResponse<Quote>> getQuote(int quoteId) async {
    try {
      final response = await _dio.get('/quotes/$quoteId');
      return ApiResponse<Quote>.fromJson(
        response.data,
        (json) => Quote.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<Quote>.fromJson(
          e.response!.data,
          (json) => Quote.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }

  /// Accept a quote
  Future<ApiResponse<Quote>> acceptQuote(int quoteId) async {
    try {
      final response = await _dio.post('/quotes/$quoteId/accept');
      return ApiResponse<Quote>.fromJson(
        response.data,
        (json) => Quote.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<Quote>.fromJson(
          e.response!.data,
          (json) => Quote.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }

  /// Reject a quote
  Future<ApiResponse<void>> rejectQuote(int quoteId, String? reason) async {
    try {
      final response = await _dio.post(
        '/quotes/$quoteId/reject',
        data: {'reason': reason},
      );
      return ApiResponse<void>.fromJson(response.data, (_) {});
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<void>.fromJson(e.response!.data, (_) {});
      }
      rethrow;
    }
  }

  /// Get escrow wallet for a project
  Future<ApiResponse<EscrowWallet>> getEscrowWallet(int projectId) async {
    try {
      final response = await _dio.get('/projects/$projectId/escrow');
      return ApiResponse<EscrowWallet>.fromJson(
        response.data,
        (json) => EscrowWallet.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<EscrowWallet>.fromJson(
          e.response!.data,
          (json) => EscrowWallet.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }

  /// Get material token for a project
  Future<ApiResponse<MaterialToken>> getMaterialToken(int projectId) async {
    try {
      final response = await _dio.get('/projects/$projectId/token');
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
}
