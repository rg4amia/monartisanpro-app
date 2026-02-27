class UserModel {
  final int id;
  final String phone;
  final String role;
  final String kycStatus;
  final int scoreNzassa;
  final int walletMateriaux;
  final int walletMo;
  final String? name;
  final String? photoUrl;
  final double? lat;
  final double? lng;

  const UserModel({
    required this.id,
    required this.phone,
    required this.role,
    required this.kycStatus,
    required this.scoreNzassa,
    required this.walletMateriaux,
    required this.walletMo,
    this.name,
    this.photoUrl,
    this.lat,
    this.lng,
  });

  bool get isKycActif => kycStatus == 'actif';
  bool get isGoldenMarker => scoreNzassa > 65;

  factory UserModel.fromJson(Map<String, dynamic> json) => UserModel(
        id: json['id'] as int,
        phone: json['phone'] as String,
        role: json['role'] as String,
        kycStatus: json['kycStatus'] as String? ?? 'en_attente',
        scoreNzassa: json['scoreNzassa'] as int? ?? 0,
        walletMateriaux: json['walletMateriaux'] as int? ?? 0,
        walletMo: json['walletMo'] as int? ?? 0,
        name: json['name'] as String?,
        photoUrl: json['photoUrl'] as String?,
        lat: (json['lat'] as num?)?.toDouble(),
        lng: (json['lng'] as num?)?.toDouble(),
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'phone': phone,
        'role': role,
        'kycStatus': kycStatus,
        'scoreNzassa': scoreNzassa,
        'walletMateriaux': walletMateriaux,
        'walletMo': walletMo,
        'name': name,
        'photoUrl': photoUrl,
        'lat': lat,
        'lng': lng,
      };
}
