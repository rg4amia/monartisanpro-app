import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'localization_service.dart';

class LanguagePreferenceService {
  static const String _languageKey = 'selected_language';
  static const String _defaultLanguage = 'fr';

  /// Get the saved language preference
  static Future<String> getLanguagePreference() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(_languageKey) ?? _defaultLanguage;
  }

  /// Save language preference
  static Future<void> setLanguagePreference(String languageCode) async {
    if (!LocalizationService.isLocaleSupported(languageCode)) {
      throw ArgumentError('Unsupported language code: $languageCode');
    }

    final prefs = await SharedPreferences.getInstance();
    await prefs.setString(_languageKey, languageCode);
  }

  /// Get the current locale based on saved preference
  static Future<Locale> getCurrentLocale() async {
    final languageCode = await getLanguagePreference();
    return LocalizationService.getLocaleFromCode(languageCode) ??
        LocalizationService.defaultLocale;
  }

  /// Clear language preference (revert to default)
  static Future<void> clearLanguagePreference() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove(_languageKey);
  }

  /// Get available languages with their display names
  static Map<String, String> getAvailableLanguages() {
    return {'fr': 'Français', 'en': 'English'};
  }

  /// Get language display name
  static String getLanguageDisplayName(
    String languageCode, {
    String? inLanguage,
  }) {
    final languages = {
      'fr': {'fr': 'Français', 'en': 'French'},
      'en': {'fr': 'Anglais', 'en': 'English'},
    };

    final displayLanguage = inLanguage ?? languageCode;
    return languages[languageCode]?[displayLanguage] ?? languageCode;
  }

  /// Check if the app should use system locale
  static Future<bool> shouldUseSystemLocale() async {
    final prefs = await SharedPreferences.getInstance();
    return !prefs.containsKey(_languageKey);
  }

  /// Get system locale if supported, otherwise return default
  static Locale getSystemLocaleOrDefault() {
    // This would typically get the system locale
    // For now, we'll return the default French locale
    return LocalizationService.defaultLocale;
  }
}
