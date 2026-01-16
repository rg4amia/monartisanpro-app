import 'dart:io';
import '../repositories/auth_repository.dart';

/// Use case for uploading KYC documents
class UploadKycUseCase {
  final AuthRepository _repository;

  UploadKycUseCase(this._repository);

  Future<void> call({
    required String userId,
    required String idType,
    required String idNumber,
    required File idDocument,
    required File selfie,
  }) async {
    return await _repository.uploadKyc(
      userId: userId,
      idType: idType,
      idNumber: idNumber,
      idDocument: idDocument,
      selfie: selfie,
    );
  }
}
