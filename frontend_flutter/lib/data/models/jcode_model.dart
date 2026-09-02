import 'jcode_item_model.dart';
import 'supplier_model.dart';

class JcodeModel {
  final int id;
  final int missionId;
  final int artisanId;
  final int? fournisseurId;
  final String code;
  final String? qrUrl;
  final String? ussdCode;
  final int montant;
  final int montantConsomme;
  final String statut;
  final double? scanLat;
  final double? scanLng;
  final String? scannedAt;
  final String expiresAt;
  final String? paymentStatus;
  final SupplierModel? supplier;
  final List<JcodeItemModel> items;

  const JcodeModel({
    required this.id,
    required this.missionId,
    required this.artisanId,
    required this.code,
    required this.montant,
    required this.statut,
    required this.expiresAt,
    this.montantConsomme = 0,
    this.fournisseurId,
    this.qrUrl,
    this.ussdCode,
    this.scanLat,
    this.scanLng,
    this.scannedAt,
    this.paymentStatus,
    this.supplier,
    this.items = const [],
  });

  bool get isActive => statut == 'actif' || statut == 'partiellement_utilise';
  bool get isUsed => statut == 'utilise';
  bool get isExpired => statut == 'expire';
  bool get isPartiallyUsed => statut == 'partiellement_utilise';
  int get montantRestant => montant - montantConsomme;

  factory JcodeModel.fromJson(Map<String, dynamic> json) {
    final artisan = json['artisan'] is Map<String, dynamic>
        ? json['artisan'] as Map<String, dynamic>
        : null;
    final fournisseur = json['fournisseur'] is Map<String, dynamic>
        ? json['fournisseur'] as Map<String, dynamic>
        : null;
    final itemsRaw = json['items'];

    return JcodeModel(
      id: _parseInt(json['id']),
      missionId: _parseInt(json['missionId'] ?? json['mission_id']),
      artisanId: _parseInt(
        json['artisanId'] ?? json['artisan_id'] ?? artisan?['id'],
      ),
      fournisseurId: _parseNullableInt(
        json['fournisseurId'] ?? json['fournisseur_id'] ?? fournisseur?['id'],
      ),
      code: (json['code'] ?? json['tokenCode'] ?? '').toString(),
      qrUrl: (json['qrUrl'] ?? json['qr_url']) as String?,
      ussdCode: (json['ussdCode'] ?? json['ussd_code']) as String?,
      montant: _parseInt(json['montant'] ?? json['tokenAmount']),
      montantConsomme:
          _parseInt(json['montantConsomme'] ?? json['montant_consomme']),
      statut: (json['statut'] ?? json['status'] ?? '').toString(),
      scanLat: _parseDouble(json['scanLat'] ?? json['scan_lat']),
      scanLng: _parseDouble(json['scanLng'] ?? json['scan_lng']),
      scannedAt: (json['scannedAt'] ?? json['scanned_at'])?.toString(),
      expiresAt: (json['expiresAt'] ??
              json['expires_at'] ??
              DateTime.now().toIso8601String())
          .toString(),
      paymentStatus:
          (json['paymentStatus'] ?? json['paiement_status']) as String?,
      supplier:
          fournisseur == null ? null : SupplierModel.fromJson(fournisseur),
      items: itemsRaw is List
          ? itemsRaw
              .whereType<Map<String, dynamic>>()
              .map(JcodeItemModel.fromJson)
              .toList()
          : const [],
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'missionId': missionId,
        'artisanId': artisanId,
        'fournisseurId': fournisseurId,
        'code': code,
        'qrUrl': qrUrl,
        'ussdCode': ussdCode,
        'montant': montant,
        'montantConsomme': montantConsomme,
        'statut': statut,
        'scanLat': scanLat,
        'scanLng': scanLng,
        'scannedAt': scannedAt,
        'expiresAt': expiresAt,
        'paymentStatus': paymentStatus,
        'supplier': supplier?.toJson(),
        'items': items.map((item) => item.toJson()).toList(),
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

  static double? _parseDouble(dynamic value) {
    if (value == null) return null;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    return double.tryParse(value.toString());
  }
}
