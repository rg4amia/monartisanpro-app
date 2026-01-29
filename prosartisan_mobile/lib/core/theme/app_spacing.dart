import 'package:flutter/widgets.dart';

/// Système d'espacement pour l'application ProSartisan
/// Définit tous les espacements et dimensions utilisés dans l'app
class AppSpacing {
  AppSpacing._();

  // ==================== SPACING SCALE ====================

  /// Extra small spacing (4px)
  static const double xs = 4.0;

  /// Small spacing (8px)
  static const double sm = 8.0;

  /// Medium spacing (12px)
  static const double md = 12.0;

  /// Base spacing (16px) - unité de base
  static const double base = 16.0;

  /// Large spacing (20px)
  static const double lg = 20.0;

  /// Extra large spacing (24px)
  static const double xl = 24.0;

  /// Double extra large spacing (32px)
  static const double xxl = 32.0;

  /// Triple extra large spacing (40px)
  static const double xxxl = 40.0;

  /// Quad extra large spacing (48px)
  static const double xxxxl = 48.0;

  // ==================== SPECIFIC SPACINGS ====================

  /// Padding interne des cartes
  static const double cardPadding = 16.0;

  /// Padding des écrans
  static const double screenPadding = 20.0;

  /// Espacement entre les sections
  static const double sectionGap = 24.0;

  /// Espacement entre les éléments de liste
  static const double listItemGap = 12.0;

  /// Espacement dans les grilles
  static const double gridGap = 12.0;

  /// Espacement pour les boutons
  static const double buttonPadding = 16.0;

  /// Espacement horizontal pour les boutons
  static const double buttonHorizontalPadding = 24.0;

  // ==================== DIMENSIONS ====================

  /// Hauteur de la barre de navigation inférieure
  static const double bottomNavHeight = 72.0;

  /// Hauteur de l'app bar
  static const double appBarHeight = 56.0;

  /// Hauteur des boutons principaux
  static const double buttonHeight = 50.0;

  /// Hauteur des champs de saisie
  static const double inputHeight = 50.0;

  /// Taille minimale des zones tactiles
  static const double minTouchTarget = 44.0;

  /// Taille des avatars par défaut
  static const double avatarSize = 48.0;

  /// Taille des avatars petits
  static const double avatarSizeSmall = 32.0;

  /// Taille des avatars grands
  static const double avatarSizeLarge = 64.0;

  /// Taille des icônes par défaut
  static const double iconSize = 24.0;

  /// Taille des icônes petites
  static const double iconSizeSmall = 20.0;

  /// Taille des icônes grandes
  static const double iconSizeLarge = 32.0;

  /// Taille des icônes de catégorie
  static const double categoryIconSize = 48.0;

  // ==================== CARD DIMENSIONS ====================

  /// Hauteur des cartes de service
  static const double serviceCardHeight = 280.0;

  /// Hauteur des cartes de catégorie
  static const double categoryCardHeight = 100.0;

  /// Hauteur des cartes promotionnelles
  static const double promotionalCardHeight = 120.0;

  /// Hauteur des cartes de paiement
  static const double paymentCardHeight = 200.0;

  // ==================== MODAL & DIALOG DIMENSIONS ====================

  /// Hauteur maximale des modales
  static const double modalMaxHeight = 0.85; // 85% de la hauteur d'écran

  /// Largeur maximale des dialogues
  static const double dialogMaxWidth = 400.0;

  /// Padding des modales
  static const double modalPadding = 24.0;

  // ==================== SEARCH BAR ====================

  /// Hauteur de la barre de recherche
  static const double searchBarHeight = 48.0;

  /// Padding horizontal de la barre de recherche
  static const double searchBarHorizontalPadding = 16.0;

  /// Padding vertical de la barre de recherche
  static const double searchBarVerticalPadding = 12.0;

  // ==================== HELPER METHODS ====================

  /// Retourne un EdgeInsets avec padding uniforme
  static EdgeInsets all(double value) => EdgeInsets.all(value);

  /// Retourne un EdgeInsets avec padding horizontal
  static EdgeInsets horizontal(double value) =>
      EdgeInsets.symmetric(horizontal: value);

  /// Retourne un EdgeInsets avec padding vertical
  static EdgeInsets vertical(double value) =>
      EdgeInsets.symmetric(vertical: value);

  /// Retourne un EdgeInsets avec padding symétrique
  static EdgeInsets symmetric({double horizontal = 0, double vertical = 0}) =>
      EdgeInsets.symmetric(horizontal: horizontal, vertical: vertical);

  /// Retourne un EdgeInsets avec padding personnalisé
  static EdgeInsets only({
    double left = 0,
    double top = 0,
    double right = 0,
    double bottom = 0,
  }) => EdgeInsets.only(left: left, top: top, right: right, bottom: bottom);

  /// Padding standard pour les écrans
  static EdgeInsets get screenPaddingAll => all(screenPadding);

  /// Padding horizontal pour les écrans
  static EdgeInsets get screenPaddingHorizontal => horizontal(screenPadding);

  /// Padding standard pour les cartes
  static EdgeInsets get cardPaddingAll => all(cardPadding);

  /// Padding pour les boutons
  static EdgeInsets get buttonPaddingDefault =>
      symmetric(horizontal: buttonHorizontalPadding, vertical: buttonPadding);

  /// Padding pour les champs de saisie
  static EdgeInsets get inputPaddingDefault =>
      symmetric(horizontal: base, vertical: md);
}
