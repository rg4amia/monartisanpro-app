import '../repositories/auth_repository.dart';

/// Use case for OTP verification
class VerifyOtpUseCase {
  final AuthRepository _repository;

  VerifyOtpUseCase(this._repository);

  Future<bool> call({required String phoneNumber, required String code}) async {
    return await _repository.verifyOtp(phoneNumber: phoneNumber, code: code);
  }
}
