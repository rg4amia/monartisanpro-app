import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../services/language_preference_service.dart';
import '../services/localization_service.dart';

class LanguageController extends GetxController {
  final Rx<Locale> _currentLocale = LocalizationService.defaultLocale.obs;
  final RxString _currentLanguageCode = 'fr'.obs;

  Locale get currentLocale => _currentLocale.value;
  String get currentLanguageCode => _currentLanguageCode.value;

  @override
  void onInit() {
    super.onInit();
    _loadSavedLanguage();
  }

  /// Load saved language preference
  Future<void> _loadSavedLanguage() async {
    try {
      final savedLanguage =
          await LanguagePreferenceService.getLanguagePreference();
      await changeLanguage(savedLanguage);
    } catch (e) {
      // If loading fails, use default language
      _currentLanguageCode.value = 'fr';
      _currentLocale.value = LocalizationService.defaultLocale;
    }
  }

  /// Change the app language
  Future<void> changeLanguage(String languageCode) async {
    if (!LocalizationService.isLocaleSupported(languageCode)) {
      throw ArgumentError('Unsupported language: $languageCode');
    }

    try {
      // Save preference
      await LanguagePreferenceService.setLanguagePreference(languageCode);

      // Update current values
      _currentLanguageCode.value = languageCode;
      final newLocale = LocalizationService.getLocaleFromCode(languageCode);
      if (newLocale != null) {
        _currentLocale.value = newLocale;

        // Update GetX locale
        Get.updateLocale(newLocale);
      }
    } catch (e) {
      // Handle error - maybe show a snackbar
      Get.snackbar(
        'Error',
        'Failed to change language: $e',
        snackPosition: SnackPosition.BOTTOM,
      );
    }
  }

  /// Toggle between French and English
  Future<void> toggleLanguage() async {
    final newLanguage = _currentLanguageCode.value == 'fr' ? 'en' : 'fr';
    await changeLanguage(newLanguage);
  }

  /// Get available languages
  Map<String, String> getAvailableLanguages() {
    return LanguagePreferenceService.getAvailableLanguages();
  }

  /// Get language display name
  String getLanguageDisplayName(String languageCode) {
    return LanguagePreferenceService.getLanguageDisplayName(
      languageCode,
      inLanguage: _currentLanguageCode.value,
    );
  }

  /// Check if current language is French
  bool get isFrench => _currentLanguageCode.value == 'fr';

  /// Check if current language is English
  bool get isEnglish => _currentLanguageCode.value == 'en';

  /// Format currency according to current locale
  String formatCurrency(double amount) {
    return LocalizationService.formatCurrency(
      amount,
      locale: _currentLanguageCode.value,
    );
  }

  /// Format date according to current locale
  String formatDate(DateTime date) {
    return LocalizationService.formatDate(
      date,
      locale: _currentLanguageCode.value,
    );
  }

  /// Format datetime according to current locale
  String formatDateTime(DateTime date) {
    return LocalizationService.formatDateTime(
      date,
      locale: _currentLanguageCode.value,
    );
  }

  /// Format relative time according to current locale
  String formatRelativeTime(DateTime date) {
    return LocalizationService.formatRelativeTime(
      date,
      locale: _currentLanguageCode.value,
    );
  }

  /// Parse date according to current locale
  DateTime? parseDate(String dateString) {
    return LocalizationService.parseDate(
      dateString,
      locale: _currentLanguageCode.value,
    );
  }

  /// Parse datetime according to current locale
  DateTime? parseDateTime(String dateTimeString) {
    return LocalizationService.parseDateTime(
      dateTimeString,
      locale: _currentLanguageCode.value,
    );
  }
}
