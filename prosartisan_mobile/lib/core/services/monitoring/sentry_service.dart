import 'package:flutter/foundation.dart';
import 'package:sentry_flutter/sentry_flutter.dart';

/// Service for error tracking and monitoring with Sentry
class SentryService {
  static const String _dsn = String.fromEnvironment(
    'SENTRY_DSN',
    defaultValue:
        'https://d8b702d7e86c689a42f5fbcee3d89f94@o464942.ingest.us.sentry.io/4510794020225024',
  );

  static const String _environment = String.fromEnvironment(
    'SENTRY_ENVIRONMENT',
    defaultValue: kDebugMode ? 'development' : 'production',
  );

  static final double _tracesSampleRate =
      double.tryParse(
        const String.fromEnvironment(
          'SENTRY_TRACES_SAMPLE_RATE',
          defaultValue: '1.0',
        ),
      ) ??
      1.0;

  static const bool _enabled = bool.fromEnvironment(
    'SENTRY_ENABLE',
    defaultValue: true,
  );

  /// Initialize Sentry
  static Future<void> initialize() async {
    if (!_enabled) {
      debugPrint('Sentry is disabled');
      return;
    }

    await SentryFlutter.init((options) {
      options.dsn = _dsn;
      options.environment = _environment;
      options.tracesSampleRate = _tracesSampleRate;

      // Enable automatic breadcrumbs and session tracking
      options.enableAutoSessionTracking = true;

      // Capture failed HTTP requests
      options.captureFailedRequests = true;

      // Set release version
      options.release = 'prosartisan-mobile@1.0.0';

      // Debug mode
      options.debug = kDebugMode;

      // Before send callback to filter events
      options.beforeSend = (event, hint) {
        // Don't send events in debug mode unless explicitly enabled
        if (kDebugMode && !_enabled) {
          return null;
        }
        return event;
      };
    });

    debugPrint('Sentry initialized: $_environment');
  }

  /// Capture an exception
  static Future<void> captureException(
    dynamic exception, {
    dynamic stackTrace,
    String? hint,
    Map<String, dynamic>? extra,
  }) async {
    if (!_enabled) return;

    await Sentry.captureException(
      exception,
      stackTrace: stackTrace,
      hint: hint != null ? Hint.withMap({'hint': hint}) : null,
      withScope: (scope) {
        if (extra != null) {
          extra.forEach((key, value) {
            scope.setExtra(key, value);
          });
        }
      },
    );
  }

  /// Capture a message
  static Future<void> captureMessage(
    String message, {
    SentryLevel level = SentryLevel.info,
    Map<String, dynamic>? extra,
  }) async {
    if (!_enabled) return;

    await Sentry.captureMessage(
      message,
      level: level,
      withScope: (scope) {
        if (extra != null) {
          extra.forEach((key, value) {
            scope.setExtra(key, value);
          });
        }
      },
    );
  }

  /// Add breadcrumb for tracking user actions
  static void addBreadcrumb({
    required String message,
    String? category,
    SentryLevel level = SentryLevel.info,
    Map<String, dynamic>? data,
  }) {
    if (!_enabled) return;

    Sentry.addBreadcrumb(
      Breadcrumb(
        message: message,
        category: category,
        level: level,
        data: data,
      ),
    );
  }

  /// Set user context
  static Future<void> setUser({
    required String id,
    String? email,
    String? username,
    Map<String, dynamic>? extra,
  }) async {
    if (!_enabled) return;

    await Sentry.configureScope((scope) {
      scope.setUser(
        SentryUser(id: id, email: email, username: username, data: extra),
      );
    });
  }

  /// Clear user context (on logout)
  static Future<void> clearUser() async {
    if (!_enabled) return;

    await Sentry.configureScope((scope) {
      scope.setUser(null);
    });
  }

  /// Set custom context
  static Future<void> setContext(String key, Map<String, dynamic> value) async {
    if (!_enabled) return;

    await Sentry.configureScope((scope) {
      scope.setContexts(key, value);
    });
  }

  /// Set tag
  static Future<void> setTag(String key, String value) async {
    if (!_enabled) return;

    await Sentry.configureScope((scope) {
      scope.setTag(key, value);
    });
  }

  /// Start a transaction for performance monitoring
  static ISentrySpan startTransaction(
    String name,
    String operation, {
    String? description,
  }) {
    if (!_enabled) {
      return NoOpSentrySpan();
    }

    return Sentry.startTransaction(name, operation, description: description);
  }

  /// Close Sentry
  static Future<void> close() async {
    if (!_enabled) return;
    await Sentry.close();
  }
}
