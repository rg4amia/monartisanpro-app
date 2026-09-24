import 'package:frontend_flutter/core/utils/json_readers.dart';

class UserModel {
  final int id;
  final String phone;
  final String role;
  final String kycStatus;
  final int scoreProsArtisan;
  final int walletMateriaux;
  final int walletMo;
  final String? name;
  final String? photoUrl;
  final double? lat;
  final double? lng;
  final int? sectorId;
  final int? tradeId;
  final String? sectorName;
  final String? tradeName;
  final bool nightInterventionAvailable;
  final String? cguAcceptedAt;
  final String? cnmciNumber;
  final String? cnmciCardUrl;
  final String cnmciStatus;
  final String? paymentPhone;
  final String? preferredPaymentProvider;

  const UserModel({
    required this.id,
    required this.phone,
    required this.role,
    required this.kycStatus,
    required this.scoreProsArtisan,
    required this.walletMateriaux,
    required this.walletMo,
    this.name,
    this.photoUrl,
    this.lat,
    this.lng,
    this.nightInterventionAvailable = false,
    this.sectorId,
    this.tradeId,
    this.sectorName,
    this.tradeName,
    this.cguAcceptedAt,
    this.cnmciNumber,
    this.cnmciCardUrl,
    this.cnmciStatus = 'non_renseigne',
    this.paymentPhone,
    this.preferredPaymentProvider,
  });

  bool get isKycActif => kycStatus == 'actif';
  bool get isGoldenMarker => scoreProsArtisan >= 700;
  bool get isCnmciVerified => cnmciStatus == 'valide';

  factory UserModel.fromJson(Map<String, dynamic> json) {
    final artisanProfile = readMap(json['artisanProfile']);
    return UserModel(
      id: readInt(json['id']) ?? 0,
      phone: readString(json['phone']) ?? '',
      role: readString(json['role']) ?? '',
      kycStatus:
          readString(json['kycStatus'] ?? json['kyc_status']) ?? 'en_attente',
      scoreProsArtisan:
          readInt(json['scoreProsArtisan'] ?? json['score_prosartisan']) ?? 0,
      walletMateriaux:
          readInt(json['walletMateriaux'] ?? json['wallet_materiaux']) ?? 0,
      walletMo: readInt(json['walletMo'] ?? json['wallet_mo']) ?? 0,
      name: readString(json['name']),
      photoUrl: readString(json['photoUrl'] ?? json['photo_url']),
      lat: readDouble(json['lat']) ??
          readDouble(readMap(json['position'])?['lat']),
      lng: readDouble(json['lng']) ??
          readDouble(readMap(json['position'])?['lng']),
      nightInterventionAvailable: _parseBool(
        json['nightInterventionAvailable'] ??
            json['night_intervention_available'] ??
            (artisanProfile != null
                ? artisanProfile['nightInterventionAvailable']
                : null),
      ),
      sectorId:
          artisanProfile != null ? readInt(artisanProfile['sectorId']) : null,
      tradeId:
          artisanProfile != null ? readInt(artisanProfile['tradeId']) : null,
      sectorName:
          artisanProfile != null ? readString(artisanProfile['sector']) : null,
      tradeName:
          artisanProfile != null ? readString(artisanProfile['trade']) : null,
      cguAcceptedAt: readString(json['cguAcceptedAt']),
      cnmciNumber: readString(json['cnmciNumber']),
      cnmciCardUrl: readString(json['cnmciCardUrl']),
      cnmciStatus: readString(json['cnmciStatus']) ?? 'non_renseigne',
      paymentPhone:
          readString(json['paymentPhone']) ?? readString(json['payment_phone']),
      preferredPaymentProvider: readString(json['preferredPaymentProvider']) ??
          readString(json['preferred_payment_provider']),
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'phone': phone,
        'role': role,
        'kycStatus': kycStatus,
        'scoreProsArtisan': scoreProsArtisan,
        'walletMateriaux': walletMateriaux,
        'walletMo': walletMo,
        'name': name,
        'photoUrl': photoUrl,
        'lat': lat,
        'lng': lng,
        'nightInterventionAvailable': nightInterventionAvailable,
        'sectorId': sectorId,
        'tradeId': tradeId,
        'sectorName': sectorName,
        'tradeName': tradeName,
        'cguAcceptedAt': cguAcceptedAt,
        'cnmciNumber': cnmciNumber,
        'cnmciCardUrl': cnmciCardUrl,
        'cnmciStatus': cnmciStatus,
        'paymentPhone': paymentPhone,
        'preferredPaymentProvider': preferredPaymentProvider,
      };

  static bool _parseBool(dynamic value) {
    if (value is bool) return value;
    if (value is int) return value == 1;
    if (value is String) {
      final normalized = value.trim().toLowerCase();
      return normalized == '1' || normalized == 'true' || normalized == 'oui';
    }
    return false;
  }
}
