import 'package:json_annotation/json_annotation.dart';

part 'user_model.g.dart';

@JsonSerializable()
class User {
  final int id;
  final String name;
  final String email;
  final String? phone;
  final String role;
  final String? avatar;
  @JsonKey(name: 'kyc_status')
  final String kycStatus;
  final String status;
  @JsonKey(name: 'phone_verified_at')
  final String? phoneVerifiedAt;
  @JsonKey(name: 'created_at')
  final String createdAt;
  @JsonKey(name: 'updated_at')
  final String updatedAt;
  @JsonKey(name: 'artisan_profile')
  final ArtisanProfile? artisanProfile;

  User({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    required this.role,
    this.avatar,
    required this.kycStatus,
    required this.status,
    this.phoneVerifiedAt,
    required this.createdAt,
    required this.updatedAt,
    this.artisanProfile,
  });

  factory User.fromJson(Map<String, dynamic> json) => _$UserFromJson(json);
  Map<String, dynamic> toJson() => _$UserToJson(this);

  bool get isArtisan => role == 'artisan';
  bool get isClient => role == 'client';
  bool get isFournisseur => role == 'fournisseur';
  bool get isKycApproved => kycStatus == 'approved';
  bool get isPhoneVerified => phoneVerifiedAt != null;
}

@JsonSerializable()
class ArtisanProfile {
  final int id;
  @JsonKey(name: 'user_id')
  final int userId;
  @JsonKey(name: 'trade_id')
  final int tradeId;
  final Trade? trade;
  @JsonKey(name: 'zone_name')
  final String? zoneName;
  final String? bio;
  @JsonKey(name: 'experience_years')
  final int experienceYears;
  final bool available;
  @JsonKey(name: 'created_at')
  final String createdAt;
  @JsonKey(name: 'updated_at')
  final String updatedAt;

  ArtisanProfile({
    required this.id,
    required this.userId,
    required this.tradeId,
    this.trade,
    this.zoneName,
    this.bio,
    required this.experienceYears,
    required this.available,
    required this.createdAt,
    required this.updatedAt,
  });

  factory ArtisanProfile.fromJson(Map<String, dynamic> json) =>
      _$ArtisanProfileFromJson(json);
  Map<String, dynamic> toJson() => _$ArtisanProfileToJson(this);
}

@JsonSerializable()
class Trade {
  final int id;
  @JsonKey(name: 'sector_id')
  final int sectorId;
  final String code;
  final String name;
  final Sector? sector;

  Trade({
    required this.id,
    required this.sectorId,
    required this.code,
    required this.name,
    this.sector,
  });

  factory Trade.fromJson(Map<String, dynamic> json) => _$TradeFromJson(json);
  Map<String, dynamic> toJson() => _$TradeToJson(this);
}

@JsonSerializable()
class Sector {
  final int id;
  final String code;
  final String name;

  Sector({
    required this.id,
    required this.code,
    required this.name,
  });

  factory Sector.fromJson(Map<String, dynamic> json) => _$SectorFromJson(json);
  Map<String, dynamic> toJson() => _$SectorToJson(this);
}
