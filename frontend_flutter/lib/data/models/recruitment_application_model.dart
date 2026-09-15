import 'recruitment_offer_model.dart';

class RecruitmentApplicationModel {
  final int id;
  final int offerId;
  final String status;
  final double? matchingScore;
  final String? appliedAt;
  final RecruitmentOfferModel? offer;

  const RecruitmentApplicationModel({
    required this.id,
    required this.offerId,
    required this.status,
    this.matchingScore,
    this.appliedAt,
    this.offer,
  });

  static int _asInt(dynamic value) {
    if (value is int) return value;
    if (value is double) return value.toInt();
    return int.tryParse('$value') ?? 0;
  }

  static double? _asDouble(dynamic value) {
    if (value == null) return null;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    return double.tryParse('$value');
  }

  factory RecruitmentApplicationModel.fromJson(Map<String, dynamic> json) {
    final offerJson = json['offer'];

    return RecruitmentApplicationModel(
      id: _asInt(json['id']),
      offerId: _asInt(json['offer_id']),
      status: json['status'] as String? ?? 'submitted',
      matchingScore: _asDouble(json['matching_score']),
      appliedAt: json['applied_at'] as String?,
      offer: offerJson is Map<String, dynamic>
          ? RecruitmentOfferModel.fromJson(offerJson)
          : null,
    );
  }
}
