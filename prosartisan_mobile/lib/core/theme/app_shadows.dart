import 'package:flutter/material.dart';

/// Système d'ombres pour l'application ProSartisan
/// Définit toutes les ombres utilisées dans l'app avec support des thèmes clair et sombre
class AppShadows {
  AppShadows._();

  // ==================== LIGHT THEME SHADOWS ====================

  /// Ombre petite pour thème clair
  static List<BoxShadow> get lightSmall => [
    BoxShadow(
      color: Colors.black.withOpacity(0.05),
      blurRadius: 2,
      offset: const Offset(0, 1),
    ),
  ];

  /// Ombre moyenne pour thème clair
  static List<BoxShadow> get lightMedium => [
    BoxShadow(
      color: Colors.black.withOpacity(0.07),
      blurRadius: 6,
      offset: const Offset(0, 4),
    ),
  ];

  /// Ombre grande pour thème clair
  static List<BoxShadow> get lightLarge => [
    BoxShadow(
      color: Colors.black.withOpacity(0.1),
      blurRadius: 15,
      offset: const Offset(0, 10),
    ),
  ];

  /// Ombre extra grande pour thème clair
  static List<BoxShadow> get lightExtraLarge => [
    BoxShadow(
      color: Colors.black.withOpacity(0.15),
      blurRadius: 25,
      offset: const Offset(0, 20),
    ),
  ];

  // ==================== DARK THEME SHADOWS ====================

  /// Ombre petite pour thème sombre
  static List<BoxShadow> get darkSmall => [
    BoxShadow(
      color: Colors.black.withOpacity(0.3),
      blurRadius: 2,
      offset: const Offset(0, 1),
    ),
  ];

  /// Ombre moyenne pour thème sombre
  static List<BoxShadow> get darkMedium => [
    BoxShadow(
      color: Colors.black.withOpacity(0.4),
      blurRadius: 6,
      offset: const Offset(0, 4),
    ),
  ];

  /// Ombre grande pour thème sombre
  static List<BoxShadow> get darkLarge => [
    BoxShadow(
      color: Colors.black.withOpacity(0.5),
      blurRadius: 15,
      offset: const Offset(0, 10),
    ),
  ];

  /// Ombre extra grande pour thème sombre
  static List<BoxShadow> get darkExtraLarge => [
    BoxShadow(
      color: Colors.black.withOpacity(0.6),
      blurRadius: 25,
      offset: const Offset(0, 20),
    ),
  ];

  /// Ombre avec effet de lueur pour thème sombre
  static List<BoxShadow> get darkGlow => [
    BoxShadow(
      color: const Color(0xFF5B7FFF).withOpacity(0.3),
      blurRadius: 20,
      offset: const Offset(0, 0),
    ),
  ];

  // ==================== SPECIALIZED SHADOWS ====================

  /// Ombre pour les cartes (thème sombre par défaut)
  static List<BoxShadow> get card => darkMedium;

  /// Ombre pour les cartes promotionnelles
  static List<BoxShadow> get promotionalCard => [
    BoxShadow(
      color: Colors.black.withOpacity(0.4),
      blurRadius: 10,
      offset: const Offset(0, 4),
    ),
  ];

  /// Ombre pour les boutons
  static List<BoxShadow> get button => [
    BoxShadow(
      color: Colors.black.withOpacity(0.3),
      blurRadius: 8,
      offset: const Offset(0, 2),
    ),
  ];

  /// Ombre pour les boutons flottants
  static List<BoxShadow> get floatingButton => [
    BoxShadow(
      color: Colors.black.withOpacity(0.4),
      blurRadius: 12,
      offset: const Offset(0, 6),
    ),
    BoxShadow(
      color: const Color(0xFF5B7FFF).withOpacity(0.2),
      blurRadius: 20,
      offset: const Offset(0, 0),
    ),
  ];

  /// Ombre pour la navigation inférieure
  static List<BoxShadow> get bottomNavigation => [
    BoxShadow(
      color: Colors.black.withOpacity(0.2),
      blurRadius: 20,
      offset: const Offset(0, -5),
    ),
  ];

  /// Ombre pour les modales
  static List<BoxShadow> get modal => [
    BoxShadow(
      color: Colors.black.withOpacity(0.5),
      blurRadius: 30,
      offset: const Offset(0, 10),
    ),
  ];

  /// Ombre pour les champs de saisie focalisés
  static List<BoxShadow> get inputFocused => [
    BoxShadow(
      color: const Color(0xFF5B7FFF).withOpacity(0.2),
      blurRadius: 8,
      offset: const Offset(0, 2),
    ),
  ];

  // ==================== ELEVATION SHADOWS ====================

  /// Élévation 1 (Material Design)
  static List<BoxShadow> get elevation1 => [
    BoxShadow(
      color: Colors.black.withOpacity(0.12),
      blurRadius: 2,
      offset: const Offset(0, 1),
    ),
    BoxShadow(
      color: Colors.black.withOpacity(0.24),
      blurRadius: 1,
      offset: const Offset(0, 1),
    ),
  ];

  /// Élévation 2 (Material Design)
  static List<BoxShadow> get elevation2 => [
    BoxShadow(
      color: Colors.black.withOpacity(0.16),
      blurRadius: 3,
      offset: const Offset(0, 1),
    ),
    BoxShadow(
      color: Colors.black.withOpacity(0.23),
      blurRadius: 3,
      offset: const Offset(0, 3),
    ),
  ];

  /// Élévation 4 (Material Design)
  static List<BoxShadow> get elevation4 => [
    BoxShadow(
      color: Colors.black.withOpacity(0.19),
      blurRadius: 10,
      offset: const Offset(0, 2),
    ),
    BoxShadow(
      color: Colors.black.withOpacity(0.23),
      blurRadius: 5,
      offset: const Offset(0, 6),
    ),
  ];

  /// Élévation 8 (Material Design)
  static List<BoxShadow> get elevation8 => [
    BoxShadow(
      color: Colors.black.withOpacity(0.25),
      blurRadius: 25,
      offset: const Offset(0, 5),
    ),
    BoxShadow(
      color: Colors.black.withOpacity(0.22),
      blurRadius: 10,
      offset: const Offset(0, 15),
    ),
  ];

  // ==================== HELPER METHODS ====================

  /// Retourne l'ombre appropriée selon le thème
  static List<BoxShadow> getShadowForTheme({
    required bool isDark,
    required String size,
  }) {
    if (isDark) {
      switch (size) {
        case 'small':
          return darkSmall;
        case 'medium':
          return darkMedium;
        case 'large':
          return darkLarge;
        case 'extraLarge':
          return darkExtraLarge;
        default:
          return darkMedium;
      }
    } else {
      switch (size) {
        case 'small':
          return lightSmall;
        case 'medium':
          return lightMedium;
        case 'large':
          return lightLarge;
        case 'extraLarge':
          return lightExtraLarge;
        default:
          return lightMedium;
      }
    }
  }

  /// Crée une ombre personnalisée
  static List<BoxShadow> custom({
    required Color color,
    required double blurRadius,
    required Offset offset,
    double opacity = 1.0,
  }) => [
    BoxShadow(
      color: color.withOpacity(opacity),
      blurRadius: blurRadius,
      offset: offset,
    ),
  ];

  /// Ombre avec couleur d'accent
  static List<BoxShadow> accentGlow({
    Color color = const Color(0xFF5B7FFF),
    double opacity = 0.3,
    double blurRadius = 20,
  }) => [
    BoxShadow(
      color: color.withOpacity(opacity),
      blurRadius: blurRadius,
      offset: const Offset(0, 0),
    ),
  ];
}
