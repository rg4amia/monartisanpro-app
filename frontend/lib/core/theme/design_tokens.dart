import 'package:flutter/material.dart';

/// MinimalAuthMobile Design System Tokens
/// Based on design_login.json specifications
class DesignTokens {
  DesignTokens._();

  // ============================================================================
  // COLOR SYSTEM
  // ============================================================================

  // Primary Colors
  static const Color primaryBase = Color(0xFF1F6FD6);
  static const Color primaryHover = Color(0xFF1A5FC0);
  static const Color primaryPressed = Color(0xFF174FA8);
  static const Color textOnPrimary = Color(0xFFFFFFFF);

  // Background Colors
  static const Color backgroundScreen = Color(0xFFF5F6F8);
  static const Color backgroundCard = Color(0xFFFFFFFF);

  // Neutral Colors
  static const Color neutral900 = Color(0xFF111111);
  static const Color neutral700 = Color(0xFF444444);
  static const Color neutral500 = Color(0xFF888888);
  static const Color neutral300 = Color(0xFFDADADA);
  static const Color neutral200 = Color(0xFFEAEAEA);
  static const Color neutral100 = Color(0xFFF2F2F2);

  // Semantic Colors
  static const Color error = Color(0xFFE5484D);
  static const Color success = Color(0xFF2FB344);
  static const Color warning = Color(0xFFF5A524);
  static const Color info = Color(0xFF3B82F6);

  // ============================================================================
  // TYPOGRAPHY
  // ============================================================================

  static const String fontFamily = 'Inter'; // Fallback to system default

  // Font Sizes
  static const double displaySize = 28.0;
  static const double headingSize = 22.0;
  static const double subheadingSize = 16.0;
  static const double bodySize = 15.0;
  static const double buttonSize = 16.0;
  static const double captionSize = 13.0;

  // Font Weights
  static const FontWeight displayWeight = FontWeight.w700;
  static const FontWeight headingWeight = FontWeight.w600;
  static const FontWeight subheadingWeight = FontWeight.w400;
  static const FontWeight bodyWeight = FontWeight.w400;
  static const FontWeight buttonWeight = FontWeight.w600;
  static const FontWeight captionWeight = FontWeight.w400;
  static const FontWeight linkWeight = FontWeight.w500;

  // Line Heights
  static const double displayLineHeight = 1.2;
  static const double headingLineHeight = 1.3;
  static const double subheadingLineHeight = 1.5;
  static const double bodyLineHeight = 1.5;
  static const double buttonLineHeight = 1.2;
  static const double captionLineHeight = 1.4;

  // Text Styles
  static TextStyle displayStyle({Color? color}) => TextStyle(
    fontFamily: fontFamily,
    fontSize: displaySize,
    fontWeight: displayWeight,
    height: displayLineHeight,
    color: color ?? neutral900,
  );

  static TextStyle headingStyle({Color? color}) => TextStyle(
    fontFamily: fontFamily,
    fontSize: headingSize,
    fontWeight: headingWeight,
    height: headingLineHeight,
    color: color ?? neutral900,
  );

  static TextStyle subheadingStyle({Color? color}) => TextStyle(
    fontFamily: fontFamily,
    fontSize: subheadingSize,
    fontWeight: subheadingWeight,
    height: subheadingLineHeight,
    color: color ?? neutral700,
  );

  static TextStyle bodyStyle({Color? color}) => TextStyle(
    fontFamily: fontFamily,
    fontSize: bodySize,
    fontWeight: bodyWeight,
    height: bodyLineHeight,
    color: color ?? neutral900,
  );

  static TextStyle buttonStyle({Color? color}) => TextStyle(
    fontFamily: fontFamily,
    fontSize: buttonSize,
    fontWeight: buttonWeight,
    height: buttonLineHeight,
    color: color ?? textOnPrimary,
  );

  static TextStyle captionStyle({Color? color}) => TextStyle(
    fontFamily: fontFamily,
    fontSize: captionSize,
    fontWeight: captionWeight,
    height: captionLineHeight,
    color: color ?? neutral500,
  );

  static TextStyle linkStyle({Color? color}) => TextStyle(
    fontFamily: fontFamily,
    fontSize: bodySize,
    fontWeight: linkWeight,
    color: color ?? primaryBase,
    decoration: TextDecoration.none,
  );

  // ============================================================================
  // SPACING SYSTEM
  // ============================================================================

  static const double baseUnit = 8.0;

  // Spacing Scale
  static const double spacing4 = 4.0;
  static const double spacing8 = 8.0;
  static const double spacing12 = 12.0;
  static const double spacing16 = 16.0;
  static const double spacing24 = 24.0;
  static const double spacing32 = 32.0;
  static const double spacing40 = 40.0;
  static const double spacing48 = 48.0;

  // Layout Spacing
  static const double horizontalPadding = 24.0;

  // ============================================================================
  // BORDER RADIUS
  // ============================================================================

  static const double radiusInput = 12.0;
  static const double radiusButton = 12.0;
  static const double radiusPill = 999.0;

  // ============================================================================
  // COMPONENT DIMENSIONS
  // ============================================================================

  static const double inputHeight = 52.0;
  static const double socialButtonHeight = 52.0;
  static const double primaryButtonHeight = 56.0;
  static const double logoSize = 64.0;
  static const double minimumTouchTarget = 44.0;

  // ============================================================================
  // ANIMATION
  // ============================================================================

  static const Duration durationFast = Duration(milliseconds: 150);
  static const Duration durationStandard = Duration(milliseconds: 250);
  static const Curve easing = Curves.easeInOut;
  static const double buttonPressScale = 0.98;

  // ============================================================================
  // SHADOWS
  // ============================================================================

  static List<BoxShadow> get subtleShadow => [
    BoxShadow(
      color: neutral900.withValues(alpha: 0.04),
      blurRadius: 8,
      offset: const Offset(0, 2),
    ),
  ];

  static List<BoxShadow> get cardShadow => [
    BoxShadow(
      color: neutral900.withValues(alpha: 0.06),
      blurRadius: 12,
      offset: const Offset(0, 4),
    ),
  ];
}
