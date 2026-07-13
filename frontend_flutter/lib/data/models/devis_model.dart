import 'jcode_item_model.dart';
import 'mission_model.dart';

class DevisLigne {
  final String type; // 'mo' | 'mat'
  final String description;
  final int montant;
  final String? source;
  final int? quantity;
  final int? unitPrice;
  final String? sku;
  final int? supplierProductId;

  const DevisLigne({
    required this.type,
    required this.description,
    required this.montant,
    this.source,
    this.quantity,
    this.unitPrice,
    this.sku,
    this.supplierProductId,
  });

  bool get isMaterial => type == 'mat';
  int get resolvedQuantity => quantity ?? 1;
  int get resolvedUnitPrice {
    if (unitPrice != null && unitPrice! > 0) {
      return unitPrice!;
    }
    final qty = resolvedQuantity == 0 ? 1 : resolvedQuantity;
    return (montant / qty).round();
  }

  JcodeItemModel toJcodeItem() {
    final catalog = source == 'catalog' && supplierProductId != null;

    return JcodeItemModel(
      supplierProductId: catalog ? supplierProductId : null,
      source: catalog ? 'catalog' : 'custom',
      name: description,
      sku: sku,
      quantity: resolvedQuantity,
      unitPrice: resolvedUnitPrice,
      subtotal: montant,
    );
  }

  factory DevisLigne.fromJson(Map<String, dynamic> json) => DevisLigne(
        type: (json['type'] ?? '').toString(),
        description: (json['description'] ?? '').toString(),
        montant: _parseInt(json['montant']),
        source: json['source']?.toString(),
        quantity: _parseNullableInt(json['quantity']),
        unitPrice: _parseNullableInt(json['unit_price'] ?? json['unitPrice']),
        sku: json['sku']?.toString(),
        supplierProductId: _parseNullableInt(
          json['supplier_product_id'] ?? json['supplierProductId'],
        ),
      );

  Map<String, dynamic> toJson() => {
        'type': type,
        'description': description,
        'montant': montant,
        if (source != null) 'source': source,
        if (quantity != null) 'quantity': quantity,
        if (unitPrice != null) 'unit_price': unitPrice,
        if (sku != null) 'sku': sku,
        if (supplierProductId != null) 'supplier_product_id': supplierProductId,
      };

  static int _parseInt(dynamic value) {
    if (value == null) return 0;
    if (value is int) return value;
    if (value is double) return value.toInt();
    return int.tryParse(value.toString()) ?? 0;
  }

  static int? _parseNullableInt(dynamic value) {
    if (value == null) return null;
    if (value is int) return value;
    if (value is double) return value.toInt();
    return int.tryParse(value.toString());
  }
}

class DevisJalon {
  final int ordre;
  final String description;
  final int montant;
  final String dateCible;

  const DevisJalon({
    required this.ordre,
    required this.description,
    required this.montant,
    required this.dateCible,
  });

  factory DevisJalon.fromJson(Map<String, dynamic> json) => DevisJalon(
        ordre: _parseInt(json['ordre']),
        description: (json['description'] ?? '').toString(),
        montant: _parseInt(json['montant']),
        dateCible: (json['date_cible'] as String? ??
            json['dateCible'] as String? ??
            ''),
      );

  Map<String, dynamic> toJson() => {
        'ordre': ordre,
        'description': description,
        'montant': montant,
        'date_cible': dateCible,
      };

  static int _parseInt(dynamic value) {
    if (value == null) return 0;
    if (value is int) return value;
    if (value is double) return value.toInt();
    return int.tryParse(value.toString()) ?? 0;
  }
}

class DevisModel {
  final int id;
  final int missionId;
  final int artisanId;
  final List<DevisLigne> lignes;
  final List<DevisJalon> jalons;
  final String statut;
  final String createdAt;
  final String? artisanName;
  final String? missionStatus;
  final double? ratioMateriaux;

  const DevisModel({
    required this.id,
    required this.missionId,
    required this.artisanId,
    required this.lignes,
    required this.jalons,
    required this.statut,
    required this.createdAt,
    this.artisanName,
    this.missionStatus,
    this.ratioMateriaux,
  });

  int get totalMo =>
      lignes.where((l) => l.type == 'mo').fold(0, (s, l) => s + l.montant);

  int get totalMat =>
      lignes.where((l) => l.type == 'mat').fold(0, (s, l) => s + l.montant);

  int get totalGeneral => totalMo + totalMat;
  List<DevisLigne> get materialLines =>
      lignes.where((ligne) => ligne.isMaterial).toList();

  factory DevisModel.fromJson(Map<String, dynamic> json) => DevisModel(
        id: _parseInt(json['id']),
        missionId: _parseInt(json['missionId'] ?? json['mission_id']),
        artisanId: _parseInt(json['artisanId'] ?? json['artisan_id']),
        lignes: (json['lignesJson'] as List<dynamic>? ?? const [])
            .whereType<Map<String, dynamic>>()
            .map(DevisLigne.fromJson)
            .toList(),
        jalons: (json['jalonsJson'] as List<dynamic>? ?? const [])
            .whereType<Map<String, dynamic>>()
            .map(DevisJalon.fromJson)
            .toList(),
        statut: (json['statut'] ?? '').toString(),
        createdAt: (json['createdAt'] ?? '').toString(),
        artisanName: json['artisanName'] as String?,
        missionStatus: MissionModel.normalizeStatus(
          (json['missionStatus'] ?? json['mission_status'] ?? '').toString(),
        ),
        ratioMateriaux: _parseNullableDouble(
          json['ratioMateriaux'] ?? json['ratio_materiaux'],
        ),
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'missionId': missionId,
        'artisanId': artisanId,
        'lignesJson': lignes.map((l) => l.toJson()).toList(),
        'jalonsJson': jalons.map((j) => j.toJson()).toList(),
        'statut': statut,
        'createdAt': createdAt,
        'artisanName': artisanName,
        'missionStatus': missionStatus,
        'ratioMateriaux': ratioMateriaux,
      };

  static int _parseInt(dynamic value) {
    if (value == null) return 0;
    if (value is int) return value;
    if (value is double) return value.toInt();
    return int.tryParse(value.toString()) ?? 0;
  }

  static double? _parseNullableDouble(dynamic value) {
    if (value == null) return null;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    return double.tryParse(value.toString());
  }
}
