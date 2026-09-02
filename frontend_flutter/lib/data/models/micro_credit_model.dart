class MicroCreditEligibilityModel {
  final bool eligible;
  final int currentScore;
  final int requiredScore;
  final int maxAmount;
  final int totalEvaluations;
  final String? reason;

  const MicroCreditEligibilityModel({
    required this.eligible,
    required this.currentScore,
    required this.requiredScore,
    required this.maxAmount,
    required this.totalEvaluations,
    this.reason,
  });

  factory MicroCreditEligibilityModel.fromJson(Map<String, dynamic> json) {
    return MicroCreditEligibilityModel(
      eligible: json['eligible'] as bool? ?? false,
      currentScore: _parseInt(
        json['score_prosartisan'] ??
            json['scoreProsArtisan'] ??
            json['current_score'] ??
            json['currentScore'],
      ),
      requiredScore: _parseInt(
        json['required_score'] ?? json['requiredScore'] ?? 700,
      ),
      maxAmount: _parseInt(json['max_amount'] ?? json['maxAmount']),
      totalEvaluations: _parseInt(
        json['total_evaluations'] ?? json['totalEvaluations'],
      ),
      reason: json['reason']?.toString(),
    );
  }
}

class MicroCreditApplicationModel {
  final int id;
  final int amount;
  final String status;
  final int scoreProsArtisanAtApplication;
  final String? approvedAt;
  final String? externalReference;

  const MicroCreditApplicationModel({
    required this.id,
    required this.amount,
    required this.status,
    required this.scoreProsArtisanAtApplication,
    this.approvedAt,
    this.externalReference,
  });

  factory MicroCreditApplicationModel.fromJson(Map<String, dynamic> json) {
    return MicroCreditApplicationModel(
      id: _parseInt(json['id']),
      amount: _parseInt(json['amount']),
      status: (json['status'] ?? '').toString(),
      scoreProsArtisanAtApplication: _parseInt(
        json['score_prosartisan_at_application'] ??
            json['scoreProsArtisanAtApplication'],
      ),
      approvedAt:
          json['approved_at']?.toString() ?? json['approvedAt']?.toString(),
      externalReference: json['external_reference']?.toString() ??
          json['externalReference']?.toString(),
    );
  }
}

int _parseInt(dynamic value) {
  if (value == null) return 0;
  if (value is int) return value;
  if (value is double) return value.toInt();
  return int.tryParse(value.toString()) ?? 0;
}
