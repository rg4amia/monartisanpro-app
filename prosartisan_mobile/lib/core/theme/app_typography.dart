import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

/// Système de typographie pour l'application ProSartisan
/// Utilise SF Pro Display comme police principale avec fallback
class AppTypography {
  AppTypography._();

  // ==================== FONT FAMILY ====================
  
  static const String fontFamily = 'SF Pro Display';
  
  // Utilisation de Google Fonts pour SF Pro Display avec fallback
  static TextStyle get _baseTextStyle => GoogleFonts.inter(
    // Inter est une excellente alternative à SF Pro Display
    fontWeight: FontWeight.normal,
  );

  // ==================== TEXT STYLES ====================
  
  /// Titre principal (28px, bold)
  static TextStyle get h1 => _baseTextStyle.copyWith(
    fontSize: 28,
    fontWeight: FontWeight.bold,
    height: 1.2,
  );

  /// Titre secondaire (24px, bold)
  static TextStyle get h2 => _baseTextStyle.copyWith(
    fontSize: 24,
    fontWeight: FontWeight.bold,
    height: 1.2,
  );

  /// Titre tertiaire (20px, semibold)
  static TextStyle get h3 => _baseTextStyle.copyWith(
    fontSize: 20,
    fontWeight: FontWeight.w600,
    height: 1.3,
  );

  /// Titre quaternaire (18px, semibold)
  static TextStyle get h4 => _baseTextStyle.copyWith(
    fontSize: 18,
    fontWeight: FontWeight.w600,
    height: 1.3,
  );

  /// Corps de texte principal (16px, normal)
  static TextStyle get body => _baseTextStyle.copyWith(
    fontSize: 16,
    fontWeight: FontWeight.normal,
    height: 1.5,
  );

  /// Corps de texte petit (14px, normal)
  static TextStyle get bodySmall => _baseTextStyle.copyWith(
    fontSize: 14,
    fontWeight: FontWeight.normal,
    height: 1.5,
  );

  /// Texte de légende (12px, normal)
  static TextStyle get caption => _baseTextStyle.copyWith(
    fontSize: 12,
    fontWeight: FontWeight.normal,
    height: 1.4,
  );

  /// Texte très petit (10px, normal)
  static TextStyle get tiny => _baseTextStyle.copyWith(
    fontSize: 10,
    fontWeight: FontWeight.normal,
    height: 1.4,
  );

  // ==================== SPECIALIZED STYLES ====================
  
  /// Style pour les boutons (16px, semibold)
  static TextStyle get button => _baseTextStyle.copyWith(
    fontSize: 16,
    fontWeight: FontWeight.w600,
    height: 1.2,
  );

  /// Style pour les badges (12px, semibold)
  static TextStyle get badge => _baseTextStyle.copyWith(
    fontSize: 12,
    fontWeight: FontWeight.w600,
    height: 1.2,
  );

  /// Style pour les prix (16px, bold)
  static TextStyle get price => _baseTextStyle.copyWith(
    fontSize: 16,
    fontWeight: FontWeight.bold,
    height: 1.2,
  );

  /// Style pour les prix importants (20px, bold)
  static TextStyle get priceLarge => _baseTextStyle.copyWith(
    fontSize: 20,
    fontWeight: FontWeight.bold,
    height: 1.2,
  );

  /// Style pour les ratings (14px, medium)
  static TextStyle get rating => _baseTextStyle.copyWith(
    fontSize: 14,
    fontWeight: FontWeight.w500,
    height: 1.3,
  );

  /// Style pour les labels de navigation (12px, medium)
  static TextStyle get navLabel => _baseTextStyle.copyWith(
    fontSize: 12,
    fontWeight: FontWeight.w500,
    height: 1.2,
  );

  /// Style pour les titres de section (18px, semibold)
  static TextStyle get sectionTitle => _baseTextStyle.copyWith(
    fontSize: 18,
    fontWeight: FontWeight.w600,
    height: 1.3,
  );

  /// Style pour les placeholders (16px, normal)
  static TextStyle get placeholder => _baseTextStyle.copyWith(
    fontSize: 16,
    fontWeight: FontWeight.normal,
    height: 1.5,
  );

  /// Style pour les numéros de carte (16px, monospace)
  static TextStyle get cardNumber => GoogleFonts.robotoMono(
    fontSize: 16,
    fontWeight: FontWeight.w500,
    height: 1.3,
  );

  // ==================== WEIGHT CONSTANTS ====================
  
  static const FontWeight regular = FontWeight.w400;
  static const FontWeight medium = FontWeight.w500;
  static const FontWeight semibold = FontWeight.w600;
  static const FontWeight bold = FontWeight.w700;

  // ==================== LINE HEIGHT CONSTANTS ====================
  
  static const double tightLineHeight = 1.2;
  static const double normalLineHeight = 1.5;
  static const double relaxedLineHeight = 1.75;

  // ==================== HELPER METHODS ====================
  
  /// Applique une couleur à un style de texte
  static TextStyle withColor(TextStyle style, Color color) {
    return style.copyWith(color: color);
  }

  /// Applique un poids à un style de texte
  static TextStyle withWeight(TextStyle style, FontWeight weight) {
    return style.copyWith(fontWeight: weight);
  }

  /// Applique une taille à un style de texte
  static TextStyle withSize(TextStyle style, double size) {
    return style.copyWith(fontSize: size);
  }
}