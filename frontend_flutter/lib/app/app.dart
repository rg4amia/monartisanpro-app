import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../core/storage/storage_service.dart';
import '../core/theme/app_theme.dart';
import 'routes/app_pages.dart';
import 'routes/app_routes.dart';

class App extends StatefulWidget {
  const App({super.key});

  @override
  State<App> createState() => _AppState();
}

class _AppState extends State<App> {
  String _initialRoute = Routes.login;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _determineInitialRoute();
  }

  Future<void> _determineInitialRoute() async {
    final token = await StorageService.getToken();
    final isOnboarded = StorageService.isOnboarded();

    setState(() {
      if (token != null && token.isNotEmpty && isOnboarded) {
        // L'utilisateur a un token valide et a complété l'onboarding
        _initialRoute = Routes.mainTab;
      } else {
        // L'utilisateur doit se connecter
        _initialRoute = Routes.login;
      }
      _isLoading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return MaterialApp(
        debugShowCheckedModeBanner: false,
        home: Scaffold(
          backgroundColor: Colors.white,
          body: Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Image.asset(
                  'assets/logo/logos.png',
                  width: 180,
                  height: 180,
                ),
                const SizedBox(height: 24),
                const CircularProgressIndicator(
                  valueColor: AlwaysStoppedAnimation<Color>(Color(0xFF5B5FEF)),
                ),
              ],
            ),
          ),
        ),
      );
    }

    return GetMaterialApp(
      title: 'ProsArtisan',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      initialRoute: _initialRoute,
      getPages: AppPages.pages,
      defaultTransition: Transition.cupertino,
      locale: const Locale('fr', 'FR'),
      fallbackLocale: const Locale('fr', 'FR'),
    );
  }
}
