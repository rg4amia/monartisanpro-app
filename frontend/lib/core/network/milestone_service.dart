import 'package:dio/dio.dart';
import '../../shared/models/auth_response.dart';
import '../../shared/models/milestone_model.dart';
import 'dio_client.dart';

class MilestoneService {
  final Dio _dio = DioClient().dio;

  /// Get milestones for a project
  Future<ApiResponse<List<Milestone>>> getProjectMilestones(
    int projectId,
  ) async {
    try {
      final response = await _dio.get('/projects/$projectId/milestones');

      return ApiResponse<List<Milestone>>.fromJson(
        response.data,
        (json) => (json as List)
            .map((item) => Milestone.fromJson(item as Map<String, dynamic>))
            .toList(),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<List<Milestone>>.fromJson(
          e.response!.data,
          (json) => [],
        );
      }
      rethrow;
    }
  }

  /// Create a new milestone
  Future<ApiResponse<Milestone>> createMilestone(
    CreateMilestoneRequest request,
  ) async {
    try {
      final response = await _dio.post('/milestones', data: request.toJson());

      return ApiResponse<Milestone>.fromJson(
        response.data,
        (json) => Milestone.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<Milestone>.fromJson(
          e.response!.data,
          (json) => Milestone.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }

  /// Complete a milestone (artisan)
  Future<ApiResponse<Milestone>> completeMilestone(
    CompleteMilestoneRequest request,
  ) async {
    try {
      FormData? formData;

      if (request.photoPath != null) {
        formData = FormData.fromMap({
          'milestone_id': request.milestoneId,
          'photo': await MultipartFile.fromFile(request.photoPath!),
          if (request.notes != null) 'notes': request.notes,
        });
      }

      final response = await _dio.post(
        '/milestones/${request.milestoneId}/complete',
        data: formData ?? request.toJson(),
      );

      return ApiResponse<Milestone>.fromJson(
        response.data,
        (json) => Milestone.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<Milestone>.fromJson(
          e.response!.data,
          (json) => Milestone.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }

  /// Validate a milestone (client)
  Future<ApiResponse<Milestone>> validateMilestone(
    ValidateMilestoneRequest request,
  ) async {
    try {
      final response = await _dio.post(
        '/milestones/${request.milestoneId}/validate',
        data: request.toJson(),
      );

      return ApiResponse<Milestone>.fromJson(
        response.data,
        (json) => Milestone.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<Milestone>.fromJson(
          e.response!.data,
          (json) => Milestone.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }

  /// Send OTP for milestone validation
  Future<ApiResponse<void>> sendMilestoneOtp(int milestoneId) async {
    try {
      final response = await _dio.post('/milestones/$milestoneId/send-otp');

      return ApiResponse<void>.fromJson(
        response.data,
        (json) => null,
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<void>.fromJson(
          e.response!.data,
          (json) => null,
        );
      }
      rethrow;
    }
  }

  /// Get labor payments for a project
  Future<ApiResponse<List<LaborPayment>>> getProjectPayments(
    int projectId,
  ) async {
    try {
      final response = await _dio.get('/projects/$projectId/labor-payments');

      return ApiResponse<List<LaborPayment>>.fromJson(
        response.data,
        (json) => (json as List)
            .map((item) => LaborPayment.fromJson(item as Map<String, dynamic>))
            .toList(),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<List<LaborPayment>>.fromJson(
          e.response!.data,
          (json) => [],
        );
      }
      rethrow;
    }
  }

  /// Get project progress
  Future<ApiResponse<ProjectProgress>> getProjectProgress(
    int projectId,
  ) async {
    try {
      final response = await _dio.get('/projects/$projectId/progress');

      return ApiResponse<ProjectProgress>.fromJson(
        response.data,
        (json) => ProjectProgress.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<ProjectProgress>.fromJson(
          e.response!.data,
          (json) => ProjectProgress.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }

  /// Update milestone order
  Future<ApiResponse<List<Milestone>>> reorderMilestones(
    int projectId,
    List<int> milestoneIds,
  ) async {
    try {
      final response = await _dio.put(
        '/projects/$projectId/milestones/reorder',
        data: {'milestone_ids': milestoneIds},
      );

      return ApiResponse<List<Milestone>>.fromJson(
        response.data,
        (json) => (json as List)
            .map((item) => Milestone.fromJson(item as Map<String, dynamic>))
            .toList(),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<List<Milestone>>.fromJson(
          e.response!.data,
          (json) => [],
        );
      }
      rethrow;
    }
  }

  /// Delete a milestone
  Future<ApiResponse<void>> deleteMilestone(int milestoneId) async {
    try {
      final response = await _dio.delete('/milestones/$milestoneId');

      return ApiResponse<void>.fromJson(
        response.data,
        (json) => null,
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<void>.fromJson(
          e.response!.data,
          (json) => null,
        );
      }
      rethrow;
    }
  }
}
