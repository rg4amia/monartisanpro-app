/// Carte du chantier d'une mission (espace artisan / client).
///
/// - [client] : position du client ayant accepté et financé la mission
///   (`null` tant que la mission n'est pas financée, côté artisan).
/// - [suppliers] : fournisseurs chez qui des matériaux ont été retirés pour le
///   devis (rattachés via les J-Codes de la mission).
class MissionSiteMap {
  const MissionSiteMap({
    required this.missionId,
    this.client,
    this.suppliers = const [],
  });

  final int missionId;
  final SiteMapClient? client;
  final List<SiteMapSupplier> suppliers;

  bool get hasAnyPoint => client != null || suppliers.isNotEmpty;

  factory MissionSiteMap.fromJson(Map<String, dynamic> json) {
    final clientJson = json['client'];
    final suppliersJson = json['suppliers'];

    return MissionSiteMap(
      missionId: _asInt(json['mission_id'] ?? json['missionId']),
      client: clientJson is Map<String, dynamic>
          ? SiteMapClient.fromJson(clientJson)
          : null,
      suppliers: suppliersJson is List
          ? suppliersJson
              .whereType<Map<String, dynamic>>()
              .map(SiteMapSupplier.fromJson)
              .toList()
          : const [],
    );
  }

  static int _asInt(dynamic v) =>
      v is int ? v : int.tryParse(v?.toString() ?? '') ?? 0;
}

class SiteMapClient {
  const SiteMapClient({
    this.name,
    this.address,
    required this.lat,
    required this.lng,
  });

  final String? name;
  final String? address;
  final double lat;
  final double lng;

  factory SiteMapClient.fromJson(Map<String, dynamic> json) {
    final coords = json['coordinates'];
    return SiteMapClient(
      name: json['name']?.toString(),
      address: json['address']?.toString(),
      lat: _coord(coords, 'lat'),
      lng: _coord(coords, 'lng'),
    );
  }

  static double _coord(dynamic coords, String key) {
    if (coords is Map) {
      return double.tryParse(coords[key]?.toString() ?? '') ?? 0.0;
    }
    return 0.0;
  }
}

class SiteMapSupplier {
  const SiteMapSupplier({
    required this.id,
    required this.name,
    required this.lat,
    required this.lng,
    this.jcodeCount = 0,
    this.montant = 0,
  });

  final int id;
  final String name;
  final double lat;
  final double lng;
  final int jcodeCount;
  final int montant;

  factory SiteMapSupplier.fromJson(Map<String, dynamic> json) {
    final coords = json['coordinates'];
    return SiteMapSupplier(
      id: MissionSiteMap._asInt(json['id']),
      name: (json['name'] ?? 'Fournisseur').toString(),
      lat: SiteMapClient._coord(coords, 'lat'),
      lng: SiteMapClient._coord(coords, 'lng'),
      jcodeCount: MissionSiteMap._asInt(json['jcodeCount'] ?? json['jcode_count']),
      montant: MissionSiteMap._asInt(json['montant']),
    );
  }
}
