import 'package:flutter/material.dart';

class AppColors {
  // Brand
  static const Color primary = Color(0xFF1A2C5B);
  static const Color primaryLight = Color(0xFF2A3F7A);
  static const Color primaryDark = Color(0xFF0F1A3A);
  static const Color secondary = Color(0xFFE9EEF8);
  static const Color secondaryDark = Color(0xFFD6DFF0);

  // Role accents
  static const Color accent = Color(0xFFE67E22); // artisan orange
  static const Color accentLight = Color(0xFFF39C12);
  static const Color success = Color(0xFF27AE60); // fournisseur green
  static const Color successLight = Color(0xFF2ECC71);
  static const Color client = Color(0xFF2F6FED);
  static const Color clientSoft = Color(0xFFEAF1FF);
  static const Color artisanSoft = Color(0xFFFFF1E4);
  static const Color supplierSoft = Color(0xFFEAF8F0);
  static const Color gold = Color(0xFFD4A017);
  static const Color driver = Color(0xFFF1C40F); // livreur yellow
  static const Color driverSoft = Color(0xFFFEF9E7);

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

  /// Couleur du Score ProsArtisan sur l'échelle 0–1000
  /// (vert à partir du seuil « marqueur doré » / micro-crédit = 700).
  static Color scoreColor(int score) {
    if (score < 400) return scoreRed;
    if (score < 700) return scoreOrange;
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

  // Trade Specific Colors (Côte d'Ivoire Palette)
  static const Color tradePlumbing = Color(0xFF0284C7); // Bleu Azur
  static const Color tradeElectricity = Color(0xFFD97706); // Ambre Énergie
  static const Color tradeHVAC = Color(0xFF06B6D4); // Cyan Froid
  static const Color tradeMasonry = Color(0xFFB45309); // Terracotta BTP
  static const Color tradePainting = Color(0xFF059669); // Émeraude Finition
  static const Color tradeCarpentry = Color(0xFF854D0E); // Chêne / Bois
  static const Color tradeWelding = Color(0xFF4F46E5); // Indigo Métal
  static const Color tradeAppliance = Color(0xFF7C3AED); // Violet Électro

  // Trade Gradients
  static const LinearGradient gradientPlumbing = LinearGradient(
    colors: [Color(0xFF38BDF8), Color(0xFF0284C7)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  static const LinearGradient gradientElectricity = LinearGradient(
    colors: [Color(0xFFFBBF24), Color(0xFFD97706)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  static const LinearGradient gradientHVAC = LinearGradient(
    colors: [Color(0xFF22D3EE), Color(0xFF0891B2)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  static const LinearGradient gradientMasonry = LinearGradient(
    colors: [Color(0xFFF59E0B), Color(0xFFB45309)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  static const LinearGradient gradientPainting = LinearGradient(
    colors: [Color(0xFF34D399), Color(0xFF059669)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  static const LinearGradient gradientCarpentry = LinearGradient(
    colors: [Color(0xFFD97706), Color(0xFF78350F)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  static const LinearGradient gradientWelding = LinearGradient(
    colors: [Color(0xFF818CF8), Color(0xFF4338CA)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  static const LinearGradient gradientGold = LinearGradient(
    colors: [Color(0xFFFDE68A), Color(0xFFD4A017), Color(0xFFB45309)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  static const LinearGradient gradientCockpit = LinearGradient(
    colors: [Color(0xFF1A2C5B), Color(0xFF0F1A3A)],
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
  );

  // Modern Card Shadow
  static List<BoxShadow> get cardShadow => [
        BoxShadow(
          color: primary.withValues(alpha: 0.05),
          blurRadius: 16,
          offset: const Offset(0, 4),
        ),
        BoxShadow(
          color: primary.withValues(alpha: 0.03),
          blurRadius: 4,
          offset: const Offset(0, 1),
        ),
      ];

  static List<BoxShadow> get goldGlow => [
        BoxShadow(
          color: const Color(0xFFD4A017).withValues(alpha: 0.35),
          blurRadius: 12,
          spreadRadius: 1,
          offset: const Offset(0, 3),
        ),
      ];
}
