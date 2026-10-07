import '../../core/utils/json_readers.dart';

/// Un point géographique.
class RoutePoint {
  const RoutePoint(this.latitude, this.longitude);

  final double latitude;
  final double longitude;

  /// Lit `{lat, lng}` ; `null` si une coordonnée manque.
  static RoutePoint? tryParse(dynamic raw) {
    final map = readMap(raw);
    final lat = readDouble(map?['lat']);
    final lng = readDouble(map?['lng']);
    if (lat == null || lng == null) return null;

    return RoutePoint(lat, lng);
  }
}

/// Itinéraire d'une étape de course, établi par le serveur
/// (`GET /orders/{id}/route`).
///
/// Le serveur seul connaît la boutique et la destination figée sur la
/// commande : l'application ne place plus ces points elle-même.
class DriverRoute {
  const DriverRoute({
    required this.from,
    required this.to,
    required this.path,
    required this.isEstimate,
    this.distanceKm,
    this.durationMin,
  });

  final RoutePoint from;
  final RoutePoint to;

  /// Tracé à dessiner, du départ à l'arrivée.
  final List<RoutePoint> path;

  /// `true` quand aucun service d'itinéraire n'a répondu : la distance est
  /// une estimation et le tracé une ligne droite.
  final bool isEstimate;

  final double? distanceKm;
  final double? durationMin;

  /// Lève une [FormatException] sur une réponse sans départ ni arrivée : une
  /// réponse illisible n'est pas un itinéraire vide.
  factory DriverRoute.fromResponse(dynamic body) {
    final data = readMap(readMap(body)?['data']);
    final from = RoutePoint.tryParse(data?['from']);
    final to = RoutePoint.tryParse(data?['to']);
    if (from == null || to == null) {
      throw const FormatException('Itinéraire absent de la réponse.');
    }

    final route = readMap(data?['route']);
    // Le serveur rend les points au format GeoJSON : longitude d'abord.
    final path = (readList(route?['geometry']) ?? const [])
        .map(readList)
        .whereType<List<dynamic>>()
        .where((pair) => pair.length >= 2)
        .map((pair) {
          final lng = readDouble(pair[0]);
          final lat = readDouble(pair[1]);

          return lat == null || lng == null ? null : RoutePoint(lat, lng);
        })
        .whereType<RoutePoint>()
        .toList();

    return DriverRoute(
      from: from,
      to: to,
      path: path.length >= 2 ? path : [from, to],
      // Sans indication, on ne présente pas la distance comme exacte.
      isEstimate: readBool(route?['is_fallback']) ?? true,
      distanceKm: readDouble(route?['distance_km']),
      durationMin: readDouble(route?['duration_min']),
    );
  }
}
