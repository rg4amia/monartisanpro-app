import 'package:get/get.dart';
import '../../../core/services/api/api_service.dart';
import '../../../core/constants/api_constants.dart';
import '../../models/sector.dart';
import '../../models/trade.dart';

/// Repository for fetching trade and sector data
class TradeRepository {
  final ApiService _apiService = Get.find<ApiService>();

  /// Fetch all sectors with their trades
  Future<List<Sector>> getSectorsWithTrades() async {
    try {
      final response = await _apiService.get(ApiConstants.trades);

      final data = response.data['data'] as List<dynamic>;
      return data
          .map((sector) => Sector.fromJson(sector as Map<String, dynamic>))
          .toList();
    } catch (e) {
      throw Exception('Failed to load trades: $e');
    }
  }

  /// Fetch all trades (flattened list)
  Future<List<Trade>> getAllTrades() async {
    try {
      final sectors = await getSectorsWithTrades();
      final List<Trade> allTrades = [];

      for (var sector in sectors) {
        for (var trade in sector.trades) {
          allTrades.add(
            Trade(
              id: trade.id,
              code: trade.code,
              name: trade.name,
              sectorId: trade.sectorId,
              sectorName: sector.name,
            ),
          );
        }
      }

      return allTrades;
    } catch (e) {
      throw Exception('Failed to load trades: $e');
    }
  }
}
