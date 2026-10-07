// Génère l'APK de publication, ou le bundle destiné à Google Play, en
// augmentant la version à chaque génération.
//
//   dart run tool/build_apk.dart              1.0.1+2 → 1.0.2+3 (correctif)
//   dart run tool/build_apk.dart --build-only 1.0.1+2 → 1.0.1+3
//   dart run tool/build_apk.dart --minor      1.0.1+2 → 1.1.0+3
//   dart run tool/build_apk.dart --major      1.0.1+2 → 2.0.0+3
//   dart run tool/build_apk.dart --no-bump    régénère la version en place
//   dart run tool/build_apk.dart --split-per-abi   un APK par architecture
//   dart run tool/build_apk.dart --bundle     bundle (.aab) pour Google Play
//   dart run tool/build_apk.dart --debug-signing   essai local sans clé de publication
//
// La version n'est conservée que si la génération réussit : un échec remet
// `pubspec.yaml` dans son état d'origine, pour qu'aucun numéro ne soit
// consommé par un APK qui n'existe pas.
//
// La génération porte toujours `--dart-define-from-file=env.json` : sans lui,
// la clé Yandex MapKit est vide et la carte est rejetée (Règle d'or 30).

import 'dart:io';

import 'version_bump.dart';

const _outputDir = 'build/app/outputs/flutter-apk';
const _bundleDir = 'build/app/outputs/bundle/release';

Future<void> main(List<String> args) async {
  const known = {
    '--build-only',
    '--minor',
    '--major',
    '--no-bump',
    '--split-per-abi',
    '--bundle',
    '--debug-signing',
  };
  final unknown = args.where((a) => !known.contains(a)).toList();
  if (unknown.isNotEmpty) {
    stderr.writeln('Option inconnue : ${unknown.join(', ')}');
    stderr.writeln('Options : ${known.join(' ')}');
    exit(64);
  }

  final pubspecFile = File('pubspec.yaml');
  if (!pubspecFile.existsSync() || !File('env.json').existsSync()) {
    stderr.writeln(
      'À lancer depuis frontend_flutter/, avec env.json présent '
      '(voir env.example.json).',
    );
    exit(66);
  }

  final original = pubspecFile.readAsStringSync();
  final current = readVersion(original);
  final part = args.contains('--major')
      ? VersionPart.major
      : args.contains('--minor')
          ? VersionPart.minor
          : args.contains('--build-only')
              ? VersionPart.buildOnly
              : VersionPart.patch;
  final next = args.contains('--no-bump') ? current : current.bump(part);
  final splitPerAbi = args.contains('--split-per-abi');
  final bundle = args.contains('--bundle');

  if (bundle && splitPerAbi) {
    stderr.writeln(
      "--split-per-abi ne s'applique pas à un bundle : Google Play "
      'découpe lui-même par architecture.',
    );
    exit(64);
  }

  // Google Play refuse un bundle signé avec la clé de débogage, sur laquelle
  // la génération se rabat sans android/key.properties.
  if (bundle && !File('android/key.properties').existsSync()) {
    stderr.writeln(
      'android/key.properties introuvable : le bundle serait signé avec la '
      'clé de débogage, que Google Play refuse.',
    );
    exit(66);
  }

  // Sans android/key.properties, l'APK est signé avec la clé de débogage :
  // installé, il ne pourra jamais être mis à jour par une version signée
  // correctement. Un essai local le demande explicitement.
  if (!bundle &&
      !File('android/key.properties').existsSync() &&
      !args.contains('--debug-signing')) {
    stderr.writeln(
      "android/key.properties introuvable : l'APK serait signé avec la clé "
      'de débogage. Pour un essai local à ne pas distribuer, ajoutez '
      '--debug-signing.',
    );
    exit(66);
  }

  if (next.toString() != current.toString()) {
    pubspecFile.writeAsStringSync(writeVersion(original, next));
    stdout.writeln('Version : $current → $next');
  } else {
    stdout.writeln('Version : $current (inchangée)');
  }

  final process = await Process.start(
    'flutter',
    [
      'build',
      bundle ? 'appbundle' : 'apk',
      '--release',
      '--dart-define-from-file=env.json',
      if (splitPerAbi) '--split-per-abi',
    ],
    mode: ProcessStartMode.inheritStdio,
    runInShell: true,
  );
  final code = await process.exitCode;

  if (code != 0) {
    pubspecFile.writeAsStringSync(original);
    stderr.writeln(
      'Génération échouée : la version reste $current, aucun numéro consommé.',
    );
    exit(code);
  }

  if (bundle) {
    final source = File('$_bundleDir/app-release.aab');
    if (!source.existsSync()) {
      stderr.writeln('Bundle attendu introuvable : ${source.path}');
      exit(70);
    }

    final named = '$_bundleDir/prosartisan-${next.name}-build${next.build}.aab';
    source.copySync(named);
    final megabytes = (source.lengthSync() / (1024 * 1024)).toStringAsFixed(1);
    stdout.writeln('Bundle : $named ($megabytes Mo)');
    return;
  }

  // Copie nommée d'après la version : un APK se reconnaît sans l'installer,
  // et la génération suivante ne l'écrase pas.
  final sources = splitPerAbi
      ? ['arm64-v8a', 'armeabi-v7a', 'x86_64']
          .map((abi) => (abi, File('$_outputDir/app-$abi-release.apk')))
      : [('universel', File('$_outputDir/app-release.apk'))];

  for (final (label, source) in sources) {
    if (!source.existsSync()) {
      stderr.writeln('APK attendu introuvable : ${source.path}');
      exit(70);
    }

    final suffix = splitPerAbi ? '-$label' : '';
    final named =
        '$_outputDir/prosartisan-${next.name}-build${next.build}$suffix.apk';
    source.copySync(named);
    final megabytes = (source.lengthSync() / (1024 * 1024)).toStringAsFixed(1);
    stdout.writeln('APK : $named ($megabytes Mo)');
  }
}
