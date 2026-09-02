import 'package:flutter/foundation.dart';
import 'error_handler.dart';

/// Logger centralisé pour l'application
/// Utilise ErrorHandler pour envoyer vers Telegram
class AppLogger {
  static const String _tag = 'ProsArtisan';

  /// Log une erreur critique
  static Future<void> error(
    String message, {
    dynamic error,
    StackTrace? stackTrace,
    String? context,
  }) async {
    debugPrint('[$_tag] ❌ ERROR: $message');
    if (error != null) {
      debugPrint('  Error: $error');
    }
    if (stackTrace != null) {
      debugPrint('  Stack: $stackTrace');
    }

    await ErrorHandler.handle(
      error ?? message,
      stackTrace: stackTrace,
      context: context,
      showSnackbar: true,
    );
  }

  /// Log un warning
  static Future<void> warning(
    String message, {
    String? context,
  }) async {
    debugPrint('[$_tag] ⚠️ WARNING: $message');

    await ErrorHandler.logWarning(
      message,
      context: context,
    );
  }

  /// Log une info (debug uniquement)
  static Future<void> info(
    String message, {
    String? context,
  }) async {
    debugPrint('[$_tag] ℹ️ INFO: $message');

    if (kDebugMode) {
      await ErrorHandler.logInfo(
        message,
        context: context,
      );
    }
  }

  /// Log un événement métier important
  static Future<void> event(
    String eventName, {
    Map<String, dynamic>? data,
    String? context,
  }) async {
    debugPrint('[$_tag] 📊 EVENT: $eventName');
    if (data != null) {
      debugPrint('  Data: $data');
    }

    await ErrorHandler.logEvent(
      eventName,
      data: data,
      context: context,
    );
  }

  /// Log un succès (debug uniquement)
  static void success(String message) {
    debugPrint('[$_tag] ✅ SUCCESS: $message');
  }

  /// Log un debug simple (console uniquement)
  static void debug(String message) {
    if (kDebugMode) {
      debugPrint('[$_tag] 🔍 DEBUG: $message');
    }
  }

  /// Log une action utilisateur
  static Future<void> userAction(
    String action, {
    Map<String, dynamic>? data,
  }) async {
    debugPrint('[$_tag] 👤 USER ACTION: $action');

    await event(
      'user_action',
      data: {
        'action': action,
        ...?data,
      },
      context: 'User Interaction',
    );
  }

  /// Log une navigation
  static void navigation(String from, String to) {
    debugPrint('[$_tag] 🧭 NAVIGATION: $from → $to');
  }

  /// Log une transaction financière
  static Future<void> transaction(
    String type, {
    required int montant,
    Map<String, dynamic>? data,
  }) async {
    debugPrint('[$_tag] 💰 TRANSACTION: $type - $montant FCFA');

    await event(
      'transaction',
      data: {
        'type': type,
        'montant': montant,
        ...?data,
      },
      context: 'Financial Transaction',
    );
  }

  /// Log une opération KYC
  static Future<void> kyc(
    String action, {
    String? status,
    Map<String, dynamic>? data,
  }) async {
    debugPrint('[$_tag] 🔐 KYC: $action${status != null ? " - $status" : ""}');

    await event(
      'kyc_$action',
      data: {
        'status': status,
        ...?data,
      },
      context: 'KYC Process',
    );
  }

  /// Log une opération de mission
  static Future<void> mission(
    String action, {
    int? missionId,
    Map<String, dynamic>? data,
  }) async {
    debugPrint(
      '[$_tag] 🎯 MISSION: $action${missionId != null ? " #$missionId" : ""}',
    );

    await event(
      'mission_$action',
      data: {
        'mission_id': missionId,
        ...?data,
      },
      context: 'Mission Management',
    );
  }

  /// Log une opération de J-Code
  static Future<void> jcode(
    String action, {
    String? code,
    Map<String, dynamic>? data,
  }) async {
    debugPrint('[$_tag] 🎫 JCODE: $action${code != null ? " - $code" : ""}');

    await event(
      'jcode_$action',
      data: {
        'code': code,
        ...?data,
      },
      context: 'J-Code Operation',
    );
  }

  /// Log une opération de jalon
  static Future<void> jalon(
    String action, {
    int? jalonId,
    Map<String, dynamic>? data,
  }) async {
    debugPrint(
      '[$_tag] 📍 JALON: $action${jalonId != null ? " #$jalonId" : ""}',
    );

    await event(
      'jalon_$action',
      data: {
        'jalon_id': jalonId,
        ...?data,
      },
      context: 'Jalon Management',
    );
  }

  /// Log une erreur de géolocalisation
  static Future<void> geoError(
    String message, {
    dynamic error,
  }) async {
    debugPrint('[$_tag] 📍 GEO ERROR: $message');

    await ErrorHandler.logWarning(
      'Geolocation: $message',
      context: 'GPS/Location',
    );
  }

  /// Log une erreur de permission
  static Future<void> permissionDenied(
    String permission, {
    String? context,
  }) async {
    debugPrint('[$_tag] 🚫 PERMISSION DENIED: $permission');

    await ErrorHandler.logWarning(
      'Permission refusée: $permission',
      context: context ?? 'Permissions',
    );
  }
}
