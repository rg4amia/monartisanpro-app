import 'package:dio/dio.dart';

/// Exécute une requête réseau avec un mécanisme de retry automatique.
///
/// Politique (identique à l'ancienne implémentation privée de
/// `MissionRepository`) :
/// - retry sur `connectionTimeout`, `receiveTimeout`, `sendTimeout`,
///   `connectionError`, ainsi que sur les statuts `408` et `429` ;
/// - **jamais** de retry sur les autres `4xx` (erreur métier) ;
/// - backoff exponentiel : `retryDelay`, `2×`, `4×`… ;
/// - le `429` déclenche systématiquement le backoff exponentiel.
class NetworkExecutor {
  const NetworkExecutor._();

  static const int defaultMaxRetries = 3;
  static const Duration defaultRetryDelay = Duration(seconds: 1);

  /// Lance [request] et la rejoue jusqu'à [maxRetries] fois selon la politique.
  ///
  /// Relance la dernière [DioException] si toutes les tentatives échouent, ou
  /// immédiatement pour une erreur non-retryable (4xx hors 408/429).
  static Future<Response<T>> run<T>(
    Future<Response<T>> Function() request, {
    int maxRetries = defaultMaxRetries,
    Duration retryDelay = defaultRetryDelay,
  }) async {
    var attempt = 0;
    DioException? lastError;

    while (attempt < maxRetries) {
      try {
        return await request();
      } on DioException catch (e) {
        lastError = e;
        final statusCode = e.response?.statusCode;

        if (statusCode == 408 || statusCode == 429) {
          attempt++;
          if (attempt < maxRetries) {
            final delayMs = statusCode == 429
                ? retryDelay.inMilliseconds * (1 << attempt) // 2s, 4s, 8s…
                : retryDelay.inMilliseconds;
            await Future<void>.delayed(Duration(milliseconds: delayMs));
            continue;
          }
          rethrow;
        }

        // Autres erreurs client : pas de retry.
        if (statusCode != null && statusCode >= 400 && statusCode < 500) {
          rethrow;
        }

        final shouldRetry = e.type == DioExceptionType.connectionTimeout ||
            e.type == DioExceptionType.receiveTimeout ||
            e.type == DioExceptionType.sendTimeout ||
            e.type == DioExceptionType.connectionError;

        if (shouldRetry && attempt < maxRetries - 1) {
          attempt++;
          await Future<void>.delayed(retryDelay);
          continue;
        }

        rethrow;
      }
    }

    throw lastError ??
        DioException(
          requestOptions: RequestOptions(path: ''),
          error: 'Toutes les tentatives réseau ont échoué',
        );
  }
}
