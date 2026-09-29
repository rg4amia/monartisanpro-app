import '../../../data/models/supplier_model.dart';

/// Libellé des fournisseurs dont le secteur n'est pas encore renseigné :
/// aucun secteur n'est deviné à leur place (Règle d'or 29).
const kUnclassifiedSectorLabel = 'Autres fournisseurs';

/// Fournisseurs d'un même secteur d'activité.
class SupplierSectorGroup {
  const SupplierSectorGroup({required this.sector, required this.suppliers});

  /// Null pour la rubrique « Autres fournisseurs ».
  final SupplierSector? sector;
  final List<SupplierModel> suppliers;

  String get label => sector?.name ?? kUnclassifiedSectorLabel;

  int? get sectorId => sector?.id;
}

/// Regroupe les fournisseurs par secteur, secteurs triés par nom et
/// fournisseurs par enseigne ; les non classés viennent en dernier.
List<SupplierSectorGroup> groupSuppliersBySector(
  Iterable<SupplierModel> suppliers,
) {
  final bySector = <int, List<SupplierModel>>{};
  final sectors = <int, SupplierSector>{};
  final unclassified = <SupplierModel>[];

  for (final supplier in suppliers) {
    final sector = supplier.sector;
    if (sector == null) {
      unclassified.add(supplier);
      continue;
    }
    sectors[sector.id] = sector;
    bySector.putIfAbsent(sector.id, () => []).add(supplier);
  }

  int byShop(SupplierModel a, SupplierModel b) =>
      _sortKey(a.shopName).compareTo(_sortKey(b.shopName));

  final groups = sectors.values
      .map(
        (sector) => SupplierSectorGroup(
          sector: sector,
          suppliers: bySector[sector.id]!..sort(byShop),
        ),
      )
      .toList()
    ..sort((a, b) => _sortKey(a.label).compareTo(_sortKey(b.label)));

  if (unclassified.isNotEmpty) {
    groups.add(
      SupplierSectorGroup(sector: null, suppliers: unclassified..sort(byShop)),
    );
  }

  return groups;
}

/// Clé de tri alphabétique française : « Électricité » avant « Plomberie »
/// (un tri sur les codes Unicode rejetait les initiales accentuées en fin).
String _sortKey(String value) {
  const accents = {
    'à': 'a',
    'â': 'a',
    'ä': 'a',
    'ç': 'c',
    'é': 'e',
    'è': 'e',
    'ê': 'e',
    'ë': 'e',
    'î': 'i',
    'ï': 'i',
    'ô': 'o',
    'ö': 'o',
    'ù': 'u',
    'û': 'u',
    'ü': 'u',
    'ÿ': 'y',
    'œ': 'oe',
    'æ': 'ae',
  };
  final lower = value.toLowerCase();
  final buffer = StringBuffer();
  for (final char in lower.split('')) {
    buffer.write(accents[char] ?? char);
  }
  return buffer.toString();
}
