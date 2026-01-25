import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'app_colors.dart';
import 'app_typography.dart';
import 'app_spacing.dart';
import 'app_radius.dart';
import 'app_shadows.dart';

/// Thème principal de l'application ProSartisan
/// Implémente le design system avec thème sombre par défaut
class AppTheme {
  AppTheme._();

  // ==================== DARK THEME (Principal) ====================

  static ThemeData get darkTheme {
    return ThemeData(
      brightness: Brightness.dark,
      useMaterial3: true,

      // Couleurs principales
      scaffoldBackgroundColor: AppColors.primaryBg,
      primaryColor: AppColors.accentPrimary,
      cardColor: AppColors.cardBg,
      canvasColor: AppColors.secondaryBg,
      dividerColor: AppColors.overlayMedium,

      // ColorScheme pour Material 3
      colorScheme: const ColorScheme.dark(
        primary: AppColors.accentPrimary,
        secondary: AppColors.accentSuccess,
        surface: AppColors.cardBg,
        background: AppColors.primaryBg,
        error: AppColors.accentDanger,
        onPrimary: Colors.white,
        onSecondary: Colors.white,
        onSurface: AppColors.textPrimary,
        onBackground: AppColors.textPrimary,
        onError: Colors.white,
        tertiary: AppColors.accentWarning,
        outline: AppColors.overlayMedium,
        surfaceVariant: AppColors.elevatedBg,
        onSurfaceVariant: AppColors.textSecondary,
      ),

      // Typographie
      textTheme: TextTheme(
        displayLarge: AppTypography.h1.copyWith(color: AppColors.textPrimary),
        displayMedium: AppTypography.h2.copyWith(color: AppColors.textPrimary),
        displaySmall: AppTypography.h3.copyWith(color: AppColors.textPrimary),
        headlineLarge: AppTypography.h2.copyWith(color: AppColors.textPrimary),
        headlineMedium: AppTypography.h3.copyWith(color: AppColors.textPrimary),
        headlineSmall: AppTypography.h4.copyWith(color: AppColors.textPrimary),
        titleLarge: AppTypography.sectionTitle.copyWith(
          color: AppColors.textPrimary,
        ),
        titleMedium: AppTypography.body.copyWith(color: AppColors.textPrimary),
        titleSmall: AppTypography.bodySmall.copyWith(
          color: AppColors.textPrimary,
        ),
        bodyLarge: AppTypography.body.copyWith(color: AppColors.textPrimary),
        bodyMedium: AppTypography.bodySmall.copyWith(
          color: AppColors.textSecondary,
        ),
        bodySmall: AppTypography.caption.copyWith(
          color: AppColors.textTertiary,
        ),
        labelLarge: AppTypography.button.copyWith(color: AppColors.textPrimary),
        labelMedium: AppTypography.badge.copyWith(color: AppColors.textPrimary),
        labelSmall: AppTypography.tiny.copyWith(color: AppColors.textMuted),
      ),

      // AppBar
      appBarTheme: AppBarTheme(
        backgroundColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0,
        systemOverlayStyle: SystemUiOverlayStyle.light,
        titleTextStyle: AppTypography.h4.copyWith(color: AppColors.textPrimary),
        iconTheme: const IconThemeData(color: AppColors.textPrimary),
        actionsIconTheme: const IconThemeData(color: AppColors.textPrimary),
      ),

      // Boutons élevés
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.accentPrimary,
          foregroundColor: Colors.white,
          elevation: 4,
          shadowColor: Colors.black.withOpacity(0.3),
          padding: AppSpacing.buttonPaddingDefault,
          shape: RoundedRectangleBorder(borderRadius: AppRadius.buttonRadius),
          textStyle: AppTypography.button,
          minimumSize: Size(0, AppSpacing.buttonHeight),
        ),
      ),

