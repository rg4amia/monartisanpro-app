import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:get/get.dart';
import 'package:sentry_flutter/sentry_flutter.dart';
import 'core/routes/app_pages.dart';
import 'core/routes/app_routes.dart';
import 'core/constants/app_strings.dart';
import 'core/controllers/language_controller.dart';
import 'core/services/localization_service.dart';
import 'core/services/api/api_client.dart';
import 'core/services/api/api_service.dart';
import 'core/services/sync/sync_service.dart';
import 'core/services/storage/offline_repository.dart';
import 'core/services/monitoring/sentry_service.dart';
import 'shared/data/repositories/reference_data_repository.dart';
import 'shared/controllers/trade_controller.dart';
import 'core/theme/app_theme.dart';
import 'features/auth/presentation/bindings/auth_binding.dart';
import 'generated/l10n/app_localizations.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Initialize Sentry
  await SentryService.initialize();

  // Initialize core services
  _initializeCoreServices();

  // Run app with Sentry error handling
  runApp(const ProSartisanApp());
}

void _initializeCoreServices() {
  // Initialize language controller
  Get.put(LanguageController());

  // Initialize core API services
  Get.put<ApiClient>(ApiClient(), permanent: true);
  Get.put<ApiService>(ApiService(Get.find<ApiClient>()), permanent: true);

  // Initialize other core services that are used across the app
  Get.put<SyncService>(SyncService(), permanent: true);
  Get.put<OfflineRepository>(OfflineRepository(), permanent: true);
  Get.put<ReferenceDataRepository>(ReferenceDataRepository(), permanent: true);

  // Initialize trade controller for reference data
  Get.put<TradeController>(TradeController(), permanent: true);
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

        theme: AppTheme.darkTheme, // Use dark theme by default
        darkTheme: AppTheme.darkTheme,
        themeMode: ThemeMode.dark, // Force le thème sombre
        initialBinding: AuthBinding(),
        initialRoute: AppRoutes.splash,
        getPages: AppPages.routes,
      ),
    );
  }
}
