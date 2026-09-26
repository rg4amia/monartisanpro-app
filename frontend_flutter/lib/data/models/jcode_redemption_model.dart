import 'supplier_model.dart';

class JcodeRedemptionModel {
  final int id;
  final int jcodeId;
  final int fournisseurId;
  final int montant;
  final String? recuPhotoUrl;
  final double? latitude;
  final double? longitude;
  final List<dynamic> items;
  final String? scannedAt;
  final SupplierModel? fournisseur;

  const JcodeRedemptionModel({
    required this.id,
    required this.jcodeId,
    required this.fournisseurId,
    required this.montant,
    this.recuPhotoUrl,
    this.latitude,
    this.longitude,
    this.items = const [],
    this.scannedAt,
    this.fournisseur,
  });

  factory JcodeRedemptionModel.fromJson(Map<String, dynamic> json) {
    final fournisseurRaw = json['fournisseur'];
    return JcodeRedemptionModel(
      id: _parseInt(json['id']),
      jcodeId: _parseInt(json['jcodeId'] ?? json['jcode_id']),
      fournisseurId: _parseInt(json['fournisseurId'] ?? json['fournisseur_id']),
      montant: _parseInt(json['montant']),
      recuPhotoUrl: (json['recuPhotoUrl'] ?? json['recu_photo_url'])?.toString(),
      latitude: _parseDouble(json['latitude']),
      longitude: _parseDouble(json['longitude']),
      items: (json['items'] as List<dynamic>?) ?? const [],
      scannedAt: (json['scannedAt'] ?? json['scanned_at'])?.toString(),
      fournisseur: fournisseurRaw is Map<String, dynamic>
          ? SupplierModel.fromJson(fournisseurRaw)
          : null,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'jcodeId': jcodeId,
        'fournisseurId': fournisseurId,
        'montant': montant,
        'recuPhotoUrl': recuPhotoUrl,
        'latitude': latitude,
        'longitude': longitude,
        'items': items,
        'scannedAt': scannedAt,
        'fournisseur': fournisseur?.toJson(),
      };

  static int _parseInt(dynamic value) {
    if (value == null) return 0;
    if (value is int) return value;
    if (value is double) return value.toInt();
    return int.tryParse(value.toString()) ?? 0;
  }

  static double? _parseDouble(dynamic value) {
    if (value == null) return null;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    return double.tryParse(value.toString());
  }
}
