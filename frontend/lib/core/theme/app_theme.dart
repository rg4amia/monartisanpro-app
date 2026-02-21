import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'app_colors.dart';
import 'app_shadows.dart';
import 'app_typography.dart';
import '../constants/spacing.dart';

/// ProsArtisan theme configuration
class AppTheme {
  AppTheme._();

  // Light theme
  static ThemeData get lightTheme {
    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.light,

      // Color scheme
      colorScheme: const ColorScheme.light(
        primary: AppColors.lightAccentPrimary,
        secondary: AppColors.lightAccentSecondary,
        surface: AppColors.lightCard,
        background: AppColors.lightBackground,
        error: AppColors.error,
        onPrimary: Colors.white,
        onSecondary: Colors.white,
        onSurface: AppColors.lightTextPrimary,
        onBackground: AppColors.lightTextPrimary,
        onError: Colors.white,
      ),

      // Scaffold
      scaffoldBackgroundColor: AppColors.lightBackground,

      // Text theme — maps AppTypography onto Material 3 roles
      textTheme: const TextTheme(
        displayLarge:  TextStyle(fontSize: AppTypography.h1Size, fontWeight: AppTypography.bold,     height: AppTypography.tightLineHeight),
        displayMedium: TextStyle(fontSize: AppTypography.h2Size, fontWeight: AppTypography.semibold, height: AppTypography.tightLineHeight),
        titleLarge:    TextStyle(fontSize: AppTypography.h3Size, fontWeight: AppTypography.semibold, height: AppTypography.normalLineHeight),
        titleMedium:   TextStyle(fontSize: AppTypography.h4Size, fontWeight: AppTypography.semibold, height: AppTypography.normalLineHeight),
        bodyLarge:     TextStyle(fontSize: AppTypography.bodySize,      fontWeight: AppTypography.regular, height: AppTypography.normalLineHeight),
        bodyMedium:    TextStyle(fontSize: AppTypography.bodySmallSize,  fontWeight: AppTypography.regular, height: AppTypography.normalLineHeight),
        bodySmall:     TextStyle(fontSize: AppTypography.captionSize,   fontWeight: AppTypography.regular, height: AppTypography.normalLineHeight),
        labelLarge:    TextStyle(fontSize: AppTypography.bodySize,      fontWeight: AppTypography.semibold),
        labelMedium:   TextStyle(fontSize: AppTypography.bodySmallSize,  fontWeight: AppTypography.medium),
        labelSmall:    TextStyle(fontSize: AppTypography.tinySize,      fontWeight: AppTypography.medium),
      ),

      // App bar
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.transparent,
        elevation: 0,
        centerTitle: true,
        systemOverlayStyle: SystemUiOverlayStyle.dark,
        iconTheme: IconThemeData(color: AppColors.lightTextPrimary),
        titleTextStyle: TextStyle(
          color: AppColors.lightTextPrimary,
          fontSize: AppTypography.h3Size,
          fontWeight: AppTypography.semibold,
        ),
      ),

