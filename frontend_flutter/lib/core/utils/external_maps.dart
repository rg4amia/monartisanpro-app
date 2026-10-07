import 'package:url_launcher/url_launcher.dart';

/// Lien neutre `geo:` : le téléphone ouvre l'application de cartes que
/// l'utilisateur a choisie.
Uri geoUriFor(double latitude, double longitude, {String? label}) {
  final point = '$latitude,$longitude';
  final query = label == null || label.trim().isEmpty
      ? point
      : '$point(${Uri.encodeComponent(label.trim())})';

  return Uri.parse('geo:$point?q=$query');
}

/// Repli : Yandex Maps, fournisseur cartographique de ProsArtisan
/// (Règle d'or 44), avec un itinéraire vers le point.
Uri yandexMapsUriFor(double latitude, double longitude) => Uri.https(
      'yandex.com',
      '/maps/',
      {'rtext': '~$latitude,$longitude', 'rtt': 'auto'},
    );

/// Ouvre un point dans une application de cartes. `false` si rien ne s'ouvre.
///
/// Aucun lien Google Maps : les écrans en ouvraient deux (Règle d'or 44).
Future<bool> openInMaps(
  double latitude,
  double longitude, {
  String? label,
  Future<bool> Function(Uri uri)? launcher,
}) async {
  final launch = launcher ??
      (Uri uri) => launchUrl(uri, mode: LaunchMode.externalApplication);

  for (final uri in [
    geoUriFor(latitude, longitude, label: label),
    yandexMapsUriFor(latitude, longitude),
  ]) {
    try {
      if (await launch(uri)) return true;
    } catch (_) {
      // Aucune application pour ce lien : on tente le suivant.
    }
  }

  return false;
}
