import 'package:dio/dio.dart';
import '../../shared/models/auth_response.dart';
import 'dio_client.dart';

class KycService {
  final Dio _dio = DioClient().dio;

  /// Upload KYC documents
  Future<ApiResponse<void>> uploadDocuments({
    required String documentType,
    required String documentPath,
    String? selfiePath,
  }) async {
    try {
      final formData = FormData();

      formData.fields.add(MapEntry('document_type', documentType));
      formData.files.add(MapEntry(
        'document',
        await MultipartFile.fromFile(documentPath),
      ));

      if (selfiePath != null) {
        formData.files.add(MapEntry(
          'selfie',
          await MultipartFile.fromFile(selfiePath),
        ));
      }

      final response = await _dio.post('/kyc/upload', data: formData);
      return ApiResponse<void>.fromJson(response.data, (_) {});
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<void>.fromJson(e.response!.data, (_) {});
      }
      rethrow;
    }
  }

  /// Get KYC status
  Future<ApiResponse<KycStatus>> getStatus() async {
    try {
      final response = await _dio.get('/kyc/status');
      return ApiResponse<KycStatus>.fromJson(
        response.data,
        (json) => KycStatus.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<KycStatus>.fromJson(
          e.response!.data,
          (json) => KycStatus.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }

  /// Get KYC documents
  Future<ApiResponse<List<KycDocument>>> getDocuments() async {
    try {
      final response = await _dio.get('/kyc/documents');
      return ApiResponse<List<KycDocument>>.fromJson(
        response.data,
        (json) => (json as List)
            .map((doc) => KycDocument.fromJson(doc as Map<String, dynamic>))
            .toList(),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<List<KycDocument>>.fromJson(
          e.response!.data,
          (json) => (json as List)
              .map((doc) => KycDocument.fromJson(doc as Map<String, dynamic>))
              .toList(),
        );
      }
      rethrow;
    }
  }
}

class KycStatus {
  final String status; // pending, approved, rejected
  final String? rejectionReason;
  final DateTime? verifiedAt;

  KycStatus({
    required this.status,
    this.rejectionReason,
    this.verifiedAt,
  });

  factory KycStatus.fromJson(Map<String, dynamic> json) {
    return KycStatus(
      status: json['kyc_status'] as String,
      rejectionReason: json['rejection_reason'] as String?,
      verifiedAt: json['verified_at'] != null
          ? DateTime.parse(json['verified_at'] as String)
          : null,
    );
  }

  bool get isPending => status == 'pending';
  bool get isApproved => status == 'approved';
  bool get isRejected => status == 'rejected';
}

class KycDocument {
  final int id;
  final String documentType;
  final String documentPath;
  final String? selfiePath;
  final String verificationStatus;
  final String? rejectionReason;
  final DateTime createdAt;

  KycDocument({
    required this.id,
    required this.documentType,
    required this.documentPath,
    this.selfiePath,
    required this.verificationStatus,
    this.rejectionReason,
    required this.createdAt,
  });

  factory KycDocument.fromJson(Map<String, dynamic> json) {
    return KycDocument(
      id: json['id'] as int,
      documentType: json['document_type'] as String,
      documentPath: json['document_path'] as String,
      selfiePath: json['selfie_path'] as String?,
      verificationStatus: json['verification_status'] as String,
      rejectionReason: json['rejection_reason'] as String?,
      createdAt: DateTime.parse(json['created_at'] as String),
    );
  }
}
