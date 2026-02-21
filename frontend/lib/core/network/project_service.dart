import 'package:dio/dio.dart';
import '../../shared/models/auth_response.dart';
import '../../shared/models/project_model.dart';
import 'dio_client.dart';

class ProjectService {
  final Dio _dio = DioClient().dio;

  /// Create a new project (quote request)
  Future<ApiResponse<Project>> createProject({
    required int? artisanId,
    required int tradeId,
    required String title,
    required String description,
    required double latitude,
    required double longitude,
    required String address,
    double? budgetMin,
    double? budgetMax,
    String? expectedCompletionDate,
  }) async {
    try {
      final response = await _dio.post(
        '/v1/projects',
        data: {
          if (artisanId != null) 'artisan_id': artisanId,
          'trade_id': tradeId,
          'title': title,
          'description': description,
          'latitude': latitude,
          'longitude': longitude,
          'address': address,
          if (budgetMin != null) 'budget_min': budgetMin,
          if (budgetMax != null) 'budget_max': budgetMax,
          if (expectedCompletionDate != null)
            'expected_completion_date': expectedCompletionDate,
        },
      );

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

  /// Get user's projects
  Future<ApiResponse<List<Project>>> getMyProjects({String? status}) async {
    try {
      final response = await _dio.get(
        '/v1/projects/my',
        queryParameters: {if (status != null) 'status': status},
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
  Future<ApiResponse<Project>> getProject(int id) async {
    try {
      final response = await _dio.get('/v1/projects/$id');

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

  /// Cancel a project
  Future<ApiResponse<void>> cancelProject(int id, String reason) async {
    try {
      final response = await _dio.delete(
        '/v1/projects/$id',
        data: {'reason': reason},
      );

      return ApiResponse<void>.fromJson(response.data, (json) => null);
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<void>.fromJson(e.response!.data, (json) => null);
      }
      rethrow;
    }
  }
}
