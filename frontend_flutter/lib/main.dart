import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:get_storage/get_storage.dart';
import 'package:intl/date_symbol_data_local.dart';
import 'package:yandex_maps_mapkit/init.dart' as mapkit_init;
import 'package:onesignal_flutter/onesignal_flutter.dart';
import 'package:get/get.dart';

import 'app/app.dart';
import 'core/utils/error_handler.dart';
import 'core/network/sync_service.dart';
import 'core/config/env_config.dart';
import 'data/services/app_settings_service.dart';

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

      // Initialisation locale française pour formatage dates
      await initializeDateFormatting('fr_FR', null);

      // GetStorage (user prefs — role, nom, kyc_status…)
      await GetStorage.init();

      // ── Découverte automatique du serveur API ──────────────────────────────
      // Teste production → émulateur → scan réseau local (port 8000)
      await EnvConfig.init();

      // Yandex MapKit — clé API (surchargeable via --dart-define=YANDEX_MAPKIT_API_KEY)
      await mapkit_init.initMapkit(
        apiKey: EnvConfig.yandexMapKitApiKey,
        locale: 'fr_FR',
      );

      // OneSignal — Initialisation (Push Notifications)
      // App ID surchargeable via --dart-define=ONESIGNAL_APP_ID
      OneSignal.Debug.setLogLevel(
        kReleaseMode ? OSLogLevel.none : OSLogLevel.verbose,
      );
      OneSignal.initialize(EnvConfig.oneSignalAppId);
      OneSignal.Notifications.requestPermission(true);

      // Initialisation de la synchro hors-ligne
      await Get.putAsync(() => SyncService().init());
      
      Get.put(AppSettingsService());

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
