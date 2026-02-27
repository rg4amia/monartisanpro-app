class JcodeModel {
  final int id;
  final int missionId;
  final int artisanId;
  final int? fournisseurId;
  final String code; // PA-XXXX
  final String? qrUrl;
  final String? ussdCode;
  final int montant;
  final String statut;
  final double? scanLat;
  final double? scanLng;
  final String? scannedAt;
  final String expiresAt;

  const JcodeModel({
    required this.id,
    required this.missionId,
    required this.artisanId,
    required this.code,
    required this.montant,
    required this.statut,
    required this.expiresAt,
    this.fournisseurId,
    this.qrUrl,
    this.ussdCode,
    this.scanLat,
    this.scanLng,
    this.scannedAt,
  });

  bool get isActive => statut == 'actif';
  bool get isUsed => statut == 'utilise';
  bool get isExpired => statut == 'expire';

  factory JcodeModel.fromJson(Map<String, dynamic> json) => JcodeModel(
        id: json['id'] as int,
        missionId: json['missionId'] as int,
        artisanId: json['artisanId'] as int,
        fournisseurId: json['fournisseurId'] as int?,
        code: json['code'] as String,
        qrUrl: json['qrUrl'] as String?,
        ussdCode: json['ussdCode'] as String?,
        montant: json['montant'] as int,
        statut: json['statut'] as String,
        scanLat: (json['scanLat'] as num?)?.toDouble(),
        scanLng: (json['scanLng'] as num?)?.toDouble(),
        scannedAt: json['scannedAt'] as String?,
        expiresAt: json['expiresAt'] as String,
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'missionId': missionId,
        'artisanId': artisanId,
        'fournisseurId': fournisseurId,
        'code': code,
        'qrUrl': qrUrl,
        'ussdCode': ussdCode,
        'montant': montant,
        'statut': statut,
        'scanLat': scanLat,
        'scanLng': scanLng,
        'scannedAt': scannedAt,
        'expiresAt': expiresAt,
      };
}
