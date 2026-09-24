import 'package:frontend_flutter/core/utils/json_readers.dart';

import 'recruitment_offer_model.dart';

class RecruitmentApplicantModel {
  final int id;
  final String name;
  final String phone;
  final int scoreProsartisan;

  const RecruitmentApplicantModel({
    required this.id,
    required this.name,
    required this.phone,
    this.scoreProsartisan = 0,
  });

  factory RecruitmentApplicantModel.fromJson(Map<String, dynamic> json) {
    return RecruitmentApplicantModel(
      id: readInt(json['id']) ?? 0,
      name: readString(json['name']) ?? '',
      phone: readString(json['phone']) ?? '',
      scoreProsartisan: readInt(json['score_prosartisan']) ?? 0,
    );
  }
}

class RecruitmentApplicationModel {
  final int id;
  final int offerId;
  final String status;
  final double? matchingScore;
  final String? appliedAt;
  final RecruitmentOfferModel? offer;
  final RecruitmentApplicantModel? artisan;
  final int? engagementId;

  const RecruitmentApplicationModel({
    required this.id,
    required this.offerId,
    required this.status,
    this.matchingScore,
    this.appliedAt,
    this.offer,
    this.artisan,
    this.engagementId,
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
    final artisanJson = json['artisan'];
    final engagementJson = json['engagement'];

    return RecruitmentApplicationModel(
      id: _asInt(json['id']),
      offerId: _asInt(json['offer_id']),
      status: readString(json['status']) ?? 'submitted',
      matchingScore: _asDouble(json['matching_score']),
      appliedAt: readString(json['applied_at']),
      offer: offerJson is Map<String, dynamic>
          ? RecruitmentOfferModel.fromJson(offerJson)
          : null,
      artisan: artisanJson is Map<String, dynamic>
          ? RecruitmentApplicantModel.fromJson(artisanJson)
          : null,
      engagementId: engagementJson is Map<String, dynamic>
          ? _asInt(engagementJson['id'])
          : null,
    );
  }
}
