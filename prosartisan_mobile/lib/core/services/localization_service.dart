import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

class LocalizationService {
  static const String _defaultLocale = 'fr';
  static const String _fallbackLocale = 'en';

  static final Map<String, Locale> _supportedLocales = {
    'fr': const Locale('fr', 'CI'),
    'en': const Locale('en', 'US'),
  };

  static Locale get defaultLocale => _supportedLocales[_defaultLocale]!;
  static Locale get fallbackLocale => _supportedLocales[_fallbackLocale]!;

  static List<Locale> get supportedLocales => _supportedLocales.values.toList();

  /// Format currency amount in XOF with proper thousand separators
  /// French: "1 000 000 FCFA"
  /// English: "1,000,000 XOF"
  static String formatCurrency(double amount, {String? locale}) {
    final currentLocale = locale ?? _defaultLocale;

    if (currentLocale == 'fr') {
      final formatter = NumberFormat('#,##0', 'fr_FR');
      final formattedAmount = formatter.format(amount).replaceAll(',', ' ');
      return '$formattedAmount FCFA';
    } else {
      final formatter = NumberFormat('#,##0', 'en_US');
      final formattedAmount = formatter.format(amount);
      return '$formattedAmount XOF';
    }
  }

  /// Parse currency string back to double
  static double parseCurrency(String formattedAmount) {
    // Remove currency symbols and spaces
    String cleanAmount = formattedAmount
        .replaceAll('FCFA', '')
        .replaceAll('XOF', '')
        .replaceAll(' ', '')
        .replaceAll(',', '')
        .trim();

    return double.tryParse(cleanAmount) ?? 0.0;
  }

  /// Format date according to locale
  /// French: "25/12/2024"
  /// English: "12/25/2024"
  static String formatDate(DateTime date, {String? locale}) {
    final currentLocale = locale ?? _defaultLocale;

    if (currentLocale == 'fr') {
      return DateFormat('dd/MM/yyyy').format(date);
    } else {
      return DateFormat('MM/dd/yyyy').format(date);
    }
  }

  /// Format time in 24-hour format
  /// Both locales: "14:30"
  static String formatTime(DateTime date) {
    return DateFormat('HH:mm').format(date);
  }

  /// Format date and time according to locale
  /// French: "25/12/2024 14:30"
  /// English: "12/25/2024 14:30"
  static String formatDateTime(DateTime date, {String? locale}) {
    final currentLocale = locale ?? _defaultLocale;

    if (currentLocale == 'fr') {
      return DateFormat('dd/MM/yyyy HH:mm').format(date);
    } else {
      return DateFormat('MM/dd/yyyy HH:mm').format(date);
    }
  }

  /// Parse date string according to locale
  static DateTime? parseDate(String dateString, {String? locale}) {
    final currentLocale = locale ?? _defaultLocale;

    try {
      if (currentLocale == 'fr') {
        return DateFormat('dd/MM/yyyy').parse(dateString);
      } else {
        return DateFormat('MM/dd/yyyy').parse(dateString);
      }
    } catch (e) {
      return null;
    }
  }

  /// Parse datetime string according to locale
  static DateTime? parseDateTime(String dateTimeString, {String? locale}) {
    final currentLocale = locale ?? _defaultLocale;

    try {
      if (currentLocale == 'fr') {
        return DateFormat('dd/MM/yyyy HH:mm').parse(dateTimeString);
      } else {
        return DateFormat('MM/dd/yyyy HH:mm').parse(dateTimeString);
      }
    } catch (e) {
      return null;
    }
  }

  /// Format relative time
  /// French: "il y a 2 heures"
  /// English: "2 hours ago"
  static String formatRelativeTime(DateTime date, {String? locale}) {
    final currentLocale = locale ?? _defaultLocale;
    final now = DateTime.now();
    final difference = now.difference(date);

    if (currentLocale == 'fr') {
      return _formatRelativeTimeFrench(difference);
    } else {
      return _formatRelativeTimeEnglish(difference);
    }
  }

  static String _formatRelativeTimeFrench(Duration difference) {
    if (difference.inDays > 0) {
      return difference.inDays == 1
          ? 'il y a 1 jour'
          : 'il y a ${difference.inDays} jours';
    }

    if (difference.inHours > 0) {
      return difference.inHours == 1
          ? 'il y a 1 heure'
          : 'il y a ${difference.inHours} heures';
    }

    if (difference.inMinutes > 0) {
      return difference.inMinutes == 1
          ? 'il y a 1 minute'
          : 'il y a ${difference.inMinutes} minutes';
    }

    return 'à l\'instant';
  }

  static String _formatRelativeTimeEnglish(Duration difference) {
    if (difference.inDays > 0) {
      return difference.inDays == 1
          ? '1 day ago'
          : '${difference.inDays} days ago';
    }

    if (difference.inHours > 0) {
      return difference.inHours == 1
          ? '1 hour ago'
          : '${difference.inHours} hours ago';
    }

    if (difference.inMinutes > 0) {
      return difference.inMinutes == 1
          ? '1 minute ago'
          : '${difference.inMinutes} minutes ago';
    }

    return 'just now';
  }

  /// Format distance
  static String formatDistance(double distanceKm, {String? locale}) {
    final formatter = NumberFormat('#0.0');
    return '${formatter.format(distanceKm)} km';
  }

  /// Format rating
  static String formatRating(double rating) {
    final formatter = NumberFormat('#0.0');
    return '${formatter.format(rating)}/5';
  }

  /// Get month names for the given locale
  static List<String> getMonthNames({String? locale}) {
    final currentLocale = locale ?? _defaultLocale;

    if (currentLocale == 'fr') {
      return [
        'Janvier',
        'Février',
        'Mars',
        'Avril',
        'Mai',
        'Juin',
        'Juillet',
        'Août',
        'Septembre',
        'Octobre',
        'Novembre',
        'Décembre',
      ];
    } else {
      return [
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December',
      ];
    }
  }

  /// Get day names for the given locale
  static List<String> getDayNames({String? locale}) {
    final currentLocale = locale ?? _defaultLocale;

    if (currentLocale == 'fr') {
      return [
        'Dimanche',
        'Lundi',
        'Mardi',
        'Mercredi',
        'Jeudi',
        'Vendredi',
        'Samedi',
      ];
    } else {
      return [
        'Sunday',
        'Monday',
        'Tuesday',
        'Wednesday',
        'Thursday',
        'Friday',
        'Saturday',
      ];
    }
  }

  /// Check if locale is supported
  static bool isLocaleSupported(String locale) {
    return _supportedLocales.containsKey(locale);
  }

  /// Get locale from language code
  static Locale? getLocaleFromCode(String languageCode) {
    return _supportedLocales[languageCode];
  }
}
