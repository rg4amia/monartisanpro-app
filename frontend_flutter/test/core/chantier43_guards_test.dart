import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/app/routes/app_pages.dart';
import 'package:frontend_flutter/app/routes/session_guard.dart';
import 'package:frontend_flutter/core/network/sync_service.dart';
import 'package:frontend_flutter/core/utils/external_maps.dart';
import 'package:frontend_flutter/core/utils/json_readers.dart';

/// Gardes du Chantier 43 : elles relisent les sources et la configuration,
/// que les tests de comportement ne voient pas.
void main() {
  Iterable<File> dartSources(String directory) => Directory(directory)
      .listSync(recursive: true)
      .whereType<File>()
      .where((f) => f.path.endsWith('.dart') && !f.path.endsWith('.g.dart'));

  String allOf(String directory) =>
      dartSources(directory).map((f) => f.readAsStringSync()).join('\n');

  group('manifeste Android', () {
    final manifest =
        File('android/app/src/main/AndroidManifest.xml').readAsStringSync();

    test('aucune sauvegarde des données de l\'application', () {
      expect(manifest, contains('android:allowBackup="false"'));
      expect(manifest, contains('android:fullBackupContent="false"'));
      expect(manifest, contains('@xml/data_extraction_rules'));

      final rules = File(
        'android/app/src/main/res/xml/data_extraction_rules.xml',
      ).readAsStringSync();
      expect(rules, contains('<cloud-backup>'));
      expect(rules, contains('<device-transfer>'));
    });

    test('le lien profond ne vise que le retour de paiement', () {
      expect(
        manifest,
        contains(
          '<data android:scheme="prosartisan" android:host="payment-result" />',
        ),
      );
      // Flutter n'ouvre aucun écran d'après l'adresse reçue.
      expect(
        manifest,
        contains(
          '<meta-data android:name="flutter_deeplinking_enabled" android:value="false"/>',
        ),
      );
    });

    test('le stockage externe n\'est plus demandé à partir d\'Android 13', () {
      expect(
        manifest,
        contains(
          'android.permission.READ_EXTERNAL_STORAGE" android:maxSdkVersion="32"',
        ),
      );
    });
  });

  test('iOS : le micro est annoncé, et tout est en français', () {
    final plist = File('ios/Runner/Info.plist').readAsStringSync();

    expect(plist, contains('NSMicrophoneUsageDescription'));
    expect(plist, isNot(contains('We need')));
  });

  group('routes', () {
    test('toute route hors des écrans publics porte la garde de session', () {
      for (final page in AppPages.pages) {
        final guarded =
            page.middlewares?.any((m) => m is SessionGuard) ?? false;

        expect(
          guarded,
          !SessionGuard.publicRoutes.contains(page.name),
          reason: page.name,
        );
      }
    });

    test('sans compte connecté, la garde renvoie à la connexion', () {
      expect(
        SessionGuard(hasSession: () => false).redirect('/wallet')?.name,
        '/login',
      );
      expect(SessionGuard(hasSession: () => true).redirect('/wallet'), isNull);
    });
  });

  group('cartes et itinéraires', () {
    final lib = allOf('lib');

    test('aucun lien Google Maps (Règle d\'or 44)', () {
      expect(lib, isNot(contains('google.com/maps')));
      expect(lib, isNot(contains('maps.google')));
    });

    test('aucun serveur d\'itinéraire appelé depuis le téléphone', () {
      expect(lib, isNot(contains('project-osrm')));
      expect(lib, isNot(contains('OSRM_BASE_URL')));
    });

    test('aucun point tiré au hasard dans le planificateur de tournée', () {
      final planner = File(
        'lib/modules/home/views/delivery_route_planner_screen.dart',
      ).readAsStringSync();

      expect(planner, isNot(contains('Random(')));
    });

    test('un point s\'ouvre par un lien neutre, puis par Yandex', () async {
      final tried = <Uri>[];

      final opened = await openInMaps(
        5.3484,
        -4.0267,
        label: 'Quincaillerie Centrale',
        launcher: (uri) async {
          tried.add(uri);
          return uri.scheme == 'https';
        },
      );

      expect(opened, isTrue);
      expect(tried.first.scheme, 'geo');
      expect(tried.first.toString(), startsWith('geo:5.3484,-4.0267?q='));
      expect(tried.last.host, 'yandex.com');
      expect(tried.last.queryParameters['rtext'], '~5.3484,-4.0267');
    });

    test('sans application de cartes, rien ne s\'ouvre et on le sait',
        () async {
      final opened = await openInMaps(
        5.3,
        -4.0,
        launcher: (uri) async => throw StateError('aucune application'),
      );

      expect(opened, isFalse);
    });
  });

  group('code retiré', () {
    final lib = allOf('lib');

    test('plus de journal vers Telegram ni d\'aide de débogage', () {
      expect(lib.toLowerCase(), isNot(contains('api.telegram.org')));
      expect(lib, isNot(contains('TELEGRAM_BOT_TOKEN')));
      expect(File('lib/core/utils/debug_helper.dart').existsSync(), isFalse);
    });

    test('la connexion envoie le rôle « livreur », jamais « driver »', () {
      final login =
          File('lib/modules/auth/views/login_screen.dart').readAsStringSync();

      expect(login, isNot(contains("role.value = 'driver'")));
      expect(login, contains("role.value = 'livreur'"));
    });

    test('aucun message technique dans les services et les réglages', () {
      for (final path in [
        'lib/modules/services/controllers/services_controller.dart',
        'lib/modules/settings/controllers/settings_controller.dart',
      ]) {
        final source = File(path).readAsStringSync();

        expect(source, isNot(contains(r": $e'")), reason: path);
        expect(source, isNot(contains('e.toString()')), reason: path);
      }
    });

    test('le récapitulatif de commande n\'appelle plus l\'API lui-même', () {
      final screen = File(
        'lib/modules/orders/views/order_checkout_screen.dart',
      ).readAsStringSync();

      expect(screen, isNot(contains('ApiClient(')));
    });
  });

  group('lecture des réponses de l\'API (Règle d\'or 28)', () {
    test('aucun transtypage direct dans les dépôts et les modèles', () {
      final direct = RegExp(
        r'''(res|response)\.data\s+as\s+(Map|List)|\[['"]\w+['"]\]\s+as\s+(String|int|double|bool|num|Map|List)\b''',
      );
      final offenders = <String>[];

      for (final file in dartSources('lib/data')) {
        final lines = file.readAsLinesSync();
        for (var i = 0; i < lines.length; i++) {
          if (direct.hasMatch(lines[i])) {
            offenders.add('${file.path}:${i + 1}');
          }
        }
      }

      // Seule exception : un chemin de fichier local, que l'application
      // écrit elle-même avant de l'envoyer.
      offenders.removeWhere(
        (o) => o.contains('mission_repository.dart'),
      );
      expect(offenders, isEmpty);
    });

    test('un objet attendu et absent lève une erreur lisible', () {
      expect(requireMap({'a': 1}), {'a': 1});
      expect(requireMap(<dynamic, dynamic>{'a': 1}), {'a': 1});
      expect(() => requireMap(null), throwsFormatException);
      expect(() => requireMap(const []), throwsFormatException);
      expect(requireDataMap({'data': {'id': 3}}), {'id': 3});
      expect(() => requireDataMap({'data': null}), throwsFormatException);
      expect(() => requireDataMap('illisible'), throwsFormatException);
    });

    test('une requête en file abîmée est écartée sans bloquer les autres', () {
      expect(QueuedRequest.tryParse({'id': '1'}), isNull);
      expect(QueuedRequest.tryParse('illisible'), isNull);

      final ok = QueuedRequest.tryParse(<dynamic, dynamic>{
        'id': '1',
        'method': 'POST',
        'url': '/orders/7/verify-delivery',
        'timestamp': '2026-10-07T10:00:00Z',
        'owner_id': '4',
        'file_paths': {'photo': '/tmp/a.jpg'},
      });
      expect(ok?.ownerId, 4);
      expect(ok?.filePaths, {'photo': '/tmp/a.jpg'});
      expect(ok?.isMultipart, isFalse);
    });
  });

  test('l\'outil de génération refuse un APK signé en débogage', () {
    final tool = File('tool/build_apk.dart').readAsStringSync();

    expect(tool, contains("'--debug-signing'"));
    expect(tool, contains("!args.contains('--debug-signing')"));
  });
}
