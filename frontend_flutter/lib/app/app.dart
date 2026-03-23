import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:yandex_maps_mapkit/mapkit_factory.dart';

import '../../../core/theme/app_theme.dart';
import 'routes/app_pages.dart';
import 'routes/app_routes.dart';

class App extends StatefulWidget {
  const App({super.key});

  @override
  State<App> createState() => _AppState();
}

class _AppState extends State<App> with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    mapkit.onStart();
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    mapkit.onStop();
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    switch (state) {
      case AppLifecycleState.resumed:
        mapkit.onStart();
        break;
      case AppLifecycleState.inactive:
      case AppLifecycleState.hidden:
      case AppLifecycleState.paused:
        mapkit.onStop();
        break;
      case AppLifecycleState.detached:
        mapkit.onStop();
        mapkit.onTerminate();
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    return GetMaterialApp(
      title: 'ProsArtisan',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      initialRoute: Routes.splash,
      getPages: AppPages.pages,
      defaultTransition: Transition.cupertino,
      locale: const Locale('fr', 'FR'),
      fallbackLocale: const Locale('fr', 'FR'),
    );
  }
}
