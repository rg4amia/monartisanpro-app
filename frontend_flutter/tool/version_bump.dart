/// Incrément de la version de l'application dans `pubspec.yaml`.
///
/// La version s'écrit `majeur.mineur.correctif+build`. Le numéro de build
/// (`versionCode` Android) augmente à chaque génération : le Play Store et
/// Android refusent d'installer un APK dont le numéro n'est pas supérieur à
/// celui déjà installé.
library;

/// Partie du nom de version à augmenter, en plus du numéro de build.
enum VersionPart {
  /// Le numéro de build seul : `1.0.1+2` → `1.0.1+3`.
  buildOnly,

  /// Le correctif : `1.0.1+2` → `1.0.2+3`.
  patch,

  /// Le mineur : `1.0.1+2` → `1.1.0+3`.
  minor,

  /// Le majeur : `1.0.1+2` → `2.0.0+3`.
  major,
}

/// Version lue dans `pubspec.yaml`.
class AppVersion {
  const AppVersion(this.major, this.minor, this.patch, this.build);

  final int major;
  final int minor;
  final int patch;
  final int build;

  /// Nom de version montré à l'utilisateur (`1.0.2`).
  String get name => '$major.$minor.$patch';

  /// Le numéro de build augmente toujours, quel que soit [part] : il ne
  /// repart jamais de 1 quand le nom de version change.
  AppVersion bump(VersionPart part) => switch (part) {
        VersionPart.buildOnly => AppVersion(major, minor, patch, build + 1),
        VersionPart.patch => AppVersion(major, minor, patch + 1, build + 1),
        VersionPart.minor => AppVersion(major, minor + 1, 0, build + 1),
        VersionPart.major => AppVersion(major + 1, 0, 0, build + 1),
      };

  @override
  String toString() => '$name+$build';
}

final RegExp _versionLine = RegExp(
  r'^version:[ \t]*(\d+)\.(\d+)\.(\d+)\+(\d+)[ \t]*$',
  multiLine: true,
);

/// Lit la version du contenu d'un `pubspec.yaml`.
///
/// Lève une [FormatException] si la ligne `version:` manque ou n'a pas la
/// forme `majeur.mineur.correctif+build` : mieux vaut arrêter la génération
/// que produire un APK au numéro inventé.
AppVersion readVersion(String pubspec) {
  final match = _versionLine.firstMatch(pubspec);
  if (match == null) {
    throw const FormatException(
      'pubspec.yaml : ligne « version: majeur.mineur.correctif+build » introuvable.',
    );
  }

  return AppVersion(
    int.parse(match.group(1)!),
    int.parse(match.group(2)!),
    int.parse(match.group(3)!),
    int.parse(match.group(4)!),
  );
}

/// Rend le contenu de `pubspec.yaml` avec sa version remplacée par [version].
/// Seule la ligne `version:` change.
String writeVersion(String pubspec, AppVersion version) {
  readVersion(pubspec);

  return pubspec.replaceFirst(_versionLine, 'version: $version');
}
