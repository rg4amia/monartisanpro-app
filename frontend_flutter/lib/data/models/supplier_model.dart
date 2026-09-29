import '../../core/utils/json_readers.dart';

/// Secteur d'activité d'un fournisseur (taxonomie `sectors` du backoffice).
class SupplierSector {
  const SupplierSector({
    required this.id,
    required this.name,
    this.icon,
    this.color,
  });

  final int id;
  final String name;

  /// Clé d'icône du backoffice (`zap`, `droplets`…).
  final String? icon;

  /// Couleur hexadécimale (`#3498DB`).
  final String? color;

  static SupplierSector? fromJson(dynamic value) {
    final json = readMap(value);
    final id = readInt(json?['id']);
    final name = readString(json?['name']);
    if (id == null || name == null || name.trim().isEmpty) return null;
    return SupplierSector(
      id: id,
      name: name,
      icon: readString(json?['icon']),
      color: readString(json?['color']),
    );
  }

  Map<String, dynamic> toJson() =>
      {'id': id, 'name': name, 'icon': icon, 'color': color};
}

class SupplierModel {
  final int id;
  final String name;
  final String phone;
  final String shopName;
  final String? status;
  final Map<String, double>? location;
  final int activeProductsCount;
  final String? createdAt;

  /// Secteur d'activité ; null tant que le backoffice ne l'a pas renseigné.
  final SupplierSector? sector;

  /// Métier ou spécialité, facultatif.
  final String? trade;

  const SupplierModel({
    required this.id,
    required this.name,
    required this.phone,
    required this.shopName,
    required this.activeProductsCount,
    this.status,
    this.location,
    this.createdAt,
    this.sector,
    this.trade,
  });

  factory SupplierModel.fromJson(Map<String, dynamic> json) {
    return SupplierModel(
      id: _parseInt(json['id']),
      name: (json['name'] ?? '').toString(),
      phone: (json['phone'] ?? '').toString(),
      shopName: (json['shopName'] ?? json['shop_name'] ?? json['name'] ?? '')
          .toString(),
      status: json['status']?.toString(),
      location: _parseLocation(json['location']),
      activeProductsCount: _parseInt(
        json['activeProductsCount'] ?? json['active_products_count'],
      ),
      createdAt: json['createdAt']?.toString(),
      sector: SupplierSector.fromJson(json['sector']),
      trade: readString(json['trade']),
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'name': name,
        'phone': phone,
        'shopName': shopName,
        'status': status,
        'location': location,
        'activeProductsCount': activeProductsCount,
        'createdAt': createdAt,
        'sector': sector?.toJson(),
        'trade': trade,
      };

  static int _parseInt(dynamic value) {
    if (value == null) return 0;
    if (value is int) return value;
    if (value is double) return value.toInt();
    return int.tryParse(value.toString()) ?? 0;
  }

  static Map<String, double>? _parseLocation(dynamic value) {
    if (value is! Map<String, dynamic>) return null;

    final lat = _parseDouble(value['lat'] ?? value['latitude']);
    final lng = _parseDouble(value['lng'] ?? value['longitude']);
    if (lat == null || lng == null) return null;

    return {'lat': lat, 'lng': lng};
  }

  static double? _parseDouble(dynamic value) {
    if (value == null) return null;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    return double.tryParse(value.toString());
  }
}
