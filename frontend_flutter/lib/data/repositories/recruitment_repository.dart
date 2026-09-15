import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/network/network_executor.dart';
import '../models/recruitment_application_model.dart';
import '../models/recruitment_engagement_model.dart';
import '../models/recruitment_offer_model.dart';

class RecruitmentRepository {
  final ApiClient _client = ApiClient();

  List<Map<String, dynamic>> _asMapList(dynamic data) {
    final list =
        data is Map<String, dynamic> && data['data'] is Map<String, dynamic>
            ? (data['data'] as Map<String, dynamic>)['data']
            : null;
    if (list is! List) return const [];
    return list.whereType<Map<String, dynamic>>().toList();
  }

  /// Certains points d'accès (candidatures d'une offre) renvoient une liste
  /// simple `{success, data: [...]}`, sans pagination imbriquée.
  List<Map<String, dynamic>> _asFlatList(dynamic data) {
    final list = data is Map<String, dynamic> ? data['data'] : null;
    if (list is! List) return const [];
    return list.whereType<Map<String, dynamic>>().toList();
  }

  /// Offres actives, filtrables par métier et commune (première page).
  Future<List<RecruitmentOfferModel>> listOffers({
    int? tradeId,
    String? commune,
  }) async {
    final res = await NetworkExecutor.run(
      () => _client.get(
        ApiEndpoints.recruitmentOffers,
        params: {
          if (tradeId != null) 'trade_id': tradeId,
          if (commune != null && commune.isNotEmpty) 'commune': commune,
        },
      ),
    );
    return _asMapList(res.data).map(RecruitmentOfferModel.fromJson).toList();
  }

  /// Offres publiées par l'utilisateur connecté (client, fournisseur, admin).
  Future<List<RecruitmentOfferModel>> myOffers() async {
    final res = await NetworkExecutor.run(
      () => _client.get(ApiEndpoints.myRecruitmentOffers),
    );
    return _asMapList(res.data).map(RecruitmentOfferModel.fromJson).toList();
  }

  /// Candidatures de l'artisan connecté.
  Future<List<RecruitmentApplicationModel>> myApplications() async {
    final res = await NetworkExecutor.run(
      () => _client.get(ApiEndpoints.myRecruitmentApplications),
    );
    return _asMapList(res.data)
        .map(RecruitmentApplicationModel.fromJson)
        .toList();
  }

  Future<RecruitmentOfferModel> createOffer({
    required int tradeId,
    required String title,
    required String description,
    required String missionType,
    required String commune,
    String? sousQuartier,
    String? dateDebut,
    int? dailyRateMin,
    int? dailyRateMax,
    int openingsCount = 1,
    String? deadlineAt,
  }) async {
    final res = await NetworkExecutor.run(
      () => _client.post(
        ApiEndpoints.recruitmentOffers,
        data: {
          'trade_id': tradeId,
          'title': title,
          'description': description,
          'mission_type': missionType,
          'commune': commune,
          if (sousQuartier != null && sousQuartier.isNotEmpty)
            'sous_quartier': sousQuartier,
          if (dateDebut != null) 'date_debut': dateDebut,
          if (dailyRateMin != null) 'daily_rate_min': dailyRateMin,
          if (dailyRateMax != null) 'daily_rate_max': dailyRateMax,
          'openings_count': openingsCount,
          if (deadlineAt != null) 'deadline_at': deadlineAt,
        },
      ),
    );
    final data = res.data;
    final offerJson =
        data is Map<String, dynamic> && data['data'] is Map<String, dynamic>
            ? data['data'] as Map<String, dynamic>
            : <String, dynamic>{};
    return RecruitmentOfferModel.fromJson(offerJson);
  }

  Future<void> applyToOffer(int offerId) async {
    await NetworkExecutor.run(
      () => _client.post(ApiEndpoints.recruitmentOfferApply(offerId)),
    );
  }

