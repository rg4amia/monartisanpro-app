import 'dart:async';
import 'dart:ui';

import 'package:flutter/material.dart';
import 'package:get_storage/get_storage.dart';
import 'package:yandex_maps_mapkit/init.dart' as mapkit_init;

import 'app/app.dart';
import 'core/utils/error_handler.dart';

Future<void> main() async {
  await runZonedGuarded(
    () async {
      WidgetsFlutterBinding.ensureInitialized();

      // Erreurs framework Flutter.
      FlutterError.onError = (FlutterErrorDetails details) {
        FlutterError.presentError(details);
        unawaited(
          ErrorHandler.handle(
            details.exception,
            stackTrace: details.stack,
            context: 'Flutter Framework Error',
            showSnackbar: false,
          ),
        );
      };

      // Erreurs async hors framework Flutter.
      PlatformDispatcher.instance.onError = (error, stack) {
        unawaited(
          ErrorHandler.handle(
            error,
            stackTrace: stack,
            context: 'Platform Dispatcher Error',
            showSnackbar: false,
          ),
        );
        return true;
      };

      // GetStorage (user prefs — role, nom, kyc_status…)
      await GetStorage.init();

      // Yandex MapKit — clé API
      await mapkit_init.initMapkit(
        apiKey: 'e8411c6c-7c2d-414b-9cb0-029fc7d5a71d',
        locale: 'fr_FR',
      );

      runApp(const App());
    },
    (error, stack) {
      unawaited(
        ErrorHandler.handle(
          error,
          stackTrace: stack,
          context: 'runZonedGuarded Error',
          showSnackbar: false,
        ),
      );
    },
  );
}
