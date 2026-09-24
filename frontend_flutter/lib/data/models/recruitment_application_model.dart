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

  /// Note vocale de candidature : l'URL (signée, 15 min) et la transcription
  /// ne sont fournies qu'une fois la note validée sans coordonnées.
  final String? voiceNoteUrl;
  final String? voiceTranscription;
  final int? voiceNoteDuration;

  /// pending · approved · contact_detected · failed — null sans note vocale.
  final String? voiceStatus;

  const RecruitmentApplicationModel({
    required this.id,
    required this.offerId,
    required this.status,
    this.matchingScore,
    this.appliedAt,
    this.offer,
    this.artisan,
    this.engagementId,
    this.voiceNoteUrl,
    this.voiceTranscription,
    this.voiceNoteDuration,
    this.voiceStatus,
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
      voiceNoteUrl: readString(json['voice_note_url']),
      voiceTranscription: readString(json['voice_transcription']),
      voiceNoteDuration: readInt(json['voice_note_duration']),
      voiceStatus: readString(json['voice_status']),
    );
  }
}
