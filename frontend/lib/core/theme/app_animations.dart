import 'package:flutter/material.dart';

/// Animation constants from design system (interactions.transitions section)
class AppAnimations {
  AppAnimations._();

  // ── Durées ──────────────────────────────────────────────────────────────────
  /// Micro-interactions (150ms)
  static const Duration fast = Duration(milliseconds: 150);

  /// Modal slide-up / standard transitions (250ms)
  static const Duration standard = Duration(milliseconds: 250);

  /// Page slide left/right (300ms)
  static const Duration page = Duration(milliseconds: 300);

  /// Theme switch color transition (200ms)
  static const Duration theme = Duration(milliseconds: 200);

  // ── Courbes ─────────────────────────────────────────────────────────────────
  static const Curve easing  = Curves.easeInOut;
  static const Curve easeOut = Curves.easeOut;

  // ── Scales (feedback tactile) ───────────────────────────────────────────────
  /// Button press: scale 0.98 + opacity 0.8
  static const double buttonPressScale = 0.98;

  /// Card tap hover: scale 1.02 + shadow increase
  static const double cardHoverScale = 1.02;
}
