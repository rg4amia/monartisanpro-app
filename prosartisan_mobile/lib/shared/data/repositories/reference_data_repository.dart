import 'package:dio/dio.dart';
import 'package:get/get.dart';
import '../../models/sector.dart';
import '../../../core/constants/api_constants.dart';
import '../../../core/services/api/api_service.dart';

class ReferenceDataRepository extends GetxService {
  final ApiService _apiService = Get.find<ApiService>();

  /// Récupère tous les secteurs avec leurs métiers associés
  Future<List<Sector>> getSectorsWithTrades() async {
    try {
      final response = await _apiService.get(ApiConstants.trades);

      if (response.statusCode == 200) {
        final List<dynamic> data = response.data['data'];
        return data.map((json) => Sector.fromJson(json)).toList();
      } else {
        throw Exception(
          'Erreur lors de la récupération des secteurs: ${response.statusCode}',
        );
      }
    } on DioException catch (e) {
      throw Exception('Erreur réseau: ${e.message}');
    } catch (e) {
      throw Exception('Erreur inattendue: $e');
    }
  }

  /// Récupère tous les métiers (tous secteurs confondus)
  Future<List<Trade>> getAllTrades() async {
    try {
      final sectors = await getSectorsWithTrades();
      final List<Trade> allTrades = [];

      for (final sector in sectors) {
        if (sector.trades != null) {
          allTrades.addAll(sector.trades!);
        }
      }

      return allTrades;
    } catch (e) {
      throw Exception('Erreur lors de la récupération des métiers: $e');
    }
  }

  /// Récupère les métiers d'un secteur spécifique
  Future<List<Trade>> getTradesBySector(int sectorId) async {
    try {
      final sectors = await getSectorsWithTrades();
      final sector = sectors.firstWhereOrNull((s) => s.id == sectorId);

      if (sector?.trades != null) {
        return sector!.trades!;
      }

      return [];
    } catch (e) {
      throw Exception(
        'Erreur lors de la récupération des métiers du secteur: $e',
      );
    }
  }

  /// Recherche des métiers par nom
  Future<List<Trade>> searchTrades(String query) async {
    try {
      final allTrades = await getAllTrades();

      if (query.isEmpty) {
        return allTrades;
      }

      return allTrades
          .where(
            (trade) =>
                trade.name.toLowerCase().contains(query.toLowerCase()) ||
                trade.code.toLowerCase().contains(query.toLowerCase()),
          )
          .toList();
    } catch (e) {
      throw Exception('Erreur lors de la recherche des métiers: $e');
    }
  }
}
