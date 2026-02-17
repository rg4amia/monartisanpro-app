import 'package:json_annotation/json_annotation.dart';

part 'scoring_model.g.dart';

@JsonSerializable()
class ArtisanScore {
  final int id;
  @JsonKey(name: 'artisan_id')
  final int artisanId;
  @JsonKey(name: 'total_score')
  final double totalScore;
  @JsonKey(name: 'reliability_score')
  final double reliabilityScore;
  @JsonKey(name: 'integrity_score')
  final double integrityScore;
  @JsonKey(name: 'quality_score')
  final double qualityScore;
  @JsonKey(name: 'responsiveness_score')
  final double responsivenessScore;
  @JsonKey(name: 'professionalism_score')
  final double professionalismScore;
  @JsonKey(name: 'projects_completed')
  final int projectsCompleted;
  @JsonKey(name: 'projects_cancelled')
  final int projectsCancelled;
  @JsonKey(name: 'average_rating')
  final double averageRating;
  @JsonKey(name: 'total_reviews')
  final int totalReviews;
  @JsonKey(name: 'fraud_incidents')
  final int fraudIncidents;
  @JsonKey(name: 'token_misuses')
  final int tokenMisuses;
  @JsonKey(name: 'last_calculated_at')
  final DateTime lastCalculatedAt;
  @JsonKey(name: 'created_at')
  final DateTime createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime updatedAt;

  ArtisanScore({
    required this.id,
    required this.artisanId,
    required this.totalScore,
    required this.reliabilityScore,
    required this.integrityScore,
    required this.qualityScore,
    required this.responsivenessScore,
    required this.professionalismScore,
    required this.projectsCompleted,
    required this.projectsCancelled,
    required this.averageRating,
    required this.totalReviews,
    required this.fraudIncidents,
    required this.tokenMisuses,
    required this.lastCalculatedAt,
    required this.createdAt,
    required this.updatedAt,
  });

  factory ArtisanScore.fromJson(Map<String, dynamic> json) =>
      _$ArtisanScoreFromJson(json);
  Map<String, dynamic> toJson() => _$ArtisanScoreToJson(this);

  // Helper getters
  String get formattedScore => totalScore.toStringAsFixed(0);
  String get scoreLabel {
    if (totalScore >= 90) return 'Excellent';
    if (totalScore >= 75) return 'Très Bon';
    if (totalScore >= 60) return 'Bon';
    if (totalScore >= 45) return 'Moyen';
    return 'Faible';
  }

  String get badgeLevel {
    if (totalScore >= 90) return 'gold';
    if (totalScore >= 75) return 'silver';
    if (totalScore >= 60) return 'bronze';
    return 'none';
  }

  double get completionRate {
    final total = projectsCompleted + projectsCancelled;
    if (total == 0) return 0;
    return (projectsCompleted / total) * 100;
  }

  bool get hasGoodReputation => totalScore >= 60 && fraudIncidents == 0;
  bool get isNewArtisan => projectsCompleted < 5;
  bool get isExperienced => projectsCompleted >= 20;

  // Component scores breakdown
  Map<String, double> get scoreBreakdown => {
        'Fiabilité': reliabilityScore,
        'Intégrité': integrityScore,
        'Qualité': qualityScore,
        'Réactivité': responsivenessScore,
        'Professionnalisme': professionalismScore,
      };
}

@JsonSerializable()
class ScoreHistory {
  final int id;
  @JsonKey(name: 'artisan_id')
  final int artisanId;
  @JsonKey(name: 'score_before')
  final double scoreBefore;
  @JsonKey(name: 'score_after')
  final double scoreAfter;
  @JsonKey(name: 'change_reason')
  final String changeReason;
  @JsonKey(name: 'related_project_id')
  final int? relatedProjectId;
  @JsonKey(name: 'related_review_id')
  final int? relatedReviewId;
  @JsonKey(name: 'created_at')
  final DateTime createdAt;

  ScoreHistory({
    required this.id,
    required this.artisanId,
    required this.scoreBefore,
    required this.scoreAfter,
    required this.changeReason,
    this.relatedProjectId,
    this.relatedReviewId,
    required this.createdAt,
  });

  factory ScoreHistory.fromJson(Map<String, dynamic> json) =>
      _$ScoreHistoryFromJson(json);
  Map<String, dynamic> toJson() => _$ScoreHistoryToJson(this);

  // Helper getters
  double get scoreDifference => scoreAfter - scoreBefore;
  bool get isIncrease => scoreDifference > 0;
  bool get isDecrease => scoreDifference < 0;

  String get formattedChange {
    final sign = isIncrease ? '+' : '';
    return '$sign${scoreDifference.toStringAsFixed(1)}';
  }
}

