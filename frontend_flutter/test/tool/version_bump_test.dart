import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

import '../../tool/version_bump.dart';

/// Incrément de la version à chaque génération de l'APK
/// (`dart run tool/build_apk.dart`).
void main() {
  const pubspec = '''
name: frontend_flutter
description: "ProsArtisan"

version: 1.0.1+2

environment:
  sdk: '>=3.5.0 <4.0.0'

dependencies:
  intl: ^0.19.0
''';

  test('lit la version du pubspec', () {
    final version = readVersion(pubspec);

    expect(version.name, '1.0.1');
    expect(version.build, 2);
    expect(version.toString(), '1.0.1+2');
  });

  test('le numéro de build augmente toujours, quelle que soit la partie', () {
    final version = readVersion(pubspec);

    expect(version.bump(VersionPart.buildOnly).toString(), '1.0.1+3');
    expect(version.bump(VersionPart.patch).toString(), '1.0.2+3');
    expect(version.bump(VersionPart.minor).toString(), '1.1.0+3');
    expect(version.bump(VersionPart.major).toString(), '2.0.0+3');
  });

  test('deux générations de suite donnent deux numéros de build distincts', () {
    final first = readVersion(pubspec).bump(VersionPart.patch);
    final second =
        readVersion(writeVersion(pubspec, first)).bump(VersionPart.patch);

    expect(first.toString(), '1.0.2+3');
    expect(second.toString(), '1.0.3+4');
  });

  test('seule la ligne de version change dans le pubspec', () {
    final updated = writeVersion(pubspec, const AppVersion(1, 0, 2, 3));

    expect(updated, pubspec.replaceFirst('1.0.1+2', '1.0.2+3'));
    // Une dépendance dont la contrainte ressemble à une version reste intacte.
    expect(updated, contains('intl: ^0.19.0'));
  });

  test('une version absente ou sans numéro de build arrête la génération', () {
    expect(() => readVersion('name: app\n'), throwsFormatException);
    expect(() => readVersion('version: 1.0.1\n'), throwsFormatException);
    expect(
      () => writeVersion('version: 1.0\n', const AppVersion(1, 0, 2, 3)),
      throwsFormatException,
    );
  });

  test('le pubspec réel du projet porte une version lisible', () {
    final version = readVersion(File('pubspec.yaml').readAsStringSync());

    expect(version.build, greaterThan(0));
  });
}
