// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'trade_model.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Sector _$SectorFromJson(Map<String, dynamic> json) => Sector(
      id: (json['id'] as num).toInt(),
      code: json['code'] as String,
      name: json['name'] as String,
      createdAt: json['created_at'] == null
          ? null
          : DateTime.parse(json['created_at'] as String),
      updatedAt: json['updated_at'] == null
          ? null
          : DateTime.parse(json['updated_at'] as String),
    );

Map<String, dynamic> _$SectorToJson(Sector instance) => <String, dynamic>{
      'id': instance.id,
      'code': instance.code,
      'name': instance.name,
      'created_at': instance.createdAt?.toIso8601String(),
      'updated_at': instance.updatedAt?.toIso8601String(),
    };

Trade _$TradeFromJson(Map<String, dynamic> json) => Trade(
      id: (json['id'] as num).toInt(),
      sectorId: (json['sector_id'] as num).toInt(),
      code: json['code'] as String,
      name: json['name'] as String,
      sector: json['sector'] == null
          ? null
          : Sector.fromJson(json['sector'] as Map<String, dynamic>),
      createdAt: json['created_at'] == null
          ? null
          : DateTime.parse(json['created_at'] as String),
      updatedAt: json['updated_at'] == null
          ? null
          : DateTime.parse(json['updated_at'] as String),
    );

Map<String, dynamic> _$TradeToJson(Trade instance) => <String, dynamic>{
      'id': instance.id,
      'sector_id': instance.sectorId,
      'code': instance.code,
      'name': instance.name,
      'sector': instance.sector,
      'created_at': instance.createdAt?.toIso8601String(),
      'updated_at': instance.updatedAt?.toIso8601String(),
    };

Location _$LocationFromJson(Map<String, dynamic> json) => Location(
      latitude: (json['latitude'] as num).toDouble(),
      longitude: (json['longitude'] as num).toDouble(),
    );

Map<String, dynamic> _$LocationToJson(Location instance) => <String, dynamic>{
      'latitude': instance.latitude,
      'longitude': instance.longitude,
    };

ArtisanProfileDetailed _$ArtisanProfileDetailedFromJson(
        Map<String, dynamic> json) =>
    ArtisanProfileDetailed(
      id: (json['id'] as num).toInt(),
      userId: (json['user_id'] as num).toInt(),
      tradeId: (json['trade_id'] as num).toInt(),
      location: json['location'] == null
          ? null
          : Location.fromJson(json['location'] as Map<String, dynamic>),
      zoneName: json['zone_name'] as String?,
      bio: json['bio'] as String?,
      experienceYears: (json['experience_years'] as num).toInt(),
      available: json['available'] as bool,
      trade: json['trade'] == null
          ? null
          : Trade.fromJson(json['trade'] as Map<String, dynamic>),
      createdAt: json['created_at'] == null
          ? null
          : DateTime.parse(json['created_at'] as String),
      updatedAt: json['updated_at'] == null
          ? null
          : DateTime.parse(json['updated_at'] as String),
    );

Map<String, dynamic> _$ArtisanProfileDetailedToJson(
        ArtisanProfileDetailed instance) =>
    <String, dynamic>{
      'id': instance.id,
      'user_id': instance.userId,
      'trade_id': instance.tradeId,
      'location': instance.location,
      'zone_name': instance.zoneName,
      'bio': instance.bio,
      'experience_years': instance.experienceYears,
      'available': instance.available,
      'trade': instance.trade,
      'created_at': instance.createdAt?.toIso8601String(),
      'updated_at': instance.updatedAt?.toIso8601String(),
    };
