import 'package:dio/dio.dart';

import 'app_exception.dart';

/// Convertit une erreur brute (généralement une [DioException]) en
/// [AppException] typée, avec extraction du message serveur si présent.
AppException toAppException(Object error, [StackTrace? stackTrace]) {
  if (error is AppException) return error;

  if (error is DioException) {
    final status = error.response?.statusCode;
    final data = error.response?.data;
    final serverMessage = _extractMessage(data);

    if (status == 401 || status == 403) {
      return AuthException(
        serverMessage ?? 'Session expirée, veuillez vous reconnecter.',
        isExpired: status == 401,
        cause: error,
        stackTrace: stackTrace ?? error.stackTrace,
      );
    }

    if (status == 422) {
      return ValidationException(
        serverMessage ?? 'Certaines informations sont invalides.',
        fieldErrors: _extractFieldErrors(data),
        cause: error,
        stackTrace: stackTrace ?? error.stackTrace,
      );
    }

    if (status != null && status >= 400 && status < 500) {
      return ValidationException(
        serverMessage ?? 'Requête refusée par le serveur.',
        cause: error,
        stackTrace: stackTrace ?? error.stackTrace,
      );
    }

    return NetworkException.fromDio(error);
  }

  return UnexpectedException(
    'Une erreur inattendue est survenue.',
    cause: error,
    stackTrace: stackTrace,
  );
}

String? _extractMessage(Object? data) {
  if (data is Map) {
    final m = data['message'] ?? data['error'] ?? data['msg'];
    if (m is String && m.trim().isNotEmpty) return m;
  }
  return null;
}

Map<String, List<String>> _extractFieldErrors(Object? data) {
  if (data is Map && data['errors'] is Map) {
    return (data['errors'] as Map).map(
      (key, value) => MapEntry(
        key.toString(),
        (value is List)
            ? value.map((e) => e.toString()).toList()
            : [value.toString()],
      ),
    );
  }
  return const {};
}
