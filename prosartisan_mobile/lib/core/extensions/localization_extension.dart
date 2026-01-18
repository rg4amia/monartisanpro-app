import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../controllers/language_controller.dart';
import '../../generated/l10n/app_localizations.dart';

extension LocalizationExtension on BuildContext {
  /// Get AppLocalizations instance
  AppLocalizations get l10n => AppLocalizations.of(this);

  /// Get LanguageController instance
  LanguageController get languageController => Get.find<LanguageController>();
}

extension CurrencyExtension on double {
  /// Format as currency using current locale
  String get asCurrency {
    final controller = Get.find<LanguageController>();
    return controller.formatCurrency(this);
  }
}

extension DateTimeExtension on DateTime {
  /// Format as date using current locale
  String get asDate {
    final controller = Get.find<LanguageController>();
    return controller.formatDate(this);
  }

  /// Format as time using current locale
  String get asTime {
    final controller = Get.find<LanguageController>();
    return controller.formatDateTime(this);
  }

  /// Format as relative time using current locale
  String get asRelativeTime {
    final controller = Get.find<LanguageController>();
    return controller.formatRelativeTime(this);
  }
}

extension StringExtension on String {
  /// Parse as currency
  double get asCurrencyValue {
    final controller = Get.find<LanguageController>();
    return controller.parseDate(this)?.millisecondsSinceEpoch.toDouble() ?? 0.0;
  }

  /// Parse as date using current locale
  DateTime? get asDate {
    final controller = Get.find<LanguageController>();
    return controller.parseDate(this);
  }

  /// Parse as datetime using current locale
  DateTime? get asDateTime {
    final controller = Get.find<LanguageController>();
    return controller.parseDateTime(this);
  }
}
