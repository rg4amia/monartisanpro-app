import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

/// Erreurs de l'application : trace locale et message pour l'utilisateur.
///
/// Rien ne part vers un service tiers. Un journal d'erreurs envoyé depuis le
/// téléphone supposait d'embarquer le jeton d'un bot dans l'application, où
/// il se lit ; un tel journal passe par le serveur.
class ErrorHandler {
  /// Gère une erreur générique
  static Future<void> handle(
    dynamic error, {
    StackTrace? stackTrace,
    String? context,
    bool showSnackbar = true,
  }) async {
    debugPrint('❌ Error: $error');
    if (stackTrace != null) {
      debugPrint('Stack: $stackTrace');
    }

    // Affiche un snackbar à l'utilisateur
    if (showSnackbar) {
      _showErrorSnackbar(getErrorMessage(error));
    }
  }

  /// Gère une erreur HTTP Dio
  static Future<void> handleDioError(
    DioException error, {
    String? context,
    bool showSnackbar = true,
  }) async {
    debugPrint('🌐 HTTP Error: ${error.type}');
    debugPrint('URL: ${error.requestOptions.uri}');
    debugPrint('Response: ${error.response?.data}');

    // Affiche un snackbar à l'utilisateur
    if (showSnackbar) {
      _showErrorSnackbar(getDioErrorMessage(error));
    }
  }

  /// Extrait un message d'erreur lisible
  static String getErrorMessage(dynamic error) {
    if (error is DioException) {
      return getDioErrorMessage(error);
    }

    if (error is String) {
      return error;
    }

    return 'Une erreur est survenue';
  }

  /// Extrait un message d'erreur Dio lisible
  static String getDioErrorMessage(DioException error) {
    switch (error.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.sendTimeout:
      case DioExceptionType.receiveTimeout:
        return 'Délai de connexion dépassé';

      case DioExceptionType.badResponse:
        final statusCode = error.response?.statusCode;
        final data = error.response?.data;

        if (statusCode == 401) {
          return 'Session expirée, veuillez vous reconnecter';
        }

        if (statusCode == 403) {
          return 'Accès refusé';
        }

        if (statusCode == 404) {
          return 'Ressource introuvable';
        }

        if (statusCode == 422 && data is Map) {
          // Erreurs de validation Laravel
          if (data['errors'] != null) {
            final errors = data['errors'] as Map;
            final firstError = errors.values.first;
            if (firstError is List && firstError.isNotEmpty) {
              return firstError.first.toString();
            }
          }
          if (data['message'] != null) {
            return data['message'].toString();
          }
        }

        if (statusCode != null && statusCode >= 500) {
          return 'Erreur serveur, veuillez réessayer';
        }

        if (data is Map && data['message'] != null) {
          return data['message'].toString();
        }

        return 'Erreur HTTP $statusCode';

      case DioExceptionType.cancel:
        return 'Requête annulée';

      case DioExceptionType.connectionError:
        return 'Pas de connexion internet';

      case DioExceptionType.badCertificate:
        return 'Certificat SSL invalide';

      case DioExceptionType.transformTimeout:
        return 'Délai de connexion dépassé';

      case DioExceptionType.unknown:
        return 'Erreur de connexion';
    }
  }

  /// Affiche un snackbar d'erreur
  static void _showErrorSnackbar(String message) {
    if (!Get.isSnackbarOpen) {
      Get.snackbar(
        'Erreur',
        message,
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: const Color(0xFFE53935),
        colorText: const Color(0xFFFFFFFF),
        duration: const Duration(seconds: 4),
        margin: const EdgeInsets.all(16),
        borderRadius: 8,
      );
    }
  }
}