  /// Candidatures reçues sur une offre appartenant à l'utilisateur connecté.
  Future<List<RecruitmentApplicationModel>> getOfferApplications(
    int offerId,
  ) async {
    final res = await NetworkExecutor.run(
      () => _client.get(ApiEndpoints.recruitmentOfferApplications(offerId)),
    );
    return _asFlatList(
      res.data,
    ).map(RecruitmentApplicationModel.fromJson).toList();
  }

  Future<void> updateApplicationStatus(
    int offerId,
    int applicationId,
    String status,
  ) async {
    await NetworkExecutor.run(
      () => _client.patch(
        ApiEndpoints.recruitmentApplicationStatus(offerId, applicationId),
        data: {'status': status},
      ),
    );
  }

  Map<String, dynamic> _asObject(dynamic data) {
    final obj = data is Map<String, dynamic> ? data['data'] : null;
    return obj is Map<String, dynamic> ? obj : <String, dynamic>{};
  }

  /// Confirme un candidat et crée l'engagement journalier séquestré.
  Future<RecruitmentEngagementModel> createEngagement({
    required int applicationId,
    required int dailyRate,
    int? totalDays,
  }) async {
    final res = await NetworkExecutor.run(
      () => _client.post(
        ApiEndpoints.recruitmentEngage(applicationId),
        data: {
          'daily_rate': dailyRate,
          if (totalDays != null) 'total_days': totalDays,
        },
      ),
    );
    return RecruitmentEngagementModel.fromJson(_asObject(res.data));
  }

  Future<List<RecruitmentEngagementModel>> myEngagements() async {
    final res = await NetworkExecutor.run(
      () => _client.get(ApiEndpoints.myRecruitmentEngagements),
    );
    return _asFlatList(
      res.data,
    ).map(RecruitmentEngagementModel.fromJson).toList();
  }

  Future<RecruitmentEngagementModel> showEngagement(int id) async {
    final res = await NetworkExecutor.run(
      () => _client.get(ApiEndpoints.recruitmentEngagement(id)),
    );
    return RecruitmentEngagementModel.fromJson(_asObject(res.data));
  }

  Future<RecruitmentEngagementModel> acceptEngagement(int id) async {
    final res = await NetworkExecutor.run(
      () => _client.post(ApiEndpoints.recruitmentEngagementAccept(id)),
    );
    return RecruitmentEngagementModel.fromJson(_asObject(res.data));
  }

  Future<void> declineEngagement(int id) async {
    await NetworkExecutor.run(
      () => _client.post(ApiEndpoints.recruitmentEngagementDecline(id)),
    );
  }

  Future<RecruitmentEngagementModel> extendEngagement(
    int id,
    int additionalDays,
  ) async {
    final res = await NetworkExecutor.run(
      () => _client.post(
        ApiEndpoints.recruitmentEngagementExtend(id),
        data: {'additional_days': additionalDays},
      ),
    );
    return RecruitmentEngagementModel.fromJson(_asObject(res.data));
  }

  /// Initie le paiement du séquestre (Wave/Orange Money) ; renvoie l'URL de
  /// paiement et l'identifiant de transaction à activer une fois confirmé.
  Future<Map<String, dynamic>> initiateEscrowPayment(
    int engagementId, {
    required String provider,
    required String phone,
  }) async {
    final res = await NetworkExecutor.run(
      () => _client.post(
        ApiEndpoints.recruitmentEngagementPay(engagementId),
        data: {'provider': provider, 'phone': phone},
      ),
    );
    return _asObject(res.data);
  }

  Future<RecruitmentEngagementModel> activateEscrow(
    int engagementId,
    int transactionId,
  ) async {
    final res = await NetworkExecutor.run(
      () => _client.post(
        ApiEndpoints.recruitmentEngagementActivate(engagementId),
        data: {'transaction_id': transactionId},
      ),
    );
    return RecruitmentEngagementModel.fromJson(_asObject(res.data));
  }

  Future<void> validateWorkday(int engagementId, int workdayId) async {
    await NetworkExecutor.run(
      () => _client.post(
        ApiEndpoints.recruitmentWorkdayValidate(engagementId, workdayId),
      ),
    );
  }
}
