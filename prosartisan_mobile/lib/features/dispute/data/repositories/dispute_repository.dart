import 'dart:io';
import 'package:dio/dio.dart';
import '../../../../core/services/api/api_service.dart';
import '../../../../core/services/api/api_client.dart';
import '../../domain/models/dispute.dart';

/// Repository for dispute-related API operations
///
/// Requirements: 9.1, 9.5, 9.6
class DisputeRepository {
  final ApiService _apiService;
  final ApiClient _apiClient;

  DisputeRepository({
    required ApiService apiService,
    required ApiClient apiClient,
  }) : _apiService = apiService,
       _apiClient = apiClient;

  /// Report a new dispute
  ///
  /// Requirement 9.1: Create dispute record
  Future<Dispute> reportDispute({
    required String missionId,
    required String defendantId,
    required String type,
    required String description,
    List<String>? evidence,
  }) async {
    final response = await _apiService.post('/disputes', {
      'mission_id': missionId,
      'defendant_id': defendantId,
      'type': type,
      'description': description,
      'evidence': evidence ?? [],
    });

    if (response.statusCode == 201) {
      final data = response.data;
      return Dispute.fromJson(data['data']);
    } else {
      throw Exception('Failed to report dispute: ${response.data}');
    }
  }

  /// Get dispute details
  Future<Dispute> getDispute(String disputeId) async {
    final response = await _apiService.get('/disputes/$disputeId');

    if (response.statusCode == 200) {
      final data = response.data;
      return Dispute.fromJson(data['data']);
    } else if (response.statusCode == 404) {
      throw Exception('Dispute not found');
    } else if (response.statusCode == 403) {
      throw Exception('Access denied');
    } else {
      throw Exception('Failed to get dispute: ${response.data}');
    }
  }

  /// Get user's disputes
  Future<List<Dispute>> getUserDisputes() async {
    final response = await _apiService.get('/disputes');

    if (response.statusCode == 200) {
      final data = response.data;
      return (data['data'] as List)
          .map((dispute) => Dispute.fromJson(dispute))
          .toList();
    } else {
      throw Exception('Failed to get disputes: ${response.data}');
    }
  }

  /// Start mediation for a dispute (admin only)
  Future<Dispute> startMediation({
    required String disputeId,
    required String mediatorId,
  }) async {
    final response = await _apiService.post(
      '/disputes/$disputeId/mediation/start',
      {'mediator_id': mediatorId},
    );

    if (response.statusCode == 200) {
      final data = response.data;
      return Dispute.fromJson(data['data']);
    } else if (response.statusCode == 403) {
      throw Exception('Access denied');
    } else if (response.statusCode == 400) {
      final data = response.data;
      throw Exception(data['message']);
    } else {
      throw Exception('Failed to start mediation: ${response.data}');
    }
  }

  /// Send message in mediation
  ///
  /// Requirement 9.5: Provide communication channel
  Future<Dispute> sendMediationMessage({
    required String disputeId,
    required String message,
  }) async {
    final response = await _apiService.post(
      '/disputes/$disputeId/mediation/message',
      {'message': message},
    );

    if (response.statusCode == 200) {
      final data = response.data;
      return Dispute.fromJson(data['data']);
    } else if (response.statusCode == 403) {
      throw Exception('Access denied');
    } else if (response.statusCode == 400) {
      final data = response.data;
      throw Exception(data['message']);
    } else {
      throw Exception('Failed to send message: ${response.data}');
    }
  }

  /// Render arbitration decision (admin only)
  ///
  /// Requirement 9.6: Execute arbitration decision
  Future<Dispute> renderArbitration({
    required String disputeId,
    required String decisionType,
    required String justification,
    int? amountCentimes,
  }) async {
    final data = {
      'decision_type': decisionType,
      'justification': justification,
    };

    if (amountCentimes != null) {
      data['amount_centimes'] = amountCentimes.toString();
    }

    final response = await _apiService.post(
      '/disputes/$disputeId/arbitration/render',
      data,
    );

    if (response.statusCode == 200) {
      final responseData = response.data;
      return Dispute.fromJson(responseData['data']);
    } else if (response.statusCode == 403) {
      throw Exception('Access denied');
    } else if (response.statusCode == 400) {
      final responseData = response.data;
      throw Exception(responseData['message']);
    } else {
      throw Exception('Failed to render arbitration: ${response.data}');
    }
  }

  /// Get all disputes (admin only)
  Future<List<Dispute>> getAllDisputes({String? status}) async {
    String endpoint = '/admin/disputes';
    if (status != null) {
      endpoint += '?status=$status';
    }

    final response = await _apiService.get(endpoint);

    if (response.statusCode == 200) {
      final data = response.data;
      return (data['data'] as List)
          .map((dispute) => Dispute.fromJson(dispute))
          .toList();
    } else if (response.statusCode == 403) {
      throw Exception('Access denied');
    } else {
      throw Exception('Failed to get disputes: ${response.data}');
    }
  }

  /// Upload evidence file
  Future<String> uploadEvidence(File file) async {
    // Use the ApiClient's upload functionality
    final data = {
      'file': await MultipartFile.fromFile(
        file.path,
        filename: file.path.split('/').last,
      ),
    };

    final response = await _apiClient.uploadFile('/upload/evidence', data);

    if (response.statusCode == 200) {
      final responseData = response.data;
      return responseData['url'];
    } else {
      throw Exception('Failed to upload evidence: ${response.data}');
    }
  }
}
