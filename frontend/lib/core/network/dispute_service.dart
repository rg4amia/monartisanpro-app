import 'package:dio/dio.dart';
import '../../shared/models/auth_response.dart';
import '../../shared/models/dispute_model.dart';
import 'dio_client.dart';

class DisputeService {
  final Dio _dio = DioClient().dio;

  /// Get disputes
  Future<ApiResponse<List<Dispute>>> getDisputes({
    String? status,
    int page = 1,
  }) async {
    try {
      final queryParams = <String, dynamic>{'page': page};

      if (status != null) {
        queryParams['status'] = status;
      }

      final response = await _dio.get(
        '/disputes',
        queryParameters: queryParams,
      );

      return ApiResponse<List<Dispute>>.fromJson(response.data, (json) {
        if (json is List) {
          return json
              .map((item) => Dispute.fromJson(item as Map<String, dynamic>))
              .toList();
        }
        final data = json as Map<String, dynamic>;
        final items = data['data'] as List;
        return items
            .map((item) => Dispute.fromJson(item as Map<String, dynamic>))
            .toList();
      });
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<List<Dispute>>.fromJson(
          e.response!.data,
          (json) => [],
        );
      }
      rethrow;
    }
  }

  /// Get dispute details
  Future<ApiResponse<Dispute>> getDisputeDetails(int disputeId) async {
    try {
      final response = await _dio.get('/disputes/$disputeId');

      return ApiResponse<Dispute>.fromJson(
        response.data,
        (json) => Dispute.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<Dispute>.fromJson(
          e.response!.data,
          (json) => Dispute.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }

  /// Send message
  Future<ApiResponse<DisputeMessage>> sendMessage(
    int disputeId,
    String message,
  ) async {
    try {
      final response = await _dio.post(
        '/disputes/$disputeId/messages',
        data: {'message': message},
      );

      return ApiResponse<DisputeMessage>.fromJson(
        response.data,
        (json) => DisputeMessage.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<DisputeMessage>.fromJson(
          e.response!.data,
          (json) => DisputeMessage.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }

  /// Create dispute
  Future<ApiResponse<Dispute>> createDispute({
    required int projectId,
    required String subject,
    required String description,
  }) async {
    try {
      final response = await _dio.post(
        '/disputes',
        data: {
          'project_id': projectId,
          'subject': subject,
          'description': description,
        },
      );

      return ApiResponse<Dispute>.fromJson(
        response.data,
        (json) => Dispute.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<Dispute>.fromJson(
          e.response!.data,
          (json) => Dispute.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }
}
