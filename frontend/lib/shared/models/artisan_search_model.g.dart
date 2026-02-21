// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'artisan_search_model.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ArtisanSearchResult _$ArtisanSearchResultFromJson(Map<String, dynamic> json) =>
    ArtisanSearchResult(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
      email: json['email'] as String,
      phone: json['phone'] as String?,
      avatar: json['avatar'] as String?,
      tradeId: (json['trade_id'] as num?)?.toInt(),
      tradeName: json['trade_name'] as String?,
      sectorName: json['sector_name'] as String?,
      zoneName: json['zone_name'] as String?,
      bio: json['bio'] as String?,
      experienceYears: (json['experience_years'] as num).toInt(),
      available: json['available'] as bool,
      distance: (json['distance'] as num?)?.toDouble(),
      distanceText: json['distance_text'] as String?,
      isNearby: json['is_nearby'] as bool? ?? false,
      latitude: (json['latitude'] as num?)?.toDouble(),
      longitude: (json['longitude'] as num?)?.toDouble(),
      nzassaScore: (json['nzassa_score'] as num?)?.toInt(),
      averageRating: (json['average_rating'] as num?)?.toDouble(),
      reviewsCount: (json['reviews_count'] as num?)?.toInt() ?? 0,
      projectsCompleted: (json['projects_completed'] as num?)?.toInt() ?? 0,
    );

Map<String, dynamic> _$ArtisanSearchResultToJson(
        ArtisanSearchResult instance) =>
    <String, dynamic>{
      'id': instance.id,
      'name': instance.name,
      'email': instance.email,
      'phone': instance.phone,
      'avatar': instance.avatar,
      'trade_id': instance.tradeId,
      'trade_name': instance.tradeName,
      'sector_name': instance.sectorName,
      'zone_name': instance.zoneName,
      'bio': instance.bio,
      'experience_years': instance.experienceYears,
      'available': instance.available,
      'distance': instance.distance,
      'distance_text': instance.distanceText,
      'is_nearby': instance.isNearby,
      'latitude': instance.latitude,
      'longitude': instance.longitude,
      'nzassa_score': instance.nzassaScore,
      'average_rating': instance.averageRating,
      'reviews_count': instance.reviewsCount,
      'projects_completed': instance.projectsCompleted,
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
