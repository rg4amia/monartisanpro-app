import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/app/routes/app_routes.dart';
import 'package:frontend_flutter/core/storage/storage_service.dart';
import 'package:frontend_flutter/modules/onboarding/views/splash_screen.dart';
import 'package:get/get.dart';

import '../../helpers/test_helpers.dart';

/// Écran de démarrage : il décide seul de l'écran suivant (audit du
/// 07/10/2026, anomalie 12 bis — module sans test).
void main() {
  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
  });

  tearDown(() async {
    Get.reset();
    await TestHelpers.cleanupTestData();
  });

  GetPage<dynamic> stub(String name) => GetPage(
        name: name,
        page: () => Scaffold(body: Text('écran $name')),
      );

  Future<void> open(WidgetTester tester) async {
    await tester.pumpWidget(
      GetMaterialApp(
        initialRoute: Routes.splash,
        getPages: [
          GetPage(name: Routes.splash, page: () => const SplashScreen()),
          stub(Routes.onboarding),
          stub(Routes.login),
          stub(Routes.mainTab),
        ],
      ),
    );
    // L'écran attend deux secondes avant de décider.
    await tester.pump(const Duration(seconds: 2));
    await tester.pumpAndSettle();
  }

  testWidgets('une première ouverture mène à la présentation', (tester) async {
    await open(tester);

    expect(Get.currentRoute, Routes.onboarding);
  });

  testWidgets('sans session, on arrive à la connexion', (tester) async {
    StorageService.setOnboarded(true);

    await open(tester);

    expect(Get.currentRoute, Routes.login);
  });

  testWidgets('avec une session, on arrive dans l\'application',
      (tester) async {
    StorageService.setOnboarded(true);
    await tester.runAsync(() => StorageService.saveToken('jeton'));

    await open(tester);

    expect(Get.currentRoute, Routes.mainTab);
  });
}
