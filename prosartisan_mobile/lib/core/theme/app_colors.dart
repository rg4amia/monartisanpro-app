import 'package:flutter/material.dart';

/// Système de couleurs pour l'application ProSartisan
/// Basé sur le design system avec support du thème sombre par défaut
class AppColors {
  AppColors._();

  // ==================== DARK THEME (Principal) ====================

  // Backgrounds
  static const Color primaryBg = Color(0xFF1A1F3A);
  static const Color secondaryBg = Color(0xFF232B4A);
  static const Color cardBg = Color(0xFF2A3354);
  static const Color elevatedBg = Color(0xFF343D5F);

  // Text Colors
  static const Color textPrimary = Color(0xFFFFFFFF);
  static const Color textSecondary = Color(0xFFA8B2D1);
  static const Color textTertiary = Color(0xFF7A8AA8);
  static const Color textMuted = Color(0xFF5A6B8C);

  // Accent Colors
  static const Color accentPrimary = Color(0xFF5B7FFF);
  static const Color accentSuccess = Color(0xFF4ADE80);
  static const Color accentWarning = Color(0xFFFBBF24);
  static const Color accentDanger = Color(0xFFEF4444);

  // Overlays
  static const Color overlayLight = Color(0x0DFFFFFF);
  static const Color overlayMedium = Color(0x1AFFFFFF);
  static const Color overlayHeavy = Color(0x4D000000);

  // ==================== LIGHT THEME ====================

  // Light Backgrounds
  static const Color lightBg = Color(0xFFF8F9FA);
  static const Color lightCard = Color(0xFFFFFFFF);
  static const Color lightSecondaryBg = Color(0xFFFFFFFF);

  // Light Text
  static const Color lightTextPrimary = Color(0xFF1A1A1A);
  static const Color lightTextSecondary = Color(0xFF6B7280);
  static const Color lightTextTertiary = Color(0xFF9CA3AF);

  // Light Accents (ajustés pour le contraste)
  static const Color lightAccentPrimary = Color(0xFF4F46E5);
  static const Color lightAccentSuccess = Color(0xFF10B981);
  static const Color lightAccentWarning = Color(0xFFF59E0B);
  static const Color lightAccentDanger = Color(0xFFEF4444);

  // ==================== STATUS COLORS ====================

  static const Color statusCompleted = Color(0xFF10B981);
  static const Color statusPending = Color(0xFFF59E0B);
  static const Color statusCancelled = Color(0xFFEF4444);
  static const Color statusConfirmed = Color(0xFF5B7FFF);

  // ==================== RATING & SPECIAL ====================

  static const Color ratingYellow = Color(0xFFFBBF24);
  static const Color categoryActive = Color(0xFF4ADE80);
  static const Color categoryInactive = cardBg;

  // ==================== GRADIENTS ====================

  static const List<Color> promotionalGradient = [
    Color(0xFF5B7FFF),
    Color(0xFF4F46E5),
  ];

  static const List<Color> paymentCardGradient = [
    Color(0xFFFF6B9D),
    Color(0xFFF59E0B),
  ];

  static const List<Color> paymentCardGradientBlue = [
    Color(0xFF5B7FFF),
    Color(0xFF8B5CF6),
  ];

  // ==================== HELPER METHODS ====================

  /// Retourne la couleur de statut appropriée
  static Color getStatusColor(String status) {
    switch (status.toLowerCase()) {
      case 'completed':
      case 'terminé':
        return statusCompleted;
      case 'pending':
      case 'en_attente':
        return statusPending;
      case 'cancelled':
      case 'annulé':
        return statusCancelled;
      case 'confirmed':
      case 'confirmé':
        return statusConfirmed;
      default:
        return textSecondary;
    }
  }

  /// Retourne un gradient promotionnel
  static LinearGradient get promotionalLinearGradient => const LinearGradient(
    colors: promotionalGradient,
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  /// Retourne un gradient pour carte de paiement
  static LinearGradient get paymentCardLinearGradient => const LinearGradient(
    colors: paymentCardGradient,
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  /// Retourne un gradient bleu pour carte de paiement
  static LinearGradient get paymentCardBlueGradient => const LinearGradient(
    colors: paymentCardGradientBlue,
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );
}
