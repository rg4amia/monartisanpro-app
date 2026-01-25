import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:get/get.dart';
import 'core/routes/app_pages.dart';
import 'core/routes/app_routes.dart';
import 'core/constants/app_strings.dart';
import 'core/controllers/language_controller.dart';
import 'core/services/localization_service.dart';
import 'core/theme/app_theme.dart';
import 'features/auth/presentation/bindings/auth_binding.dart';
import 'generated/l10n/app_localizations.dart';

void main() {
  // Initialize language controller
  Get.put(LanguageController());

  runApp(const ProSartisanApp());
}

class ProSartisanApp extends StatelessWidget {
  const ProSartisanApp({super.key});

  @override
  Widget build(BuildContext context) {
    final languageController = Get.find<LanguageController>();

    return Obx(
      () => GetMaterialApp(
        title: AppStrings.appName,
        debugShowCheckedModeBanner: false,

        // Localization configuration
        locale: languageController.currentLocale,
        fallbackLocale: LocalizationService.fallbackLocale,
        supportedLocales: LocalizationService.supportedLocales,
        localizationsDelegates: const [
          AppLocalizations.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],

        theme: AppTheme.darkTheme, // Utilise le thème sombre par défaut
        darkTheme: AppTheme.darkTheme,
        themeMode: ThemeMode.dark, // Force le thème sombre
        initialBinding: AuthBinding(),
        initialRoute: AppRoutes.splash,
        getPages: AppPages.routes,
      ),
    );
  }
}
