import 'package:flutter/material.dart';

/// ProsArtisan color palette based on design system
class AppColors {
  AppColors._();

  // Light theme colors
  static const Color lightBackground = Color(0xFFF8F9FA);
  static const Color lightBackgroundSecondary = Color(0xFFFFFFFF);
  static const Color lightCard = Color(0xFFFFFFFF);

  static const Color lightTextPrimary = Color(0xFF1A1A1A);
  static const Color lightTextSecondary = Color(0xFF6B7280);
  static const Color lightTextTertiary = Color(0xFF9CA3AF);

  static const Color lightAccentPrimary = Color(0xFF4F46E5);
  static const Color lightAccentSecondary = Color(0xFF10B981);
  static const Color lightAccentHighlight = Color(0xFFF59E0B);
  static const Color lightAccentDanger = Color(0xFFEF4444);

  // Dark theme colors
  static const Color darkBackground = Color(0xFF1A1F3A);
  static const Color darkBackgroundSecondary = Color(0xFF232B4A);
  static const Color darkCard = Color(0xFF2A3354);
  static const Color darkElevated = Color(0xFF343D5F);

  static const Color darkTextPrimary = Color(0xFFFFFFFF);
  static const Color darkTextSecondary = Color(0xFFA8B2D1);
  static const Color darkTextTertiary = Color(0xFF7A8AA8);
  static const Color darkTextMuted = Color(0xFF5A6B8C);

  static const Color darkAccentPrimary = Color(0xFF5B7FFF);
  static const Color darkAccentSecondary = Color(0xFF4ADE80);
  static const Color darkAccentHighlight = Color(0xFFFBBF24);
  static const Color darkAccentDanger = Color(0xFFEF4444);

  // Status colors (shared)
  static const Color success = Color(0xFF10B981);
  static const Color warning = Color(0xFFF59E0B);
  static const Color error = Color(0xFFEF4444);
  static const Color info = Color(0xFF3B82F6);

  // Rating star color
  static const Color starRating = Color(0xFFFBBF24);

  // Gradients
  static const List<Color> primaryGradient = [
    Color(0xFF4F46E5),
    Color(0xFF7C3AED),
  ];

  static const List<Color> successGradient = [
    Color(0xFF10B981),
    Color(0xFF059669),
  ];

  static const List<Color> warningGradient = [
    Color(0xFFF59E0B),
    Color(0xFFD97706),
  ];

  // Overlay colors (dark theme)
  static const Color overlayLight = Color(
    0x0DFFFFFF,
  ); // rgba(255, 255, 255, 0.05)
  static const Color overlayMedium = Color(
    0x1AFFFFFF,
  ); // rgba(255, 255, 255, 0.1)
  static const Color overlayHeavy = Color(0x4D000000); // rgba(0, 0, 0, 0.3)

  // Specific UI colors
  static const Color goldenMarker = Color(0xFFFBBF24); // For <2km artisans
  static const Color blueMarker = Color(0xFF5B7FFF); // For other artisans
  static const Color clusterMarker = Color(0xFFEF4444); // For clusters
}
