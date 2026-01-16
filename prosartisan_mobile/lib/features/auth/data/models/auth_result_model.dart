import '../../domain/entities/auth_result.dart';
import 'user_model.dart';

/// Authentication result model for JSON serialization
class AuthResultModel extends AuthResult {
  const AuthResultModel({required super.user, required super.token});

  /// Create AuthResultModel from JSON
  factory AuthResultModel.fromJson(Map<String, dynamic> json) {
    return AuthResultModel(
      user: UserModel.fromJson(json['user'] as Map<String, dynamic>),
      token: json['token'] as String,
    );
  }

  /// Convert AuthResultModel to JSON
  Map<String, dynamic> toJson() {
    return {'user': (user as UserModel).toJson(), 'token': token};
  }

  /// Convert to AuthResult entity
  AuthResult toEntity() {
    return AuthResult(user: user, token: token);
  }
}
