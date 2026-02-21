import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:yandex_maps_mapkit/init.dart' as mapkit_init;
import 'core/theme/app_theme.dart';
import 'core/constants/app_constants.dart';
import 'core/init/app_bindings.dart';
import 'features/auth/presentation/screens/splash_screen.dart';

void main() async {
  // Ensure Flutter is initialized
  WidgetsFlutterBinding.ensureInitialized();

  // Initialize Yandex MapKit with API key
  await mapkit_init.initMapkit(apiKey: 'e8411c6c-7c2d-414b-9cb0-029fc7d5a71d');

  // Initialize dependency injection
  AppBindings().dependencies();

  runApp(const ProsArtisanApp());
}

class ProsArtisanApp extends StatelessWidget {
  const ProsArtisanApp({super.key});

  @override
  Widget build(BuildContext context) {
    return GetMaterialApp(
      title: AppConstants.appName,
      debugShowCheckedModeBanner: false,
      theme: AppTheme.lightTheme,
      darkTheme: AppTheme.darkTheme,
      themeMode: ThemeMode.system,
      initialBinding: AppBindings(),
      home: const SplashScreen(), // Changed from OnboardingScreen
      defaultTransition: Transition.cupertino,
      transitionDuration: const Duration(milliseconds: 300),
    );
  }
}
