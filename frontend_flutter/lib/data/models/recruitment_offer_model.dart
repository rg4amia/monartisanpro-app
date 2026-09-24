import 'package:frontend_flutter/core/utils/json_readers.dart';

class RecruitmentOfferModel {
  final int id;
  final String title;
  final String description;
  final String missionType;
  final String commune;
  final String? sousQuartier;
  final String? dateDebut;
  final int? dailyRateMin;
  final int? dailyRateMax;
  final int openingsCount;
  final String? deadlineAt;
  final String status;
  final String? tradeName;
  final String? creatorName;
  final String creatorType;
  final int applicationsCount;

  const RecruitmentOfferModel({
    required this.id,
    required this.title,
    required this.description,
    required this.missionType,
    required this.commune,
    this.sousQuartier,
    this.dateDebut,
    this.dailyRateMin,
    this.dailyRateMax,
    this.openingsCount = 1,
    this.deadlineAt,
    required this.status,
    this.tradeName,
    this.creatorName,
    this.creatorType = 'admin',
    this.applicationsCount = 0,
  });

  static int? _asInt(dynamic value) {
    if (value == null) return null;
    if (value is int) return value;
    if (value is double) return value.toInt();
    return int.tryParse('$value');
  }

  factory RecruitmentOfferModel.fromJson(Map<String, dynamic> json) {
    final trade = json['trade'];
    final creator = json['creator'];

    return RecruitmentOfferModel(
      id: _asInt(json['id']) ?? 0,
      title: readString(json['title']) ?? '',
      description: readString(json['description']) ?? '',
      missionType: readString(json['mission_type']) ?? 'journalier',
      commune: readString(json['commune']) ?? '',
      sousQuartier: readString(json['sous_quartier']),
      dateDebut: readString(json['date_debut']),
      dailyRateMin: _asInt(json['daily_rate_min']),
      dailyRateMax: _asInt(json['daily_rate_max']),
      openingsCount: _asInt(json['openings_count']) ?? 1,
      deadlineAt: readString(json['deadline_at']),
      status: readString(json['status']) ?? 'pending_review',
      tradeName:
          trade is Map<String, dynamic> ? trade['name'] as String? : null,
      creatorName:
          creator is Map<String, dynamic> ? creator['name'] as String? : null,
      creatorType: readString(json['creator_type']) ?? 'admin',
      applicationsCount: _asInt(json['applications_count']) ?? 0,
    );
  }
}
