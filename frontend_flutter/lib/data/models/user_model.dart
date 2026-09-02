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
    final artisanProfile = json['artisanProfile'] as Map<String, dynamic>?;
    return UserModel(
      id: json['id'] as int,
      phone: json['phone'] as String,
      role: json['role'] as String,
      kycStatus: (json['kycStatus'] ?? json['kyc_status']) as String? ?? 'en_attente',
      scoreProsArtisan: (json['scoreProsArtisan'] ?? json['score_prosartisan']) as int? ?? 0,
      walletMateriaux: (json['walletMateriaux'] ?? json['wallet_materiaux']) as int? ?? 0,
      walletMo: (json['walletMo'] ?? json['wallet_mo']) as int? ?? 0,
      name: json['name'] as String?,
      photoUrl: (json['photoUrl'] ?? json['photo_url']) as String?,
      lat: (json['lat'] as num?)?.toDouble() ??
          (json['position'] is Map<String, dynamic>
              ? (json['position'] as Map<String, dynamic>)['lat'] as num?
              : null)?.toDouble(),
      lng: (json['lng'] as num?)?.toDouble() ??
          (json['position'] is Map<String, dynamic>
              ? (json['position'] as Map<String, dynamic>)['lng'] as num?
              : null)?.toDouble(),
      nightInterventionAvailable: _parseBool(
        json['nightInterventionAvailable'] ??
            json['night_intervention_available'] ??
            (artisanProfile != null
                ? artisanProfile['nightInterventionAvailable']
                : null),
      ),
      sectorId: artisanProfile != null ? artisanProfile['sectorId'] as int? : null,
      tradeId: artisanProfile != null ? artisanProfile['tradeId'] as int? : null,
      sectorName: artisanProfile != null ? artisanProfile['sector'] as String? : null,
      tradeName: artisanProfile != null ? artisanProfile['trade'] as String? : null,
      cguAcceptedAt: json['cguAcceptedAt'] as String?,
      cnmciNumber: json['cnmciNumber'] as String?,
      cnmciCardUrl: json['cnmciCardUrl'] as String?,
      cnmciStatus: json['cnmciStatus'] as String? ?? 'non_renseigne',
      paymentPhone: json['paymentPhone'] as String? ?? json['payment_phone'] as String?,
      preferredPaymentProvider: json['preferredPaymentProvider'] as String? ?? json['preferred_payment_provider'] as String?,
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
