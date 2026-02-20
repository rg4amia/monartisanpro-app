import 'package:dio/dio.dart';
import '../../shared/models/auth_response.dart';
import '../../shared/models/trade_model.dart';
import 'dio_client.dart';

class TradeService {
  final Dio _dio = DioClient().dio;

  /// Get all sectors
  Future<ApiResponse<List<Sector>>> getSectors() async {
    try {
      final response = await _dio.get('/sectors');

      return ApiResponse<List<Sector>>.fromJson(
        response.data,
        (json) => (json as List)
            .map((sector) => Sector.fromJson(sector as Map<String, dynamic>))
            .toList(),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<List<Sector>>.fromJson(
          e.response!.data,
          (json) => [],
        );
      }
      rethrow;
    }
  }

  /// Get all trades
  Future<ApiResponse<List<Trade>>> getAllTrades() async {
    try {
      final response = await _dio.get('/trades');

      return ApiResponse<List<Trade>>.fromJson(
        response.data,
        (json) => (json as List)
            .map((trade) => Trade.fromJson(trade as Map<String, dynamic>))
            .toList(),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<List<Trade>>.fromJson(
          e.response!.data,
          (json) => [],
        );
      }
      rethrow;
    }
  }

  /// Get trades by sector
  Future<ApiResponse<List<Trade>>> getTradesBySector(int sectorId) async {
    try {
      final response = await _dio.get('/sectors/$sectorId/trades');

      return ApiResponse<List<Trade>>.fromJson(response.data, (json) {
        // Backend returns {sector: {...}, trades: [...]}
        // We need to extract the trades array
        if (json is Map<String, dynamic> && json.containsKey('trades')) {
          return (json['trades'] as List)
              .map((trade) => Trade.fromJson(trade as Map<String, dynamic>))
              .toList();
        }
        // Fallback if data is already a list
        if (json is List) {
          return json
              .map((trade) => Trade.fromJson(trade as Map<String, dynamic>))
              .toList();
        }
        return [];
      });
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<List<Trade>>.fromJson(
          e.response!.data,
          (json) => [],
        );
      }
      rethrow;
    }
  }

  /// Get trade details
  Future<ApiResponse<Trade>> getTrade(int tradeId) async {
    try {
      final response = await _dio.get('/trades/$tradeId');

      return ApiResponse<Trade>.fromJson(
        response.data,
        (json) => Trade.fromJson(json as Map<String, dynamic>),
      );
    } on DioException catch (e) {
      if (e.response != null) {
        return ApiResponse<Trade>.fromJson(
          e.response!.data,
          (json) => Trade.fromJson(json as Map<String, dynamic>),
        );
      }
      rethrow;
    }
  }
}
