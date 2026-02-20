import 'package:json_annotation/json_annotation.dart';

part 'artisan_search_model.g.dart';

@JsonSerializable()
class ArtisanSearchResult {
  final int id;
  final String name;
  final String email;
  final String? phone;
  final String? avatar;
  @JsonKey(name: 'trade_id')
  final int? tradeId;
  @JsonKey(name: 'trade_name')
  final String? tradeName;
  @JsonKey(name: 'sector_name')
  final String? sectorName;
  @JsonKey(name: 'zone_name')
  final String? zoneName;
  final String? bio;
  @JsonKey(name: 'experience_years')
  final int experienceYears;
  final bool available;
  final double? distance; // in meters
  @JsonKey(name: 'distance_text')
  final String? distanceText;
  @JsonKey(name: 'is_nearby')
  final bool isNearby; // <2km
  final double? latitude;
  final double? longitude;
  @JsonKey(name: 'nzassa_score')
  final int? nzassaScore;
  @JsonKey(name: 'average_rating')
  final double? averageRating;
  @JsonKey(name: 'reviews_count')
  final int reviewsCount;
  @JsonKey(name: 'projects_completed')
  final int projectsCompleted;

  ArtisanSearchResult({
    required this.id,
    required this.name,
    required this.email,
    this.phone,
    this.avatar,
    this.tradeId,
    this.tradeName,
    this.sectorName,
    this.zoneName,
    this.bio,
    required this.experienceYears,
    required this.available,
    this.distance,
    this.distanceText,
    required this.isNearby,
    this.latitude,
    this.longitude,
    this.nzassaScore,
    this.averageRating,
    required this.reviewsCount,
    required this.projectsCompleted,
  });

  factory ArtisanSearchResult.fromJson(Map<String, dynamic> json) =>
      _$ArtisanSearchResultFromJson(json);
  Map<String, dynamic> toJson() => _$ArtisanSearchResultToJson(this);

  // Helper getters
  String? get badgeLevel {
    if (nzassaScore == null) return null;
    if (nzassaScore! >= 80) return 'gold';
    if (nzassaScore! >= 60) return 'silver';
    if (nzassaScore! >= 40) return 'bronze';
    return 'none';
  }

  int get totalReviews => reviewsCount;

  String get formattedDistance {
    if (distanceText != null) return distanceText!;
    if (distance == null) return '';
    if (distance! < 1000) {
      return '${distance!.round()}m';
    }
    return '${(distance! / 1000).toStringAsFixed(1)}km';
  }
}

@JsonSerializable()
class ClusterMarker {
  final double lat;
  final double lng;
  final int count;
  final List<int> artisans;

  ClusterMarker({
    required this.lat,
    required this.lng,
    required this.count,
    required this.artisans,
  });

  factory ClusterMarker.fromJson(Map<String, dynamic> json) =>
      _$ClusterMarkerFromJson(json);
  Map<String, dynamic> toJson() => _$ClusterMarkerToJson(this);
}
