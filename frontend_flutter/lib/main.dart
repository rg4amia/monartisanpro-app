import 'package:flutter/material.dart';
import 'package:get_storage/get_storage.dart';
import 'package:yandex_maps_mapkit/init.dart' as mapkit_init;

import 'app/app.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // GetStorage (user prefs — role, nom, kyc_status…)
  await GetStorage.init();

  // Yandex MapKit — clé API
  await mapkit_init.initMapkit(
    apiKey: 'e8411c6c-7c2d-414b-9cb0-029fc7d5a71d',
  );

  runApp(const App());
}
