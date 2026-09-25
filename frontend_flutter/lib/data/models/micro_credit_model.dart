import 'package:frontend_flutter/core/utils/json_readers.dart';

class MicroCreditEligibilityModel {
  final bool eligible;
  final int currentScore;
  final int requiredScore;
  final int maxAmount;
  final int totalEvaluations;
  final String? reason;
  final bool hasActiveCredit;
  final MicroCreditApplicationModel? activeCredit;

  const MicroCreditEligibilityModel({
    required this.eligible,
    required this.currentScore,
    required this.requiredScore,
    required this.maxAmount,
    required this.totalEvaluations,
    this.reason,
    this.hasActiveCredit = false,
    this.activeCredit,
  });

  factory MicroCreditEligibilityModel.fromJson(Map<String, dynamic> json) {
    final activeCreditJson = json['active_credit'] ?? json['activeCredit'];
    return MicroCreditEligibilityModel(
      eligible: readBool(json['eligible']) ?? false,
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
      hasActiveCredit: readBool(json['has_active_credit'] ?? json['hasActiveCredit']) ?? false,
      activeCredit: activeCreditJson is Map<String, dynamic>
          ? MicroCreditApplicationModel.fromJson(activeCreditJson)
          : null,
    );
  }
}

class MicroCreditApplicationModel {
  final int id;
  final int amount;
  final int repaidAmount;
  final int remainingAmount;
  final String status;
  final int scoreProsArtisanAtApplication;
  final String? approvedAt;
  final String? disbursedAt;
  final String? repaidAt;
  final String? externalReference;

  const MicroCreditApplicationModel({
    required this.id,
    required this.amount,
    this.repaidAmount = 0,
    int? remainingAmount,
    required this.status,
    required this.scoreProsArtisanAtApplication,
    this.approvedAt,
    this.disbursedAt,
    this.repaidAt,
    this.externalReference,
  }) : remainingAmount = remainingAmount ?? (amount - repaidAmount);

  factory MicroCreditApplicationModel.fromJson(Map<String, dynamic> json) {
    final amt = _parseInt(json['amount']);
    final repaid = _parseInt(json['repaid_amount'] ?? json['repaidAmount']);
    final remaining = json['remaining_amount'] != null || json['remainingAmount'] != null
        ? _parseInt(json['remaining_amount'] ?? json['remainingAmount'])
        : (amt - repaid);

    return MicroCreditApplicationModel(
      id: _parseInt(json['id']),
      amount: amt,
      repaidAmount: repaid,
      remainingAmount: remaining > 0 ? remaining : 0,
      status: (json['status'] ?? '').toString(),
      scoreProsArtisanAtApplication: _parseInt(
        json['score_prosartisan_at_application'] ??
            json['scoreProsArtisanAtApplication'],
      ),
      approvedAt:
          json['approved_at']?.toString() ?? json['approvedAt']?.toString(),
      disbursedAt:
          json['disbursed_at']?.toString() ?? json['disbursedAt']?.toString(),
      repaidAt:
          json['repaid_at']?.toString() ?? json['repaidAt']?.toString(),
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
