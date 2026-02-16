// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'user_model.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

User _$UserFromJson(Map<String, dynamic> json) => User(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
      email: json['email'] as String,
      phone: json['phone'] as String?,
      role: json['role'] as String,
      avatar: json['avatar'] as String?,
      kycStatus: json['kyc_status'] as String,
      status: json['status'] as String,
      phoneVerifiedAt: json['phone_verified_at'] as String?,
      createdAt: json['created_at'] as String,
      updatedAt: json['updated_at'] as String,
      artisanProfile: json['artisan_profile'] == null
          ? null
          : ArtisanProfile.fromJson(
              json['artisan_profile'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$UserToJson(User instance) => <String, dynamic>{
      'id': instance.id,
      'name': instance.name,
      'email': instance.email,
      'phone': instance.phone,
      'role': instance.role,
      'avatar': instance.avatar,
      'kyc_status': instance.kycStatus,
      'status': instance.status,
      'phone_verified_at': instance.phoneVerifiedAt,
      'created_at': instance.createdAt,
      'updated_at': instance.updatedAt,
      'artisan_profile': instance.artisanProfile,
    };

ArtisanProfile _$ArtisanProfileFromJson(Map<String, dynamic> json) =>
    ArtisanProfile(
      id: (json['id'] as num).toInt(),
      userId: (json['user_id'] as num).toInt(),
      tradeId: (json['trade_id'] as num).toInt(),
      trade: json['trade'] == null
          ? null
          : Trade.fromJson(json['trade'] as Map<String, dynamic>),
      zoneName: json['zone_name'] as String?,
      bio: json['bio'] as String?,
      experienceYears: (json['experience_years'] as num).toInt(),
      available: json['available'] as bool,
      createdAt: json['created_at'] as String,
      updatedAt: json['updated_at'] as String,
    );

Map<String, dynamic> _$ArtisanProfileToJson(ArtisanProfile instance) =>
    <String, dynamic>{
      'id': instance.id,
      'user_id': instance.userId,
      'trade_id': instance.tradeId,
      'trade': instance.trade,
      'zone_name': instance.zoneName,
      'bio': instance.bio,
      'experience_years': instance.experienceYears,
      'available': instance.available,
      'created_at': instance.createdAt,
      'updated_at': instance.updatedAt,
    };

Trade _$TradeFromJson(Map<String, dynamic> json) => Trade(
      id: (json['id'] as num).toInt(),
      sectorId: (json['sector_id'] as num).toInt(),
      code: json['code'] as String,
      name: json['name'] as String,
      sector: json['sector'] == null
          ? null
          : Sector.fromJson(json['sector'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$TradeToJson(Trade instance) => <String, dynamic>{
      'id': instance.id,
      'sector_id': instance.sectorId,
      'code': instance.code,
      'name': instance.name,
      'sector': instance.sector,
    };

Sector _$SectorFromJson(Map<String, dynamic> json) => Sector(
      id: (json['id'] as num).toInt(),
      code: json['code'] as String,
      name: json['name'] as String,
    );

Map<String, dynamic> _$SectorToJson(Sector instance) => <String, dynamic>{
      'id': instance.id,
      'code': instance.code,
      'name': instance.name,
    };
