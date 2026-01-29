import 'package:flutter/foundation.dart';
import '../services/monitoring/sentry_service.dart';

/// Global error handler for the application
class ErrorHandler {
  /// Handle and log errors
  static Future<void> handleError(
    dynamic error, {
    dynamic stackTrace,
    String? context,
    Map<String, dynamic>? extra,
  }) async {
    // Log to console in debug mode
    if (kDebugMode) {
      debugPrint('Error in $context: $error');
      if (stackTrace != null) {
        debugPrint('Stack trace: $stackTrace');
      }
    }

    // Send to Sentry
    await SentryService.captureException(
      error,
      stackTrace: stackTrace,
      hint: context,
      extra: extra,
    );
  }

  /// Handle API errors
  static Future<void> handleApiError(
    dynamic error, {
    String? endpoint,
    int? statusCode,
    Map<String, dynamic>? requestData,
  }) async {
    await handleError(
      error,
      context: 'API Error',
      extra: {
        'endpoint': endpoint,
        'status_code': statusCode,
        'request_data': requestData,
      },
    );
  }

  /// Handle database errors
  static Future<void> handleDatabaseError(
    dynamic error, {
    String? operation,
    String? table,
  }) async {
    await handleError(
      error,
      context: 'Database Error',
      extra: {'operation': operation, 'table': table},
    );
  }

  /// Handle navigation errors
  static Future<void> handleNavigationError(
    dynamic error, {
    String? route,
  }) async {
    await handleError(
      error,
      context: 'Navigation Error',
      extra: {'route': route},
    );
  }

  /// Get user-friendly error message
  static String getUserMessage(dynamic error) {
    if (error is Exception) {
      final message = error.toString().replaceAll('Exception: ', '');
      return message;
    }
    return 'Une erreur est survenue. Veuillez réessayer.';
  }
}
