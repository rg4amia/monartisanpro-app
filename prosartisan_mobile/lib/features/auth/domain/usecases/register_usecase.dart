import '../entities/auth_result.dart';
import '../repositories/auth_repository.dart';

/// Use case for user registration
class RegisterUseCase {
  final AuthRepository _repository;

  RegisterUseCase(this._repository);

  Future<AuthResult> call({
    required String email,
    required String password,
    required String userType,
    String? phoneNumber,
    String? tradeCategory,
    String? businessName,
    String? tradeName,
    int? sectorId,
    String? sectorName,
  }) async {
    return await _repository.register(
      email: email,
      password: password,
      userType: userType,
      phoneNumber: phoneNumber,
      tradeCategory: tradeCategory,
      businessName: businessName,
      tradeName: tradeName,
      sectorId: sectorId,
      sectorName: sectorName,
    );
  }
}
