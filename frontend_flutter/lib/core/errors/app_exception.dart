import 'package:dio/dio.dart';

/// Erreur applicative typée. Base de la hiérarchie d'exceptions du projet,
/// préalable à l'activation du lint `only_throw_errors`.
///
/// Toujours porteuse d'un [message] affichable à l'utilisateur (français).
sealed class AppException implements Exception {
  const AppException(this.message, {this.cause, this.stackTrace});

  final String message;
  final Object? cause;
  final StackTrace? stackTrace;

  @override
  String toString() => '$runtimeType: $message';
}

/// Problème de connectivité, de timeout ou serveur (5xx).
class NetworkException extends AppException {
  const NetworkException(
    super.message, {
    this.statusCode,
    super.cause,
    super.stackTrace,
  });

  final int? statusCode;

  factory NetworkException.fromDio(DioException e) {
    final code = e.response?.statusCode;
    final message = switch (e.type) {
      DioExceptionType.connectionTimeout ||
      DioExceptionType.sendTimeout ||
      DioExceptionType.receiveTimeout =>
        'Délai de connexion dépassé. Vérifiez votre réseau.',
      DioExceptionType.connectionError =>
        'Impossible de joindre le serveur. Vérifiez votre connexion.',
      _ when code != null && code >= 500 =>
        'Le serveur a rencontré une erreur. Réessayez plus tard.',
      _ => 'Une erreur réseau est survenue.',
    };
    return NetworkException(
      message,
      statusCode: code,
      cause: e,
      stackTrace: e.stackTrace,
    );
  }
}

/// Erreur de validation métier renvoyée par l'API (HTTP 422 le plus souvent).
class ValidationException extends AppException {
  const ValidationException(
    super.message, {
    this.fieldErrors = const {},
    super.cause,
    super.stackTrace,
  });

  /// `{'phone': ['Le numéro est invalide'], ...}`
  final Map<String, List<String>> fieldErrors;
}

/// Session invalide / expirée (HTTP 401/403).
class AuthException extends AppException {
  const AuthException(
    super.message, {
    this.isExpired = false,
    super.cause,
    super.stackTrace,
  });

  final bool isExpired;
}

/// Échec de lecture/écriture du cache local.
class CacheException extends AppException {
  const CacheException(super.message, {super.cause, super.stackTrace});
}

/// Cas non prévu (format de réponse inattendu, invariant rompu…).
class UnexpectedException extends AppException {
  const UnexpectedException(
    super.message, {
    super.cause,
    super.stackTrace,
  });
}