      // Boutons de texte
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: AppColors.accentPrimary,
          padding: AppSpacing.buttonPaddingDefault,
          shape: RoundedRectangleBorder(borderRadius: AppRadius.buttonRadius),
          textStyle: AppTypography.button,
          minimumSize: Size(0, AppSpacing.buttonHeight),
        ),
      ),

      // Boutons outlined
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: AppColors.accentPrimary,
          side: const BorderSide(color: AppColors.accentPrimary),
          padding: AppSpacing.buttonPaddingDefault,
          shape: RoundedRectangleBorder(borderRadius: AppRadius.buttonRadius),
          textStyle: AppTypography.button,
          minimumSize: Size(0, AppSpacing.buttonHeight),
        ),
      ),

      // Boutons d'icône
      iconButtonTheme: IconButtonThemeData(
        style: IconButton.styleFrom(
          foregroundColor: AppColors.textPrimary,
          backgroundColor: AppColors.overlayLight,
          minimumSize: const Size(
            AppSpacing.minTouchTarget,
            AppSpacing.minTouchTarget,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: AppRadius.circular(AppSpacing.minTouchTarget / 2),
          ),
        ),
      ),

      // Champs de saisie
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.cardBg,
        contentPadding: AppSpacing.inputPaddingDefault,
        border: OutlineInputBorder(
          borderRadius: AppRadius.inputRadius,
          borderSide: BorderSide(color: AppColors.overlayMedium),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: AppRadius.inputRadius,
          borderSide: BorderSide(color: AppColors.overlayMedium),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: AppRadius.inputRadius,
          borderSide: const BorderSide(
            color: AppColors.accentPrimary,
            width: 2,
          ),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: AppRadius.inputRadius,
          borderSide: const BorderSide(color: AppColors.accentDanger),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: AppRadius.inputRadius,
          borderSide: const BorderSide(color: AppColors.accentDanger, width: 2),
        ),
        hintStyle: AppTypography.placeholder.copyWith(
          color: AppColors.textMuted,
        ),
        labelStyle: AppTypography.bodySmall.copyWith(
          color: AppColors.textSecondary,
        ),
        floatingLabelStyle: AppTypography.bodySmall.copyWith(
          color: AppColors.accentPrimary,
        ),
      ),

      // Cartes
      cardTheme: CardThemeData(
        color: AppColors.cardBg,
        elevation: 0,
        shadowColor: Colors.black.withOpacity(0.4),
        shape: RoundedRectangleBorder(borderRadius: AppRadius.cardRadius),
        margin: EdgeInsets.zero,
      ),

      // Dividers
      dividerTheme: const DividerThemeData(
        color: AppColors.overlayMedium,
        thickness: 1,
        space: 1,
      ),

      // Chips
      chipTheme: ChipThemeData(
        backgroundColor: AppColors.cardBg,
        selectedColor: AppColors.accentPrimary,
        disabledColor: AppColors.overlayLight,
        labelStyle: AppTypography.bodySmall.copyWith(
          color: AppColors.textPrimary,
        ),
        secondaryLabelStyle: AppTypography.bodySmall.copyWith(
          color: Colors.white,
        ),
        padding: AppSpacing.symmetric(
          horizontal: AppSpacing.md,
          vertical: AppSpacing.xs,
        ),
        shape: RoundedRectangleBorder(
          borderRadius: AppRadius.circular(AppRadius.navigationPill),
        ),
      ),

      // Bottom Navigation Bar
      bottomNavigationBarTheme: const BottomNavigationBarThemeData(
        backgroundColor: AppColors.cardBg,
        selectedItemColor: AppColors.accentPrimary,
        unselectedItemColor: AppColors.textMuted,
        type: BottomNavigationBarType.fixed,
        elevation: 0,
      ),

      // Navigation Bar (Material 3)
      navigationBarTheme: NavigationBarThemeData(
        backgroundColor: AppColors.cardBg,
        indicatorColor: AppColors.accentPrimary.withOpacity(0.2),
        labelTextStyle: MaterialStateProperty.resolveWith((states) {
          if (states.contains(MaterialState.selected)) {
            return AppTypography.navLabel.copyWith(
              color: AppColors.accentPrimary,
            );
          }
          return AppTypography.navLabel.copyWith(color: AppColors.textMuted);
        }),
        iconTheme: MaterialStateProperty.resolveWith((states) {
          if (states.contains(MaterialState.selected)) {
            return const IconThemeData(color: AppColors.accentPrimary);
          }
          return const IconThemeData(color: AppColors.textMuted);
        }),
      ),

      // Floating Action Button
      floatingActionButtonTheme: FloatingActionButtonThemeData(
        backgroundColor: AppColors.accentPrimary,
        foregroundColor: Colors.white,
        elevation: 8,
        shape: RoundedRectangleBorder(
          borderRadius: AppRadius.circular(AppSpacing.avatarSize / 2),
        ),
      ),

      // Dialog
      dialogTheme: DialogThemeData(
        backgroundColor: AppColors.cardBg,
        elevation: 8,
        shape: RoundedRectangleBorder(borderRadius: AppRadius.largeRadius),
        titleTextStyle: AppTypography.h4.copyWith(color: AppColors.textPrimary),
        contentTextStyle: AppTypography.body.copyWith(
          color: AppColors.textSecondary,
        ),
      ),

      // Bottom Sheet
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: AppColors.cardBg,
        elevation: 8,
        shape: RoundedRectangleBorder(borderRadius: AppRadius.modalRadius),
        modalBackgroundColor: AppColors.cardBg,
        modalElevation: 8,
      ),

      // Snack Bar
      snackBarTheme: SnackBarThemeData(
        backgroundColor: AppColors.elevatedBg,
        contentTextStyle: AppTypography.body.copyWith(
          color: AppColors.textPrimary,
        ),
        shape: RoundedRectangleBorder(borderRadius: AppRadius.mediumRadius),
        behavior: SnackBarBehavior.floating,
        elevation: 8,
      ),

      // Progress Indicator
      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: AppColors.accentPrimary,
        linearTrackColor: AppColors.overlayMedium,
        circularTrackColor: AppColors.overlayMedium,
      ),

      // Switch
      switchTheme: SwitchThemeData(
        thumbColor: MaterialStateProperty.resolveWith((states) {
          if (states.contains(MaterialState.selected)) {
            return AppColors.accentPrimary;
          }
          return AppColors.textMuted;
        }),
        trackColor: MaterialStateProperty.resolveWith((states) {
          if (states.contains(MaterialState.selected)) {
            return AppColors.accentPrimary.withOpacity(0.3);
          }
          return AppColors.overlayMedium;
        }),
      ),

      // Checkbox
      checkboxTheme: CheckboxThemeData(
        fillColor: MaterialStateProperty.resolveWith((states) {
          if (states.contains(MaterialState.selected)) {
            return AppColors.accentPrimary;
          }
          return Colors.transparent;
        }),
        checkColor: MaterialStateProperty.all(Colors.white),
        side: const BorderSide(color: AppColors.overlayMedium, width: 2),
        shape: RoundedRectangleBorder(borderRadius: AppRadius.circular(4)),
      ),

      // Radio
      radioTheme: RadioThemeData(
        fillColor: MaterialStateProperty.resolveWith((states) {
          if (states.contains(MaterialState.selected)) {
            return AppColors.accentPrimary;
          }
          return AppColors.overlayMedium;
        }),
      ),
    );
  }

  // ==================== LIGHT THEME ====================

  static ThemeData get lightTheme {
    return ThemeData(
      brightness: Brightness.light,
      useMaterial3: true,

      // Couleurs principales
      scaffoldBackgroundColor: AppColors.lightBg,
      primaryColor: AppColors.lightAccentPrimary,
      cardColor: AppColors.lightCard,
      canvasColor: AppColors.lightSecondaryBg,
      dividerColor: Colors.grey.shade300,

      // ColorScheme pour Material 3
      colorScheme: ColorScheme.light(
        primary: AppColors.lightAccentPrimary,
        secondary: AppColors.lightAccentSuccess,
        surface: AppColors.lightCard,
        background: AppColors.lightBg,
        error: AppColors.lightAccentDanger,
        onPrimary: Colors.white,
        onSecondary: Colors.white,
        onSurface: AppColors.lightTextPrimary,
        onBackground: AppColors.lightTextPrimary,
        onError: Colors.white,
        tertiary: AppColors.lightAccentWarning,
        outline: Colors.grey.shade300,
        surfaceVariant: Colors.grey.shade100,
        onSurfaceVariant: AppColors.lightTextSecondary,
      ),

      // Typographie (même structure que dark theme mais avec couleurs claires)
      textTheme: TextTheme(
        displayLarge: AppTypography.h1.copyWith(
          color: AppColors.lightTextPrimary,
        ),
        displayMedium: AppTypography.h2.copyWith(
          color: AppColors.lightTextPrimary,
        ),
        displaySmall: AppTypography.h3.copyWith(
          color: AppColors.lightTextPrimary,
        ),
        headlineLarge: AppTypography.h2.copyWith(
          color: AppColors.lightTextPrimary,
        ),
        headlineMedium: AppTypography.h3.copyWith(
          color: AppColors.lightTextPrimary,
        ),
        headlineSmall: AppTypography.h4.copyWith(
          color: AppColors.lightTextPrimary,
        ),
        titleLarge: AppTypography.sectionTitle.copyWith(
          color: AppColors.lightTextPrimary,
        ),
        titleMedium: AppTypography.body.copyWith(
          color: AppColors.lightTextPrimary,
        ),
        titleSmall: AppTypography.bodySmall.copyWith(
          color: AppColors.lightTextPrimary,
        ),
        bodyLarge: AppTypography.body.copyWith(
          color: AppColors.lightTextPrimary,
        ),
        bodyMedium: AppTypography.bodySmall.copyWith(
          color: AppColors.lightTextSecondary,
        ),
        bodySmall: AppTypography.caption.copyWith(
          color: AppColors.lightTextTertiary,
        ),
        labelLarge: AppTypography.button.copyWith(
          color: AppColors.lightTextPrimary,
        ),
        labelMedium: AppTypography.badge.copyWith(
          color: AppColors.lightTextPrimary,
        ),
        labelSmall: AppTypography.tiny.copyWith(
          color: AppColors.lightTextSecondary,
        ),
      ),

      // AppBar
      appBarTheme: AppBarTheme(
        backgroundColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0,
        systemOverlayStyle: SystemUiOverlayStyle.dark,
        titleTextStyle: AppTypography.h4.copyWith(
          color: AppColors.lightTextPrimary,
        ),
        iconTheme: IconThemeData(color: AppColors.lightTextPrimary),
        actionsIconTheme: IconThemeData(color: AppColors.lightTextPrimary),
      ),

      // Boutons (adaptés pour le thème clair)
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.lightAccentPrimary,
          foregroundColor: Colors.white,
          elevation: 2,
          shadowColor: Colors.black.withOpacity(0.1),
          padding: AppSpacing.buttonPaddingDefault,
          shape: RoundedRectangleBorder(borderRadius: AppRadius.buttonRadius),
          textStyle: AppTypography.button,
          minimumSize: Size(0, AppSpacing.buttonHeight),
        ),
      ),

      // Autres composants adaptés pour le thème clair...
      // (Structure similaire au thème sombre mais avec les couleurs claires)
    );
  }

  // ==================== HELPER METHODS ====================

  /// Retourne le thème approprié selon le mode
  static ThemeData getTheme({bool isDark = true}) {
    return isDark ? darkTheme : lightTheme;
  }

  /// Configuration système pour la barre de statut
  static SystemUiOverlayStyle get systemUiOverlayStyle =>
      const SystemUiOverlayStyle(
        statusBarColor: Colors.transparent,
        statusBarIconBrightness: Brightness.light,
        statusBarBrightness: Brightness.dark,
        systemNavigationBarColor: AppColors.cardBg,
        systemNavigationBarIconBrightness: Brightness.light,
      );
}
