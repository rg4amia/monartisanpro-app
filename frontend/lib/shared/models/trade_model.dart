import 'package:json_annotation/json_annotation.dart';

part 'trade_model.g.dart';

@JsonSerializable()
class Sector {
  final int id;
  final String code;
  final String name;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;

  Sector({
    required this.id,
    required this.code,
    required this.name,
    this.createdAt,
    this.updatedAt,
  });

  factory Sector.fromJson(Map<String, dynamic> json) =>
      _$SectorFromJson(json);
  Map<String, dynamic> toJson() => _$SectorToJson(this);
}

@JsonSerializable()
class Trade {
  final int id;
  @JsonKey(name: 'sector_id')
  final int sectorId;
  final String code;
  final String name;
  final Sector? sector;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;

  Trade({
    required this.id,
    required this.sectorId,
    required this.code,
    required this.name,
    this.sector,
    this.createdAt,
    this.updatedAt,
  });

  factory Trade.fromJson(Map<String, dynamic> json) => _$TradeFromJson(json);
  Map<String, dynamic> toJson() => _$TradeToJson(this);
}

@JsonSerializable()
class Location {
  final double latitude;
  final double longitude;

  Location({
    required this.latitude,
    required this.longitude,
  });

  factory Location.fromJson(Map<String, dynamic> json) =>
      _$LocationFromJson(json);
  Map<String, dynamic> toJson() => _$LocationToJson(this);
}

@JsonSerializable()
class ArtisanProfileDetailed {
  final int id;
  @JsonKey(name: 'user_id')
  final int userId;
  @JsonKey(name: 'trade_id')
  final int tradeId;
  final Location? location;
  @JsonKey(name: 'zone_name')
  final String? zoneName;
  final String? bio;
  @JsonKey(name: 'experience_years')
  final int experienceYears;
  final bool available;
  final Trade? trade;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;

  ArtisanProfileDetailed({
    required this.id,
    required this.userId,
    required this.tradeId,
    this.location,
    this.zoneName,
    this.bio,
    required this.experienceYears,
    required this.available,
    this.trade,
    this.createdAt,
    this.updatedAt,
  });

  factory ArtisanProfileDetailed.fromJson(Map<String, dynamic> json) =>
      _$ArtisanProfileDetailedFromJson(json);
  Map<String, dynamic> toJson() => _$ArtisanProfileDetailedToJson(this);
}
