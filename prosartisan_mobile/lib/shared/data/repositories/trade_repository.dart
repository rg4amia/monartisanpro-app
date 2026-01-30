import 'package:get/get.dart';
import '../../../core/services/api/api_service.dart';
import '../../../core/constants/api_constants.dart';
import '../../models/sector.dart';
import '../../models/trade.dart';

/// Repository for fetching trade and sector data
class TradeRepository {
  final ApiService _apiService = Get.find<ApiService>();

  /// Fetch all sectors
  Future<List<Sector>> getSectors() async {
    try {
      final response = await _apiService.get(ApiConstants.sectors);

      final data = response.data['data'] as List<dynamic>;
      return data
          .map((sector) => Sector.fromJson(sector as Map<String, dynamic>))
          .toList();
    } catch (e) {
      throw Exception('Failed to load sectors: $e');
    }
  }

  /// Fetch trades for a specific sector
  Future<List<Trade>> getTradesBySector(int sectorId) async {
    try {
      final endpoint = ApiConstants.tradesBySector.replaceAll(
        '{sectorId}',
        sectorId.toString(),
      );
      final response = await _apiService.get(endpoint);

      final data = response.data['data'] as List<dynamic>;
      return data
          .map((trade) => Trade.fromJson(trade as Map<String, dynamic>))
          .toList();
    } catch (e) {
      throw Exception('Failed to load trades for sector: $e');
    }
  }

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
      final response = await _apiService.get(ApiConstants.allTrades);

      final data = response.data['data'] as List<dynamic>;
      return data
          .map((trade) => Trade.fromJson(trade as Map<String, dynamic>))
          .toList();
    } catch (e) {
      throw Exception('Failed to load all trades: $e');
    }
  }
}
