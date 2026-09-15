class RecruitmentWorkdayModel {
  final int id;
  final int dayNumber;
  final int montant;
  final String status;
  final String? validatedAt;

  const RecruitmentWorkdayModel({
    required this.id,
    required this.dayNumber,
    required this.montant,
    required this.status,
    this.validatedAt,
  });

  static int _asInt(dynamic value) {
    if (value is int) return value;
    if (value is double) return value.toInt();
    return int.tryParse('$value') ?? 0;
  }

  factory RecruitmentWorkdayModel.fromJson(Map<String, dynamic> json) {
    return RecruitmentWorkdayModel(
      id: _asInt(json['id']),
      dayNumber: _asInt(json['day_number']),
      montant: _asInt(json['montant']),
      status: json['status'] as String? ?? 'awaiting_payment',
      validatedAt: json['validated_at'] as String?,
    );
  }
}

class RecruitmentEngagementModel {
  final int id;
  final int offerId;
  final int applicationId;
  final int artisanId;
  final int recruiterId;
  final int dailyRate;
  final int totalDays;
  final int montantTotal;
  final String status;
  final String? acceptedAt;
  final String? offerTitle;
  final String? artisanName;
  final String? artisanPhone;
  final String? recruiterName;
  final String? recruiterPhone;
  final List<RecruitmentWorkdayModel> workdays;

  const RecruitmentEngagementModel({
    required this.id,
    required this.offerId,
    required this.applicationId,
    required this.artisanId,
    required this.recruiterId,
    required this.dailyRate,
    required this.totalDays,
    required this.montantTotal,
    required this.status,
    this.acceptedAt,
    this.offerTitle,
    this.artisanName,
    this.artisanPhone,
    this.recruiterName,
    this.recruiterPhone,
    this.workdays = const [],
  });

  static int _asInt(dynamic value) {
    if (value is int) return value;
    if (value is double) return value.toInt();
    return int.tryParse('$value') ?? 0;
  }

  int get unpaidAmount => workdays
      .where((w) => w.status == 'awaiting_payment')
      .fold(0, (sum, w) => sum + w.montant);

  int get validatedDays =>
      workdays.where((w) => w.status == 'validated').length;

  factory RecruitmentEngagementModel.fromJson(Map<String, dynamic> json) {
    final offer = json['offer'];
    final artisan = json['artisan'];
    final recruiter = json['recruiter'];
    final workdaysJson = json['workdays'];

    return RecruitmentEngagementModel(
      id: _asInt(json['id']),
      offerId: _asInt(json['offer_id']),
      applicationId: _asInt(json['application_id']),
      artisanId: _asInt(json['artisan_id']),
      recruiterId: _asInt(json['recruiter_id']),
      dailyRate: _asInt(json['daily_rate']),
      totalDays: _asInt(json['total_days']),
      montantTotal: _asInt(json['montant_total']),
      status: json['status'] as String? ?? 'pending_artisan_acceptance',
      acceptedAt: json['accepted_at'] as String?,
      offerTitle:
          offer is Map<String, dynamic> ? offer['title'] as String? : null,
      artisanName:
          artisan is Map<String, dynamic> ? artisan['name'] as String? : null,
      artisanPhone:
          artisan is Map<String, dynamic> ? artisan['phone'] as String? : null,
      recruiterName: recruiter is Map<String, dynamic>
          ? recruiter['name'] as String?
          : null,
      recruiterPhone: recruiter is Map<String, dynamic>
          ? recruiter['phone'] as String?
          : null,
      workdays: workdaysJson is List
          ? workdaysJson
              .whereType<Map<String, dynamic>>()
              .map(RecruitmentWorkdayModel.fromJson)
              .toList()
          : const [],
    );
  }
}
