class ReputationProfile {
  final String id;
  final String artisanId;
  final int currentScore;
  final bool isEligibleForMicroCredit;
  final ReputationMetrics metrics;
  final DateTime lastCalculatedAt;
  final DateTime createdAt;
  final DateTime updatedAt;

  const ReputationProfile({
    required this.id,
    required this.artisanId,
    required this.currentScore,
    required this.isEligibleForMicroCredit,
    required this.metrics,
    required this.lastCalculatedAt,
    required this.createdAt,
    required this.updatedAt,
  });

  factory ReputationProfile.fromJson(Map<String, dynamic> json) {
    return ReputationProfile(
      id: json['id'],
      artisanId: json['artisan_id'],
      currentScore: json['current_score'],
      isEligibleForMicroCredit: json['is_eligible_for_micro_credit'],
      metrics: ReputationMetrics.fromJson(json['metrics']),
      lastCalculatedAt: DateTime.parse(json['last_calculated_at']),
      createdAt: DateTime.parse(json['created_at']),
      updatedAt: DateTime.parse(json['updated_at']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'artisan_id': artisanId,
      'current_score': currentScore,
      'is_eligible_for_micro_credit': isEligibleForMicroCredit,
      'metrics': metrics.toJson(),
      'last_calculated_at': lastCalculatedAt.toIso8601String(),
      'created_at': createdAt.toIso8601String(),
      'updated_at': updatedAt.toIso8601String(),
    };
  }
}

class ReputationMetrics {
  final double reliabilityScore;
  final double integrityScore;
  final double qualityScore;
  final double reactivityScore;
  final int completedProjects;
  final int acceptedProjects;
  final double averageRating;
  final double averageResponseTimeHours;
  final int fraudAttempts;

  const ReputationMetrics({
    required this.reliabilityScore,
    required this.integrityScore,
    required this.qualityScore,
    required this.reactivityScore,
    required this.completedProjects,
    required this.acceptedProjects,
    required this.averageRating,
    required this.averageResponseTimeHours,
    required this.fraudAttempts,
  });

  factory ReputationMetrics.fromJson(Map<String, dynamic> json) {
    return ReputationMetrics(
      reliabilityScore: json['reliability_score'].toDouble(),
      integrityScore: json['integrity_score'].toDouble(),
      qualityScore: json['quality_score'].toDouble(),
      reactivityScore: json['reactivity_score'].toDouble(),
      completedProjects: json['completed_projects'],
      acceptedProjects: json['accepted_projects'],
      averageRating: json['average_rating'].toDouble(),
      averageResponseTimeHours: json['average_response_time_hours'].toDouble(),
      fraudAttempts: json['fraud_attempts'],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'reliability_score': reliabilityScore,
      'integrity_score': integrityScore,
      'quality_score': qualityScore,
      'reactivity_score': reactivityScore,
      'completed_projects': completedProjects,
      'accepted_projects': acceptedProjects,
      'average_rating': averageRating,
      'average_response_time_hours': averageResponseTimeHours,
      'fraud_attempts': fraudAttempts,
    };
  }
}
