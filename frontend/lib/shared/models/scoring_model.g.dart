// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'scoring_model.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ArtisanScore _$ArtisanScoreFromJson(Map<String, dynamic> json) => ArtisanScore(
      id: (json['id'] as num).toInt(),
      artisanId: (json['artisan_id'] as num).toInt(),
      totalScore: (json['total_score'] as num).toDouble(),
      reliabilityScore: (json['reliability_score'] as num).toDouble(),
      integrityScore: (json['integrity_score'] as num).toDouble(),
      qualityScore: (json['quality_score'] as num).toDouble(),
      responsivenessScore: (json['responsiveness_score'] as num).toDouble(),
      professionalismScore: (json['professionalism_score'] as num).toDouble(),
      projectsCompleted: (json['projects_completed'] as num).toInt(),
      projectsCancelled: (json['projects_cancelled'] as num).toInt(),
      averageRating: (json['average_rating'] as num).toDouble(),
      totalReviews: (json['total_reviews'] as num).toInt(),
      fraudIncidents: (json['fraud_incidents'] as num).toInt(),
      tokenMisuses: (json['token_misuses'] as num).toInt(),
      lastCalculatedAt: DateTime.parse(json['last_calculated_at'] as String),
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
    );

Map<String, dynamic> _$ArtisanScoreToJson(ArtisanScore instance) =>
    <String, dynamic>{
      'id': instance.id,
      'artisan_id': instance.artisanId,
      'total_score': instance.totalScore,
      'reliability_score': instance.reliabilityScore,
      'integrity_score': instance.integrityScore,
      'quality_score': instance.qualityScore,
      'responsiveness_score': instance.responsivenessScore,
      'professionalism_score': instance.professionalismScore,
      'projects_completed': instance.projectsCompleted,
      'projects_cancelled': instance.projectsCancelled,
      'average_rating': instance.averageRating,
      'total_reviews': instance.totalReviews,
      'fraud_incidents': instance.fraudIncidents,
      'token_misuses': instance.tokenMisuses,
      'last_calculated_at': instance.lastCalculatedAt.toIso8601String(),
      'created_at': instance.createdAt.toIso8601String(),
      'updated_at': instance.updatedAt.toIso8601String(),
    };

ScoreHistory _$ScoreHistoryFromJson(Map<String, dynamic> json) => ScoreHistory(
      id: (json['id'] as num).toInt(),
      artisanId: (json['artisan_id'] as num).toInt(),
      scoreBefore: (json['score_before'] as num).toDouble(),
      scoreAfter: (json['score_after'] as num).toDouble(),
      changeReason: json['change_reason'] as String,
      relatedProjectId: (json['related_project_id'] as num?)?.toInt(),
      relatedReviewId: (json['related_review_id'] as num?)?.toInt(),
      createdAt: DateTime.parse(json['created_at'] as String),
    );

Map<String, dynamic> _$ScoreHistoryToJson(ScoreHistory instance) =>
    <String, dynamic>{
      'id': instance.id,
      'artisan_id': instance.artisanId,
      'score_before': instance.scoreBefore,
      'score_after': instance.scoreAfter,
      'change_reason': instance.changeReason,
      'related_project_id': instance.relatedProjectId,
      'related_review_id': instance.relatedReviewId,
      'created_at': instance.createdAt.toIso8601String(),
    };

Review _$ReviewFromJson(Map<String, dynamic> json) => Review(
      id: (json['id'] as num).toInt(),
      projectId: (json['project_id'] as num).toInt(),
      clientId: (json['client_id'] as num).toInt(),
      artisanId: (json['artisan_id'] as num).toInt(),
      rating: (json['rating'] as num).toInt(),
      comment: json['comment'] as String?,
      qualityRating: (json['quality_rating'] as num).toInt(),
      communicationRating: (json['communication_rating'] as num).toInt(),
      timelinessRating: (json['timeliness_rating'] as num).toInt(),
      professionalismRating: (json['professionalism_rating'] as num).toInt(),
      wouldRecommend: json['would_recommend'] as bool,
      photos:
          (json['photos'] as List<dynamic>?)?.map((e) => e as String).toList(),
      artisanResponse: json['artisan_response'] as String?,
      respondedAt: json['responded_at'] == null
          ? null
          : DateTime.parse(json['responded_at'] as String),
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
    );

Map<String, dynamic> _$ReviewToJson(Review instance) => <String, dynamic>{
      'id': instance.id,
      'project_id': instance.projectId,
      'client_id': instance.clientId,
      'artisan_id': instance.artisanId,
      'rating': instance.rating,
      'comment': instance.comment,
      'quality_rating': instance.qualityRating,
      'communication_rating': instance.communicationRating,
      'timeliness_rating': instance.timelinessRating,
      'professionalism_rating': instance.professionalismRating,
      'would_recommend': instance.wouldRecommend,
      'photos': instance.photos,
      'artisan_response': instance.artisanResponse,
      'responded_at': instance.respondedAt?.toIso8601String(),
      'created_at': instance.createdAt.toIso8601String(),
      'updated_at': instance.updatedAt.toIso8601String(),
    };

CreateReviewRequest _$CreateReviewRequestFromJson(Map<String, dynamic> json) =>
    CreateReviewRequest(
      projectId: (json['project_id'] as num).toInt(),
      rating: (json['rating'] as num).toInt(),
      comment: json['comment'] as String?,
      qualityRating: (json['quality_rating'] as num).toInt(),
      communicationRating: (json['communication_rating'] as num).toInt(),
      timelinessRating: (json['timeliness_rating'] as num).toInt(),
      professionalismRating: (json['professionalism_rating'] as num).toInt(),
      wouldRecommend: json['would_recommend'] as bool,
      photos:
          (json['photos'] as List<dynamic>?)?.map((e) => e as String).toList(),
    );

Map<String, dynamic> _$CreateReviewRequestToJson(
        CreateReviewRequest instance) =>
    <String, dynamic>{
      'project_id': instance.projectId,
      'rating': instance.rating,
      'comment': instance.comment,
      'quality_rating': instance.qualityRating,
      'communication_rating': instance.communicationRating,
      'timeliness_rating': instance.timelinessRating,
      'professionalism_rating': instance.professionalismRating,
      'would_recommend': instance.wouldRecommend,
      'photos': instance.photos,
    };

ReviewStats _$ReviewStatsFromJson(Map<String, dynamic> json) => ReviewStats(
      totalReviews: (json['total_reviews'] as num).toInt(),
      averageRating: (json['average_rating'] as num).toDouble(),
      fiveStarCount: (json['five_star_count'] as num).toInt(),
      fourStarCount: (json['four_star_count'] as num).toInt(),
      threeStarCount: (json['three_star_count'] as num).toInt(),
      twoStarCount: (json['two_star_count'] as num).toInt(),
      oneStarCount: (json['one_star_count'] as num).toInt(),
      recommendationPercentage:
          (json['recommendation_percentage'] as num).toDouble(),
    );

Map<String, dynamic> _$ReviewStatsToJson(ReviewStats instance) =>
    <String, dynamic>{
      'total_reviews': instance.totalReviews,
      'average_rating': instance.averageRating,
      'five_star_count': instance.fiveStarCount,
      'four_star_count': instance.fourStarCount,
      'three_star_count': instance.threeStarCount,
      'two_star_count': instance.twoStarCount,
      'one_star_count': instance.oneStarCount,
      'recommendation_percentage': instance.recommendationPercentage,
    };
