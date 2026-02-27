import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../core/storage/storage_service.dart';
import '../core/theme/app_theme.dart';
import 'routes/app_pages.dart';
import 'routes/app_routes.dart';

class App extends StatelessWidget {
  const App({super.key});

  @override
  Widget build(BuildContext context) {
    final String initialRoute = StorageService.isOnboarded()
        ? Routes.mainTab
        : Routes.login;

    return GetMaterialApp(
      title: 'ProsArtisan',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      initialRoute: initialRoute,
      getPages: AppPages.pages,
      defaultTransition: Transition.cupertino,
      locale: const Locale('fr', 'FR'),
      fallbackLocale: const Locale('fr', 'FR'),
    );
  }
}
