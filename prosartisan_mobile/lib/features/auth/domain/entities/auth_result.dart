import 'user.dart';

/// Authentication result containing user and token
class AuthResult {
  final User user;
  final String token;

  const AuthResult({required this.user, required this.token});
}
