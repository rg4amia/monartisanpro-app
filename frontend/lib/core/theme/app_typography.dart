import 'package:flutter/material.dart';

/// ProsArtisan typography system based on design system
class AppTypography {
  AppTypography._();

  // Font weights
  static const FontWeight regular = FontWeight.w400;
  static const FontWeight medium = FontWeight.w500;
  static const FontWeight semibold = FontWeight.w600;
  static const FontWeight bold = FontWeight.w700;

  // Font sizes
  static const double h1Size = 28.0;
  static const double h2Size = 24.0;
  static const double h3Size = 20.0;
  static const double h4Size = 18.0;
  static const double bodySize = 16.0;
  static const double bodySmallSize = 14.0;
  static const double captionSize = 12.0;
  static const double tinySize = 10.0;

  // Line heights
  static const double tightLineHeight = 1.2;
  static const double normalLineHeight = 1.5;
  static const double relaxedLineHeight = 1.75;

  // Text styles for light theme
  static TextStyle h1Light(Color color) => TextStyle(
        fontSize: h1Size,
        fontWeight: bold,
        height: tightLineHeight,
        color: color,
      );

  static TextStyle h2Light(Color color) => TextStyle(
        fontSize: h2Size,
        fontWeight: semibold,
        height: tightLineHeight,
        color: color,
      );

  static TextStyle h3Light(Color color) => TextStyle(
        fontSize: h3Size,
        fontWeight: semibold,
        height: normalLineHeight,
        color: color,
      );

  static TextStyle h4Light(Color color) => TextStyle(
        fontSize: h4Size,
        fontWeight: medium,
        height: normalLineHeight,
        color: color,
      );

  static TextStyle bodyLight(Color color) => TextStyle(
        fontSize: bodySize,
        fontWeight: regular,
        height: normalLineHeight,
        color: color,
      );

  static TextStyle bodySmallLight(Color color) => TextStyle(
        fontSize: bodySmallSize,
        fontWeight: regular,
        height: normalLineHeight,
        color: color,
      );

  static TextStyle captionLight(Color color) => TextStyle(
        fontSize: captionSize,
        fontWeight: regular,
        height: normalLineHeight,
        color: color,
      );

  static TextStyle buttonText(Color color) => TextStyle(
        fontSize: bodySize,
        fontWeight: semibold,
        color: color,
      );

  static TextStyle labelText(Color color) => TextStyle(
        fontSize: bodySmallSize,
        fontWeight: medium,
        color: color,
      );
}
