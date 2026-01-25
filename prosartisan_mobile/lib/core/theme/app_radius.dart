import 'package:flutter/material.dart';

/// Système de border radius pour l'application ProSartisan
/// Définit tous les rayons de courbure utilisés dans l'app
class AppRadius {
  AppRadius._();

  // ==================== RADIUS SCALE ====================

  /// Small radius (8px)
  static const double sm = 8.0;

  /// Medium radius (12px)
  static const double md = 12.0;

  /// Large radius (16px)
  static const double lg = 16.0;

  /// Extra large radius (20px)
  static const double xl = 20.0;

  /// Double extra large radius (24px)
  static const double xxl = 24.0;

  /// Full radius (9999px) - pour les éléments circulaires
  static const double full = 9999.0;

  // ==================== SPECIFIC RADIUS ====================

  /// Radius pour les cartes
  static const double card = 16.0;

  /// Radius pour les boutons
  static const double button = 12.0;

  /// Radius pour les cartes de catégorie
  static const double category = 16.0;

  /// Radius pour les champs de saisie
  static const double input = 12.0;

  /// Radius pour les badges
  static const double badge = 6.0;

  /// Radius pour les badges de prix
  static const double priceBadge = 8.0;

  /// Radius pour la barre de recherche
  static const double searchBar = 12.0;

  /// Radius pour les modales (coins supérieurs)
  static const double modal = 24.0;

  /// Radius pour les avatars (circulaire)
  static const double avatar = full;

  /// Radius pour les images
  static const double image = 16.0;

  /// Radius pour les pills de navigation
  static const double navigationPill = 20.0;

  // ==================== BORDER RADIUS OBJECTS ====================

  /// BorderRadius small
  static BorderRadius get smallRadius => BorderRadius.circular(sm);

  /// BorderRadius medium
  static BorderRadius get mediumRadius => BorderRadius.circular(md);

  /// BorderRadius large
  static BorderRadius get largeRadius => BorderRadius.circular(lg);

  /// BorderRadius extra large
  static BorderRadius get extraLargeRadius => BorderRadius.circular(xl);

  /// BorderRadius double extra large
  static BorderRadius get doubleExtraLargeRadius => BorderRadius.circular(xxl);

  /// BorderRadius full (circulaire)
  static BorderRadius get fullRadius => BorderRadius.circular(full);

  /// BorderRadius pour les cartes
  static BorderRadius get cardRadius => BorderRadius.circular(card);

  /// BorderRadius pour les boutons
  static BorderRadius get buttonRadius => BorderRadius.circular(button);

  /// BorderRadius pour les catégories
  static BorderRadius get categoryRadius => BorderRadius.circular(category);

  /// BorderRadius pour les champs de saisie
  static BorderRadius get inputRadius => BorderRadius.circular(input);

  /// BorderRadius pour les badges
  static BorderRadius get badgeRadius => BorderRadius.circular(badge);

  /// BorderRadius pour les badges de prix
  static BorderRadius get priceBadgeRadius => BorderRadius.circular(priceBadge);

  /// BorderRadius pour la barre de recherche
  static BorderRadius get searchBarRadius => BorderRadius.circular(searchBar);

  /// BorderRadius pour les images
  static BorderRadius get imageRadius => BorderRadius.circular(image);

  // ==================== SPECIALIZED BORDER RADIUS ====================

  /// BorderRadius pour les modales (coins supérieurs arrondis)
  static BorderRadius get modalRadius => const BorderRadius.only(
    topLeft: Radius.circular(modal),
    topRight: Radius.circular(modal),
  );

  /// BorderRadius pour les cartes avec image (coins supérieurs arrondis)
  static BorderRadius get cardTopRadius => const BorderRadius.only(
    topLeft: Radius.circular(card),
    topRight: Radius.circular(card),
  );

  /// BorderRadius pour les cartes avec image (coins inférieurs arrondis)
  static BorderRadius get cardBottomRadius => const BorderRadius.only(
    bottomLeft: Radius.circular(card),
    bottomRight: Radius.circular(card),
  );

  /// BorderRadius pour la navigation inférieure (coins supérieurs arrondis)
  static BorderRadius get bottomNavRadius => const BorderRadius.only(
    topLeft: Radius.circular(modal),
    topRight: Radius.circular(modal),
  );

  // ==================== HELPER METHODS ====================

  /// Crée un BorderRadius personnalisé
  static BorderRadius circular(double radius) => BorderRadius.circular(radius);

  /// Crée un BorderRadius avec des coins spécifiques
  static BorderRadius only({
    double topLeft = 0,
    double topRight = 0,
    double bottomLeft = 0,
    double bottomRight = 0,
  }) => BorderRadius.only(
    topLeft: Radius.circular(topLeft),
    topRight: Radius.circular(topRight),
    bottomLeft: Radius.circular(bottomLeft),
    bottomRight: Radius.circular(bottomRight),
  );

  /// Crée un BorderRadius pour les coins supérieurs
  static BorderRadius top(double radius) => BorderRadius.only(
    topLeft: Radius.circular(radius),
    topRight: Radius.circular(radius),
  );

  /// Crée un BorderRadius pour les coins inférieurs
  static BorderRadius bottom(double radius) => BorderRadius.only(
    bottomLeft: Radius.circular(radius),
    bottomRight: Radius.circular(radius),
  );

  /// Crée un BorderRadius pour les coins gauches
  static BorderRadius left(double radius) => BorderRadius.only(
    topLeft: Radius.circular(radius),
    bottomLeft: Radius.circular(radius),
  );

  /// Crée un BorderRadius pour les coins droits
  static BorderRadius right(double radius) => BorderRadius.only(
    topRight: Radius.circular(radius),
    bottomRight: Radius.circular(radius),
  );
}
