import 'package:get/get.dart';

import '../../../core/network/api_client.dart';
import '../../../core/network/api_endpoints.dart';
import '../models/sector_model.dart';
import '../models/trade_model.dart';

class ServicesController extends GetxController {
  final _api = ApiClient();

  final sectors = <SectorModel>[].obs;
  final isLoading = true.obs;
  final selectedSector = Rx<SectorModel?>(null);
  final trades = <TradeModel>[].obs;
  final isLoadingTrades = false.obs;

  @override
  void onInit() {
    super.onInit();
    loadSectors();
  }

  Future<void> loadSectors() async {
    try {
      isLoading.value = true;
      final response = await _api.get(ApiEndpoints.sectors);

      if (response.data['success'] == true) {
        final data = response.data['data'] as List;
        sectors.value = data.map((e) => SectorModel.fromJson(e)).toList();
      }
    } catch (e) {
      Get.snackbar('Erreur', 'Échec du chargement des services : $e');
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> loadTrades(SectorModel sector) async {
    try {
      selectedSector.value = sector;
      isLoadingTrades.value = true;
      trades.clear();

      final response = await _api.get(ApiEndpoints.sectorTrades(sector.id));

      if (response.data['success'] == true) {
        final data = response.data['data'] as List;
        trades.value = data.map((e) => TradeModel.fromJson(e)).toList();
      }
    } catch (e) {
      Get.snackbar('Erreur', 'Échec du chargement des métiers : $e');
    } finally {
      isLoadingTrades.value = false;
    }
  }

  void selectTrade(TradeModel trade) {
    Get.back(result: {
      'sector': selectedSector.value,
      'trade': trade,
    });
  }
}
