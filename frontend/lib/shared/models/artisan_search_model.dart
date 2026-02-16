import 'package:json_annotation/json_annotation.dart';
import 'user_model.dart';
import 'trade_model.dart';

part 'artisan_search_model.g.dart';

@JsonSerializable()
class ArtisanSearchResult {
  final int id;
  final User user;
  @JsonKey(name: 'artisan_profile')
  final ArtisanProfileDetailed? artisanProfile;
  final double? distance; // in meters
  @JsonKey(name: 'is_nearby')
  final bool isNearby; // <2km
  @JsonKey(name: 'fuzzy_location')
  final Location? fuzzyLocation; // ±50m offset for privacy
  @JsonKey(name: 'average_rating')
  final double? averageRating;
  @JsonKey(name: 'reviews_count')
  final int reviewsCount;
  @JsonKey(name: 'projects_completed')
  final int projectsCompleted;
  @JsonKey(name: 'nzassa_score')
  final int? nzassaScore;

  ArtisanSearchResult({
    required this.id,
    required this.user,
    this.artisanProfile,
    this.distance,
    required this.isNearby,
    this.fuzzyLocation,
    this.averageRating,
    required this.reviewsCount,
    required this.projectsCompleted,
    this.nzassaScore,
  });

  factory ArtisanSearchResult.fromJson(Map<String, dynamic> json) =>
      _$ArtisanSearchResultFromJson(json);
  Map<String, dynamic> toJson() => _$ArtisanSearchResultToJson(this);

  // Helper getters
  String get name => user.name;
  String? get avatar => user.avatar;
  String get tradeName => artisanProfile?.trade?.name ?? 'Artisan';
  int get experienceYears => artisanProfile?.experienceYears ?? 0;
  bool get isAvailable => artisanProfile?.available ?? false;
  String? get zoneName => artisanProfile?.zoneName;

  String get distanceText {
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