      // Card — shadow from AppShadows.lightMd (0 4px 6px rgba(0,0,0,0.07))
      cardTheme: CardThemeData(
        color: AppColors.lightCard,
        elevation: 2,
        shadowColor: AppShadows.lightMd.first.color,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(Spacing.cardRadius),
        ),
      ),

      // Elevated button
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.lightAccentPrimary,
          foregroundColor: Colors.white,
          elevation: 2,
          padding: const EdgeInsets.symmetric(
            horizontal: Spacing.xl,
            vertical: Spacing.base,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(Spacing.buttonRadius),
          ),
          textStyle: AppTypography.buttonText(Colors.white),
        ),
      ),

      // Outlined button
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: AppColors.lightAccentPrimary,
          side: const BorderSide(color: AppColors.lightAccentPrimary),
          padding: const EdgeInsets.symmetric(
            horizontal: Spacing.xl,
            vertical: Spacing.base,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(Spacing.buttonRadius),
          ),
          textStyle: AppTypography.buttonText(AppColors.lightAccentPrimary),
        ),
      ),

      // Text button
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: AppColors.lightAccentPrimary,
          textStyle: AppTypography.buttonText(AppColors.lightAccentPrimary),
        ),
      ),

      // Input decoration
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.lightCard,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(Spacing.radiusMd),
          borderSide: const BorderSide(color: AppColors.lightTextTertiary),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(Spacing.radiusMd),
          borderSide: const BorderSide(color: AppColors.lightTextTertiary),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(Spacing.radiusMd),
          borderSide: const BorderSide(color: AppColors.lightAccentPrimary, width: 2),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(Spacing.radiusMd),
          borderSide: const BorderSide(color: AppColors.error),
        ),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: Spacing.base,
          vertical: Spacing.md,
        ),
        hintStyle: AppTypography.bodyLight(AppColors.lightTextTertiary),
      ),

      // Bottom navigation bar
      bottomNavigationBarTheme: const BottomNavigationBarThemeData(
        backgroundColor: AppColors.lightCard,
        selectedItemColor: AppColors.lightAccentPrimary,
        unselectedItemColor: AppColors.lightTextTertiary,
        type: BottomNavigationBarType.fixed,
        elevation: 8,
      ),

      // Floating action button
      floatingActionButtonTheme: const FloatingActionButtonThemeData(
        backgroundColor: AppColors.lightAccentPrimary,
        foregroundColor: Colors.white,
        elevation: 4,
      ),

      // Tab bar — accent indicator, transparent divider
      tabBarTheme: const TabBarThemeData(
        labelColor: AppColors.lightAccentPrimary,
        unselectedLabelColor: AppColors.lightTextSecondary,
        indicatorColor: AppColors.lightAccentPrimary,
        dividerColor: Colors.transparent,
      ),

      // Chip — pill shape, accent when selected
      chipTheme: ChipThemeData(
        backgroundColor: AppColors.lightBackgroundSecondary,
        selectedColor: AppColors.lightAccentPrimary,
        labelStyle: const TextStyle(
          fontSize: AppTypography.bodySmallSize,
          fontWeight: AppTypography.regular,
        ),
        padding: const EdgeInsets.symmetric(
          horizontal: Spacing.base,
          vertical: Spacing.xs,
        ),
        shape: const StadiumBorder(),
      ),

      // Snack bar — floating, rounded, dark background
      snackBarTheme: SnackBarThemeData(
        backgroundColor: AppColors.lightTextPrimary,
        contentTextStyle: const TextStyle(
          fontSize: AppTypography.bodySmallSize,
          fontWeight: AppTypography.regular,
          color: Colors.white,
        ),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(Spacing.radiusMd),
        ),
        behavior: SnackBarBehavior.floating,
      ),

      // Dialog — rounded 20dp corners, no surface tint
      dialogTheme: DialogThemeData(
        backgroundColor: AppColors.lightCard,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(Spacing.radiusXl),
        ),
      ),
    );
  }

  // Dark theme
  static ThemeData get darkTheme {
    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,

      // Color scheme
      colorScheme: const ColorScheme.dark(
        primary: AppColors.darkAccentPrimary,
        secondary: AppColors.darkAccentSecondary,
        surface: AppColors.darkCard,
        background: AppColors.darkBackground,
        error: AppColors.darkAccentDanger,
        onPrimary: Colors.white,
        onSecondary: Colors.white,
        onSurface: AppColors.darkTextPrimary,
        onBackground: AppColors.darkTextPrimary,
        onError: Colors.white,
      ),

      // Scaffold
      scaffoldBackgroundColor: AppColors.darkBackground,

      // Text theme — same scale as light, colors come from colorScheme
      textTheme: const TextTheme(
        displayLarge:  TextStyle(fontSize: AppTypography.h1Size, fontWeight: AppTypography.bold,     height: AppTypography.tightLineHeight),
        displayMedium: TextStyle(fontSize: AppTypography.h2Size, fontWeight: AppTypography.semibold, height: AppTypography.tightLineHeight),
        titleLarge:    TextStyle(fontSize: AppTypography.h3Size, fontWeight: AppTypography.semibold, height: AppTypography.normalLineHeight),
        titleMedium:   TextStyle(fontSize: AppTypography.h4Size, fontWeight: AppTypography.semibold, height: AppTypography.normalLineHeight),
        bodyLarge:     TextStyle(fontSize: AppTypography.bodySize,      fontWeight: AppTypography.regular, height: AppTypography.normalLineHeight),
        bodyMedium:    TextStyle(fontSize: AppTypography.bodySmallSize,  fontWeight: AppTypography.regular, height: AppTypography.normalLineHeight),
        bodySmall:     TextStyle(fontSize: AppTypography.captionSize,   fontWeight: AppTypography.regular, height: AppTypography.normalLineHeight),
        labelLarge:    TextStyle(fontSize: AppTypography.bodySize,      fontWeight: AppTypography.semibold),
        labelMedium:   TextStyle(fontSize: AppTypography.bodySmallSize,  fontWeight: AppTypography.medium),
        labelSmall:    TextStyle(fontSize: AppTypography.tinySize,      fontWeight: AppTypography.medium),
      ),

      // App bar
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.transparent,
        elevation: 0,
        centerTitle: true,
        systemOverlayStyle: SystemUiOverlayStyle.light,
        iconTheme: IconThemeData(color: AppColors.darkTextPrimary),
        titleTextStyle: TextStyle(
          color: AppColors.darkTextPrimary,
          fontSize: AppTypography.h3Size,
          fontWeight: AppTypography.semibold,
        ),
      ),

      // Card — shadow from AppShadows.darkMd (0 4px 6px rgba(0,0,0,0.40))
      cardTheme: CardThemeData(
        color: AppColors.darkCard,
        elevation: 4,
        shadowColor: AppShadows.darkMd.first.color,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(Spacing.cardRadius),
        ),
      ),

      // Elevated button
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: AppColors.darkAccentPrimary,
          foregroundColor: Colors.white,
          elevation: 4,
          padding: const EdgeInsets.symmetric(
            horizontal: Spacing.xl,
            vertical: Spacing.base,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(Spacing.buttonRadius),
          ),
          textStyle: AppTypography.buttonText(Colors.white),
        ),
      ),

      // Outlined button
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: AppColors.darkAccentPrimary,
          side: const BorderSide(color: AppColors.darkAccentPrimary),
          padding: const EdgeInsets.symmetric(
            horizontal: Spacing.xl,
            vertical: Spacing.base,
          ),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(Spacing.buttonRadius),
          ),
          textStyle: AppTypography.buttonText(AppColors.darkAccentPrimary),
        ),
      ),

      // Text button
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: AppColors.darkAccentPrimary,
          textStyle: AppTypography.buttonText(AppColors.darkAccentPrimary),
        ),
      ),

      // Input decoration
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.darkCard,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(Spacing.radiusMd),
          borderSide: BorderSide(color: AppColors.overlayMedium),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(Spacing.radiusMd),
          borderSide: BorderSide(color: AppColors.overlayMedium),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(Spacing.radiusMd),
          borderSide: const BorderSide(color: AppColors.darkAccentPrimary, width: 2),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(Spacing.radiusMd),
          borderSide: const BorderSide(color: AppColors.darkAccentDanger),
        ),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: Spacing.base,
          vertical: Spacing.md,
        ),
        hintStyle: AppTypography.bodyLight(AppColors.darkTextTertiary),
      ),

      // Bottom navigation bar
      bottomNavigationBarTheme: const BottomNavigationBarThemeData(
        backgroundColor: AppColors.darkCard,
        selectedItemColor: AppColors.darkAccentPrimary,
        unselectedItemColor: AppColors.darkTextTertiary,
        type: BottomNavigationBarType.fixed,
        elevation: 8,
      ),

      // Floating action button
      floatingActionButtonTheme: const FloatingActionButtonThemeData(
        backgroundColor: AppColors.darkAccentPrimary,
        foregroundColor: Colors.white,
        elevation: 6,
      ),

      // Tab bar
      tabBarTheme: const TabBarThemeData(
        labelColor: AppColors.darkAccentPrimary,
        unselectedLabelColor: AppColors.darkTextSecondary,
        indicatorColor: AppColors.darkAccentPrimary,
        dividerColor: Colors.transparent,
      ),

      // Chip
      chipTheme: ChipThemeData(
        backgroundColor: AppColors.darkElevated,
        selectedColor: AppColors.darkAccentPrimary,
        labelStyle: const TextStyle(
          fontSize: AppTypography.bodySmallSize,
          fontWeight: AppTypography.regular,
        ),
        padding: const EdgeInsets.symmetric(
          horizontal: Spacing.base,
          vertical: Spacing.xs,
        ),
        shape: const StadiumBorder(),
      ),

      // Snack bar
      snackBarTheme: SnackBarThemeData(
        backgroundColor: AppColors.darkElevated,
        contentTextStyle: const TextStyle(
          fontSize: AppTypography.bodySmallSize,
          fontWeight: AppTypography.regular,
          color: AppColors.darkTextPrimary,
        ),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(Spacing.radiusMd),
        ),
        behavior: SnackBarBehavior.floating,
      ),

      // Dialog
      dialogTheme: DialogThemeData(
        backgroundColor: AppColors.darkCard,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(Spacing.radiusXl),
        ),
      ),
    );
  }
}
