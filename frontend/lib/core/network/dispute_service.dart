import 'package:dio/dio.dart';
import 'package:get/get.dart' as getx;
import '../../shared/models/dispute_model.dart';
import 'dio_client.dart';

class DisputeService {
  final DioClient _dioClient = getx.Get.find();

  Future<List<Dispute>> getDisputes({String? status}) async {
    try {
      final queryParams = status != null ? {'status': status} : null;
      final response = await _dioClient.dio.get(
        '/disputes',
        queryParameters: queryParams,
      );

      if (response.statusCode == 200) {
        final List<dynamic> data = response.data['data'] ?? response.data;
        return data.map((json) => Dispute.fromJson(json)).toList();
      }

      throw Exception('Failed to load disputes');
    } on DioException catch (e) {
      throw Exception(e.response?.data['message'] ?? 'Failed to load disputes');
    }
  }

  Future<Dispute> getDisputeById(int id) async {
    try {
      final response = await _dioClient.dio.get('/disputes/$id');

      if (response.statusCode == 200) {
        return Dispute.fromJson(response.data['data'] ?? response.data);
      }

      throw Exception('Failed to load dispute details');
    } on DioException catch (e) {
      throw Exception(e.response?.data['message'] ?? 'Failed to load dispute details');
    }
  }

  Future<Dispute> createDispute(CreateDisputeRequest request) async {
    try {
      final response = await _dioClient.dio.post(
        '/disputes',
        data: request.toJson(),
      );

      if (response.statusCode == 201 || response.statusCode == 200) {
        return Dispute.fromJson(response.data['data'] ?? response.data);
      }

      throw Exception('Failed to create dispute');
    } on DioException catch (e) {
      throw Exception(e.response?.data['message'] ?? 'Failed to create dispute');
    }
  }

  Future<DisputeMessage> sendMessage(
    int disputeId,
    String message, {
    List<String>? attachments,
  }) async {
    try {
      final response = await _dioClient.dio.post(
        '/disputes/$disputeId/messages',
        data: {
          'message': message,
          if (attachments != null) 'attachments': attachments,
        },
      );

      if (response.statusCode == 201 || response.statusCode == 200) {
        return DisputeMessage.fromJson(response.data['data'] ?? response.data);
      }

      throw Exception('Failed to send message');
    } on DioException catch (e) {
      throw Exception(e.response?.data['message'] ?? 'Failed to send message');
    }
  }

  Future<List<DisputeMessage>> getMessages(int disputeId) async {
    try {
      final response = await _dioClient.dio.get('/disputes/$disputeId/messages');

      if (response.statusCode == 200) {
        final List<dynamic> data = response.data['data'] ?? response.data;
        return data.map((json) => DisputeMessage.fromJson(json)).toList();
      }

      throw Exception('Failed to load messages');
    } on DioException catch (e) {
      throw Exception(e.response?.data['message'] ?? 'Failed to load messages');
    }
  }

  Future<void> uploadEvidence(int disputeId, List<String> filePaths) async {
    try {
      final formData = FormData();

      for (var i = 0; i < filePaths.length; i++) {
        formData.files.add(MapEntry(
          'evidence[$i]',
          await MultipartFile.fromFile(filePaths[i]),
        ));
      }

      final response = await _dioClient.dio.post(
        '/disputes/$disputeId/evidence',
        data: formData,
      );

      if (response.statusCode != 200 && response.statusCode != 201) {
        throw Exception('Failed to upload evidence');
      }
    } on DioException catch (e) {
      throw Exception(e.response?.data['message'] ?? 'Failed to upload evidence');
    }
  }
}
