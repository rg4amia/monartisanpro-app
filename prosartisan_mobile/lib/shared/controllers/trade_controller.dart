import 'package:get/get.dart';
import '../models/sector.dart';
import '../models/trade.dart';
import '../data/repositories/trade_repository.dart';

/// Controller for managing trade and sector data
class TradeController extends GetxController {
  final TradeRepository _tradeRepository = TradeRepository();

  final RxList<Sector> sectors = <Sector>[].obs;
  final RxList<Trade> trades = <Trade>[].obs;
  final RxBool isLoading = false.obs;
  final RxString errorMessage = ''.obs;

  @override
  void onInit() {
    super.onInit();
    loadTrades();
  }

  /// Load all trades from the API
  Future<void> loadTrades() async {
    try {
      isLoading.value = true;
      errorMessage.value = '';

      sectors.value = await _tradeRepository.getSectorsWithTrades();
      trades.value = await _tradeRepository.getAllTrades();
    } catch (e) {
      errorMessage.value = e.toString().replaceAll('Exception: ', '');
    } finally {
      isLoading.value = false;
    }
  }

  /// Get trades for a specific sector
  List<Trade> getTradesBySector(int sectorId) {
    return trades.where((trade) => trade.sectorId == sectorId).toList();
  }

  /// Refresh trades data
  Future<void> refreshTrades() async {
    await loadTrades();
  }
}
