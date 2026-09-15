import 'dart:async';

import 'package:dio/dio.dart';
import 'package:get/get.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_endpoints.dart';
import '../../../data/models/recruitment_offer_model.dart';
import '../../../data/repositories/recruitment_repository.dart';
import '../../services/models/sector_model.dart';
import '../../services/models/trade_model.dart';

/// Espace client / fournisseur : publier une offre de recrutement et suivre
/// ses offres déjà publiées.
class RecruitmentPublishController extends GetxController {
  final RecruitmentRepository _repo = RecruitmentRepository();
  final ApiClient _api = ApiClient();

  final myOffers = <RecruitmentOfferModel>[].obs;
  final isLoading = false.obs;
  final isSubmitting = false.obs;

  final sectors = <SectorModel>[].obs;
  final trades = <TradeModel>[].obs;
  final selectedSector = Rx<SectorModel?>(null);
  final selectedTrade = Rx<TradeModel?>(null);
  final isLoadingTrades = false.obs;

  @override
  void onInit() {
    super.onInit();
    unawaited(loadMyOffers());
    unawaited(loadSectors());
  }

  Future<void> loadMyOffers() async {
    isLoading.value = true;
    try {
      myOffers.value = await _repo.myOffers();
    } catch (_) {
      Get.snackbar('Erreur', 'Impossible de charger vos offres publiées.');
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> loadSectors() async {
    try {
      final response = await _api.get(ApiEndpoints.sectors);
      final data = response.data;
      if (data is Map<String, dynamic> &&
          data['success'] == true &&
          data['data'] is List) {
        sectors.value = (data['data'] as List)
            .whereType<Map<String, dynamic>>()
            .map(SectorModel.fromJson)
            .toList();
      }
    } catch (_) {
      // Les secteurs se rechargeront à la prochaine ouverture du formulaire.
    }
  }

  Future<void> selectSector(SectorModel sector) async {
    selectedSector.value = sector;
    selectedTrade.value = null;
    trades.clear();
    isLoadingTrades.value = true;
    try {
      final response = await _api.get(ApiEndpoints.sectorTrades(sector.id));
      final data = response.data;
      if (data is Map<String, dynamic> &&
          data['success'] == true &&
          data['data'] is List) {
        trades.value = (data['data'] as List)
            .whereType<Map<String, dynamic>>()
            .map(TradeModel.fromJson)
            .toList();
      }
    } catch (_) {
      Get.snackbar(
        'Erreur',
        'Impossible de charger les métiers de ce secteur.',
      );
    } finally {
      isLoadingTrades.value = false;
    }
  }

  void selectTrade(TradeModel trade) {
    selectedTrade.value = trade;
  }

  /// Retourne `true` si l'offre a bien été créée.
  Future<bool> publish({
    required String title,
    required String description,
    required String missionType,
    required String commune,
    String? sousQuartier,
    int? dailyRateMin,
    int? dailyRateMax,
    int openingsCount = 1,
    String? deadlineAt,
  }) async {
    final trade = selectedTrade.value;
    if (trade == null) {
      Get.snackbar('Métier requis', 'Sélectionnez un métier pour cette offre.');
      return false;
    }

    isSubmitting.value = true;
    try {
      await _repo.createOffer(
        tradeId: trade.id,
        title: title,
        description: description,
        missionType: missionType,
        commune: commune,
        sousQuartier: sousQuartier,
        dailyRateMin: dailyRateMin,
        dailyRateMax: dailyRateMax,
        openingsCount: openingsCount,
        deadlineAt: deadlineAt,
      );
      await loadMyOffers();
      return true;
    } on DioException catch (e) {
      final responseData = e.response?.data;
      final message = responseData is Map<String, dynamic>
          ? responseData['message'] as String?
          : null;
      Get.snackbar(
        'Publication impossible',
        message ?? 'Une erreur est survenue.',
      );
      return false;
    } catch (_) {
      Get.snackbar('Publication impossible', 'Une erreur est survenue.');
      return false;
    } finally {
      isSubmitting.value = false;
    }
  }
}