@JsonSerializable()
class Review {
  final int id;
  @JsonKey(name: 'project_id')
  final int projectId;
  @JsonKey(name: 'client_id')
  final int clientId;
  @JsonKey(name: 'artisan_id')
  final int artisanId;
  final int rating; // 1-5 stars
  final String? comment;
  @JsonKey(name: 'quality_rating')
  final int qualityRating;
  @JsonKey(name: 'communication_rating')
  final int communicationRating;
  @JsonKey(name: 'timeliness_rating')
  final int timelinessRating;
  @JsonKey(name: 'professionalism_rating')
  final int professionalismRating;
  @JsonKey(name: 'would_recommend')
  final bool wouldRecommend;
  @JsonKey(name: 'photos')
  final List<String>? photos;
  @JsonKey(name: 'artisan_response')
  final String? artisanResponse;
  @JsonKey(name: 'responded_at')
  final DateTime? respondedAt;
  @JsonKey(name: 'created_at')
  final DateTime createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime updatedAt;

  Review({
    required this.id,
    required this.projectId,
    required this.clientId,
    required this.artisanId,
    required this.rating,
    this.comment,
    required this.qualityRating,
    required this.communicationRating,
    required this.timelinessRating,
    required this.professionalismRating,
    required this.wouldRecommend,
    this.photos,
    this.artisanResponse,
    this.respondedAt,
    required this.createdAt,
    required this.updatedAt,
  });

  factory Review.fromJson(Map<String, dynamic> json) =>
      _$ReviewFromJson(json);
  Map<String, dynamic> toJson() => _$ReviewToJson(this);

  // Helper getters
  double get averageDetailedRating {
    return (qualityRating +
            communicationRating +
            timelinessRating +
            professionalismRating) /
        4;
  }

  bool get hasPhotos => photos != null && photos!.isNotEmpty;
  bool get hasResponse => artisanResponse != null && artisanResponse!.isNotEmpty;
  bool get isPositive => rating >= 4;
  bool get isNegative => rating <= 2;

  String get ratingLabel {
    switch (rating) {
      case 5:
        return 'Excellent';
      case 4:
        return 'Très bon';
      case 3:
        return 'Bon';
      case 2:
        return 'Moyen';
      case 1:
        return 'Mauvais';
      default:
        return 'N/A';
    }
  }
}

@JsonSerializable()
class CreateReviewRequest {
  @JsonKey(name: 'project_id')
  final int projectId;
  final int rating;
  final String? comment;
  @JsonKey(name: 'quality_rating')
  final int qualityRating;
  @JsonKey(name: 'communication_rating')
  final int communicationRating;
  @JsonKey(name: 'timeliness_rating')
  final int timelinessRating;
  @JsonKey(name: 'professionalism_rating')
  final int professionalismRating;
  @JsonKey(name: 'would_recommend')
  final bool wouldRecommend;
  final List<String>? photos;

  CreateReviewRequest({
    required this.projectId,
    required this.rating,
    this.comment,
    required this.qualityRating,
    required this.communicationRating,
    required this.timelinessRating,
    required this.professionalismRating,
    required this.wouldRecommend,
    this.photos,
  });

  Map<String, dynamic> toJson() => _$CreateReviewRequestToJson(this);
}

@JsonSerializable()
class ReviewStats {
  @JsonKey(name: 'total_reviews')
  final int totalReviews;
  @JsonKey(name: 'average_rating')
  final double averageRating;
  @JsonKey(name: 'five_star_count')
  final int fiveStarCount;
  @JsonKey(name: 'four_star_count')
  final int fourStarCount;
  @JsonKey(name: 'three_star_count')
  final int threeStarCount;
  @JsonKey(name: 'two_star_count')
  final int twoStarCount;
  @JsonKey(name: 'one_star_count')
  final int oneStarCount;
  @JsonKey(name: 'recommendation_percentage')
  final double recommendationPercentage;

  ReviewStats({
    required this.totalReviews,
    required this.averageRating,
    required this.fiveStarCount,
    required this.fourStarCount,
    required this.threeStarCount,
    required this.twoStarCount,
    required this.oneStarCount,
    required this.recommendationPercentage,
  });

  factory ReviewStats.fromJson(Map<String, dynamic> json) =>
      _$ReviewStatsFromJson(json);
  Map<String, dynamic> toJson() => _$ReviewStatsToJson(this);

  // Helper getters
  double getPercentageForRating(int rating) {
    if (totalReviews == 0) return 0;
    int count;
    switch (rating) {
      case 5:
        count = fiveStarCount;
        break;
      case 4:
        count = fourStarCount;
        break;
      case 3:
        count = threeStarCount;
        break;
      case 2:
        count = twoStarCount;
        break;
      case 1:
        count = oneStarCount;
        break;
      default:
        count = 0;
    }
    return (count / totalReviews) * 100;
  }
}
