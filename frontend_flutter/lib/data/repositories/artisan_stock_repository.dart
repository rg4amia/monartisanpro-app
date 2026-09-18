import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/network/network_executor.dart';
import '../models/artisan_stock_model.dart';

/// Accès à l'API du stock personnel de l'artisan (matériaux/outils qu'il
/// possède déjà). Pas de cache local Hive : la liste est toujours rechargée
/// depuis l'API, comme il s'agit d'une donnée propre à l'artisan et amenée à
/// changer fréquemment (ajout/retrait manuel).
class ArtisanStockRepository {
  final ApiClient _client = ApiClient();

  Future<List<ArtisanStockModel>> getStock() async {
    final res = await NetworkExecutor.run(
      () => _client.get(ApiEndpoints.artisanStock),
    );
    return _asList(res.data).map(ArtisanStockModel.fromJson).toList();
  }

  Future<ArtisanStockModel> createStockItem({
    required String description,
    required int quantity,
    required int unitCost,
    required String condition,
  }) async {
    final res = await _client.post(
      ApiEndpoints.artisanStock,
      data: {
        'description': description,
        'quantity': quantity,
        'unit_cost': unitCost,
        'condition': condition,
      },
    );
    return ArtisanStockModel.fromJson(_asObject(res.data));
  }

  Future<ArtisanStockModel> updateStockItem(
    int id, {
    String? description,
    int? quantity,
    int? unitCost,
    String? condition,
  }) async {
    final res = await _client.put(
      ApiEndpoints.artisanStockItem(id),
      data: {
        if (description != null) 'description': description,
        if (quantity != null) 'quantity': quantity,
        if (unitCost != null) 'unit_cost': unitCost,
        if (condition != null) 'condition': condition,
      },
    );
    return ArtisanStockModel.fromJson(_asObject(res.data));
  }

  Future<void> deleteStockItem(int id) async {
    await _client.delete(ApiEndpoints.artisanStockItem(id));
  }

  List<Map<String, dynamic>> _asList(dynamic data) {
    final list = data is Map<String, dynamic> ? data['data'] : null;
    if (list is! List) return const [];
    return list
        .whereType<Map>()
        .map((e) => Map<String, dynamic>.from(e))
        .toList();
  }

  Map<String, dynamic> _asObject(dynamic data) {
    final obj = data is Map<String, dynamic> ? data['data'] : null;
    return obj is Map ? Map<String, dynamic>.from(obj) : <String, dynamic>{};
  }
}
