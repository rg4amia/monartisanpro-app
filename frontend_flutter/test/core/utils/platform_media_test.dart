import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/utils/platform_media.dart';

/// Médias ouverts hors de l'application et journal du téléphone (Chantier 42).
void main() {
  const api = 'https://api.prosartisan.net/api/v1';

  group('adresse d\'un média reçue du serveur', () {
    test('un fichier de la plateforme s\'ouvre', () {
      expect(
        isPlatformMediaUrl(
          'https://api.prosartisan.net/media/prive/missions/a.mp4?signature=x',
          apiBaseUrl: api,
        ),
        isTrue,
      );
      expect(
        isPlatformMediaUrl(
          'https://API.prosartisan.net/storage/uploads/a.mp4',
          apiBaseUrl: api,
        ),
        isTrue,
      );
    });

    test('une adresse choisie par un autre utilisateur ne s\'ouvre pas', () {
      for (final url in [
        'https://un-site.example/x.mp4',
        'https://api.prosartisan.net.un-site.example/x.mp4',
        'https://api.prosartisan.net@un-site.example/x.mp4',
        'tel:+2250708091011',
        'intent://un-site.example/x.mp4',
        '/storage/uploads/a.mp4',
        '',
      ]) {
        expect(isPlatformMediaUrl(url, apiBaseUrl: api), isFalse, reason: url);
      }
    });
  });

  group('journal du téléphone', () {
    test('la version de publication neutralise debugPrint', () {
      final main = File('lib/main.dart').readAsStringSync();

      expect(main, contains('if (kReleaseMode) {'));
      expect(
        main,
        contains('debugPrint = (String? message, {int? wrapWidth}) {};'),
      );
    });

    test('l\'adresse de l\'Assistant, qui porte le jeton, n\'est jamais écrite',
        () {
      final screen = File('lib/modules/ia/views/ia_assistant_screen.dart')
          .readAsStringSync();
      final logged = RegExp(r'debugPrint\(([^;]*);', dotAll: true)
          .allMatches(screen)
          .map((m) => m.group(1)!);

      expect(logged, isNotEmpty);
      for (final call in logged) {
        expect(call, isNot(contains('assistantUrl')));
        expect(call, isNot(contains('_authToken')));
        expect(call, isNot(contains('error.url')));
      }
    });
  });
}
