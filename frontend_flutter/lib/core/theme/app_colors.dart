import 'package:flutter/material.dart';

class AppColors {
  // Brand
  static const Color primary = Color(0xFF1A2C5B);
  static const Color primaryLight = Color(0xFF2A3F7A);
  static const Color primaryDark = Color(0xFF0F1A3A);

  // Role accents
  static const Color accent = Color(0xFFE67E22);       // artisan orange
  static const Color accentLight = Color(0xFFF39C12);
  static const Color success = Color(0xFF27AE60);      // fournisseur green
  static const Color successLight = Color(0xFF2ECC71);

  // States
  static const Color danger = Color(0xFFE74C3C);
  static const Color warning = Color(0xFFF39C12);
  static const Color info = Color(0xFF3498DB);
  static const Color infoLight = Color(0xFFD6EAF8);

  // Backgrounds
  static const Color background = Color(0xFFF5F6FA);
  static const Color surface = Color(0xFFFFFFFF);
  static const Color card = Color(0xFFFFFFFF);
  static const Color border = Color(0xFFE8EAF0);

  // Text
  static const Color textPrimary = Color(0xFF1A2C5B);
  static const Color textSecondary = Color(0xFF6B7280);
  static const Color textMuted = Color(0xFF9CA3AF);
  static const Color textLight = Color(0xFFFFFFFF);

  // Overlay
  static const Color overlay = Color(0x991A2C5B);
  static const Color shadowColor = Color(0x141A2C5B);

  // Payment providers
  static const Color wave = Color(0xFF1DC5C8);
  static const Color orangeMoney = Color(0xFFFF6600);

  // Score colors
  static const Color scoreRed = Color(0xFFE74C3C);
  static const Color scoreOrange = Color(0xFFE67E22);
  static const Color scoreGreen = Color(0xFF27AE60);

  static Color scoreColor(int score) {
    if (score < 40) return scoreRed;
    if (score < 70) return scoreOrange;
    return scoreGreen;
  }

  // Mission status colors
  static const Map<String, Color> missionStatusColors = {
    'en_attente': Color(0xFF6B7280),
    'financee': Color(0xFF3498DB),
    'en_cours': Color(0xFFE67E22),
    'terminee': Color(0xFF27AE60),
    'litige': Color(0xFFE74C3C),
  };

  static Color missionStatus(String status) =>
      missionStatusColors[status] ?? textSecondary;
}
