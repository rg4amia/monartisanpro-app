import 'dart:convert';
import 'dart:io';
import 'package:http/http.dart' as http;
import '../../domain/models/dispute.dart';
import '../../../../core/services/api_service.dart';
import '../../../../core/services/auth_service.dart';

/// Repository for dispute-related API operations
///
/// Requirements: 9.1, 9.5, 9.6
class DisputeRepository {
  final ApiService _apiService;
  final AuthService _authService;

  DisputeRepository({
    required ApiService apiService,
    required AuthService authService,
  }) : _apiService = apiService,
       _authService = authService;

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
    final response = await _apiService.post(
      '/disputes',
      data: {
        'mission_id': missionId,
        'defendant_id': defendantId,
        'type': type,
        'description': description,
        'evidence': evidence ?? [],
      },
    );

    if (response.statusCode == 201) {
      final data = json.decode(response.body);
      return Dispute.fromJson(data['data']);
    } else {
      throw Exception('Failed to report dispute: ${response.body}');
    }
  }

  /// Get dispute details
  Future<Dispute> getDispute(String disputeId) async {
    final response = await _apiService.get('/disputes/$disputeId');

    if (response.statusCode == 200) {
      final data = json.decode(response.body);
      return Dispute.fromJson(data['data']);
    } else if (response.statusCode == 404) {
      throw Exception('Dispute not found');
    } else if (response.statusCode == 403) {
      throw Exception('Access denied');
    } else {
      throw Exception('Failed to get dispute: ${response.body}');
    }
  }

  /// Get user's disputes
  Future<List<Dispute>> getUserDisputes() async {
    final response = await _apiService.get('/disputes');

    if (response.statusCode == 200) {
      final data = json.decode(response.body);
      return (data['data'] as List)
          .map((dispute) => Dispute.fromJson(dispute))
          .toList();
    } else {
      throw Exception('Failed to get disputes: ${response.body}');
    }
  }

  /// Start mediation for a dispute (admin only)
  Future<Dispute> startMediation({
    required String disputeId,
    required String mediatorId,
  }) async {
    final response = await _apiService.post(
      '/disputes/$disputeId/mediation/start',
      data: {'mediator_id': mediatorId},
    );

    if (response.statusCode == 200) {
      final data = json.decode(response.body);
      return Dispute.fromJson(data['data']);
    } else if (response.statusCode == 403) {
      throw Exception('Access denied');
    } else if (response.statusCode == 400) {
      final data = json.decode(response.body);
      throw Exception(data['message']);
    } else {
      throw Exception('Failed to start mediation: ${response.body}');
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
      data: {'message': message},
    );

    if (response.statusCode == 200) {
      final data = json.decode(response.body);
      return Dispute.fromJson(data['data']);
    } else if (response.statusCode == 403) {
      throw Exception('Access denied');
    } else if (response.statusCode == 400) {
      final data = json.decode(response.body);
      throw Exception(data['message']);
    } else {
      throw Exception('Failed to send message: ${response.body}');
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
      data['amount_centimes'] = amountCentimes;
    }

    final response = await _apiService.post(
      '/disputes/$disputeId/arbitration/render',
      data: data,
    );

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      return Dispute.fromJson(responseData['data']);
    } else if (response.statusCode == 403) {
      throw Exception('Access denied');
    } else if (response.statusCode == 400) {
      final responseData = json.decode(response.body);
      throw Exception(responseData['message']);
    } else {
      throw Exception('Failed to render arbitration: ${response.body}');
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
      final data = json.decode(response.body);
      return (data['data'] as List)
          .map((dispute) => Dispute.fromJson(dispute))
          .toList();
    } else if (response.statusCode == 403) {
      throw Exception('Access denied');
    } else {
      throw Exception('Failed to get disputes: ${response.body}');
    }
  }

  /// Upload evidence file
  Future<String> uploadEvidence(File file) async {
    // This would typically upload to a file storage service
    // For now, we'll simulate returning a URL
    // In a real implementation, you'd upload to AWS S3, Firebase Storage, etc.

    final request = http.MultipartRequest(
      'POST',
      Uri.parse('${_apiService.baseUrl}/upload/evidence'),
    );

    request.headers.addAll(await _authService.getAuthHeaders());
    request.files.add(await http.MultipartFile.fromPath('file', file.path));

    final streamedResponse = await request.send();
    final response = await http.Response.fromStream(streamedResponse);

    if (response.statusCode == 200) {
      final data = json.decode(response.body);
      return data['url'];
    } else {
      throw Exception('Failed to upload evidence: ${response.body}');
    }
  }
}
