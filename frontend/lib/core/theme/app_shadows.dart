import 'package:flutter/material.dart';

/// Shadow tokens from design system (shadows section)
///
/// Hex opacity encoding: rgba(0,0,0,alpha) → 0xAArrggbb
///   0.05 → 0x0D | 0.07 → 0x12 | 0.10 → 0x1A | 0.15 → 0x26
///   0.30 → 0x4D | 0.40 → 0x66 | 0.50 → 0x80 | 0.60 → 0x99
class AppShadows {
  AppShadows._();

  // ── Light mode ──────────────────────────────────────────────────────────────
  /// 0 1px 2px rgba(0,0,0,0.05)
  static const lightSm = [
    BoxShadow(color: Color(0x0D000000), blurRadius: 2, offset: Offset(0, 1)),
  ];

  /// 0 4px 6px rgba(0,0,0,0.07)
  static const lightMd = [
    BoxShadow(color: Color(0x12000000), blurRadius: 6, offset: Offset(0, 4)),
  ];

  /// 0 10px 15px rgba(0,0,0,0.10)
  static const lightLg = [
    BoxShadow(color: Color(0x1A000000), blurRadius: 15, offset: Offset(0, 10)),
  ];

  /// 0 20px 25px rgba(0,0,0,0.15)
  static const lightXl = [
    BoxShadow(color: Color(0x26000000), blurRadius: 25, offset: Offset(0, 20)),
  ];

  // ── Dark mode ───────────────────────────────────────────────────────────────
  /// 0 1px 2px rgba(0,0,0,0.30)
  static const darkSm = [
    BoxShadow(color: Color(0x4D000000), blurRadius: 2, offset: Offset(0, 1)),
  ];

  /// 0 4px 6px rgba(0,0,0,0.40)
  static const darkMd = [
    BoxShadow(color: Color(0x66000000), blurRadius: 6, offset: Offset(0, 4)),
  ];

  /// 0 10px 15px rgba(0,0,0,0.50)
  static const darkLg = [
    BoxShadow(color: Color(0x80000000), blurRadius: 15, offset: Offset(0, 10)),
  ];

  /// 0 20px 25px rgba(0,0,0,0.60)
  static const darkXl = [
    BoxShadow(color: Color(0x99000000), blurRadius: 25, offset: Offset(0, 20)),
  ];

  /// Accent glow: 0 0 20px rgba(91,127,255,0.30)
  static const darkGlow = [
    BoxShadow(color: Color(0x4D5B7FFF), blurRadius: 20),
  ];
}
