// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'artisan_search_model.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ArtisanSearchResult _$ArtisanSearchResultFromJson(Map<String, dynamic> json) =>
    ArtisanSearchResult(
      id: (json['id'] as num).toInt(),
      user: User.fromJson(json['user'] as Map<String, dynamic>),
      artisanProfile: json['artisan_profile'] == null
          ? null
          : ArtisanProfileDetailed.fromJson(
              json['artisan_profile'] as Map<String, dynamic>),
      distance: (json['distance'] as num?)?.toDouble(),
      isNearby: json['is_nearby'] as bool,
      fuzzyLocation: json['fuzzy_location'] == null
          ? null
          : Location.fromJson(json['fuzzy_location'] as Map<String, dynamic>),
      averageRating: (json['average_rating'] as num?)?.toDouble(),
      reviewsCount: (json['reviews_count'] as num).toInt(),
      projectsCompleted: (json['projects_completed'] as num).toInt(),
      nzassaScore: (json['nzassa_score'] as num?)?.toInt(),
    );

Map<String, dynamic> _$ArtisanSearchResultToJson(
        ArtisanSearchResult instance) =>
    <String, dynamic>{
      'id': instance.id,
      'user': instance.user,
      'artisan_profile': instance.artisanProfile,
      'distance': instance.distance,
      'is_nearby': instance.isNearby,
      'fuzzy_location': instance.fuzzyLocation,
      'average_rating': instance.averageRating,
      'reviews_count': instance.reviewsCount,
      'projects_completed': instance.projectsCompleted,
      'nzassa_score': instance.nzassaScore,
    };

ClusterMarker _$ClusterMarkerFromJson(Map<String, dynamic> json) =>
    ClusterMarker(
      lat: (json['lat'] as num).toDouble(),
      lng: (json['lng'] as num).toDouble(),
      count: (json['count'] as num).toInt(),
      artisans: (json['artisans'] as List<dynamic>)
          .map((e) => (e as num).toInt())
          .toList(),
    );

Map<String, dynamic> _$ClusterMarkerToJson(ClusterMarker instance) =>
    <String, dynamic>{
      'lat': instance.lat,
      'lng': instance.lng,
      'count': instance.count,
      'artisans': instance.artisans,
    };
