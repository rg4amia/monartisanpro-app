import '../../core/cache/cache_store.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/network/network_executor.dart';
import '../../core/storage/storage_service.dart';
import '../models/devis_model.dart';

class DevisRepository {
  final ApiClient _client = ApiClient();

  static final CacheStore<DevisModel> _store = CacheStore<DevisModel>(
    boxName: 'devis_cache',
    fromJson: DevisModel.fromJson,
    toJson: (d) => d.toJson(),
  );

  static const Duration _ttl = Duration(minutes: 3);

  String get _scope => 'u${StorageService.getUserId() ?? 0}';
  String _missionKey(int missionId) => '${_scope}_mission_$missionId';
  String _devisKey(int id) => '${_scope}_devis_$id';

  Future<List<DevisModel>> getMissionDevis(
    int missionId, {
    bool forceRefresh = false,
  }) async {
    await _store.init();
    return _store.readList(
      key: _missionKey(missionId),
      ttl: _ttl,
      policy: forceRefresh ? CachePolicy.networkFirst : CachePolicy.cacheFirst,
      fetch: () async {
        final res = await NetworkExecutor.run(
          () => _client.get(ApiEndpoints.missionDevis(missionId)),
        );
        final list = res.data['data'] as List<dynamic>;
        return list
            .map((e) => DevisModel.fromJson(e as Map<String, dynamic>))
            .toList();
      },
    );
  }

  Future<DevisModel> getDevis(int id, {bool forceRefresh = false}) async {
    await _store.init();
    return _store.readOne(
      key: _devisKey(id),
      ttl: _ttl,
      policy: forceRefresh ? CachePolicy.networkFirst : CachePolicy.cacheFirst,
      fetch: () async {
        final res = await NetworkExecutor.run(
          () => _client.get(ApiEndpoints.devis(id)),
        );
        return DevisModel.fromJson(res.data['data'] as Map<String, dynamic>);
      },
    );
  }

  Future<DevisModel> createDevis({
    required int missionId,
    required List<DevisLigne> lignes,
    required List<DevisJalon> jalons,
  }) async {
    final res = await _client.post(ApiEndpoints.missionDevis(missionId), data: {
      'lignes_json': lignes.map((l) => l.toJson()).toList(),
      'jalons_json': jalons.map((j) => j.toJson()).toList(),
    });
    final devis = DevisModel.fromJson(res.data['data'] as Map<String, dynamic>);
    await _invalidate(missionId: missionId, devisId: devis.id);
    return devis;
  }

  Future<DevisModel> updateDevis({
    required int id,
    required List<DevisLigne> lignes,
    required List<DevisJalon> jalons,
  }) async {
    final res = await _client.put(ApiEndpoints.devis(id), data: {
      'lignes_json': lignes.map((l) => l.toJson()).toList(),
      'jalons_json': jalons.map((j) => j.toJson()).toList(),
    });
    final devis = DevisModel.fromJson(res.data['data'] as Map<String, dynamic>);
    await _invalidate(missionId: devis.missionId, devisId: id);
    return devis;
  }

  Future<DevisModel> acceptDevis(int id, {required int transactionId}) async {
    final res = await _client.post(
      ApiEndpoints.acceptDevis(id),
      data: {'transaction_id': transactionId},
    );
    final devis = DevisModel.fromJson(res.data['data'] as Map<String, dynamic>);
    await _invalidate(missionId: devis.missionId, devisId: id);
    return devis;
  }

  Future<void> refuseDevis(int id) async {
    await _client.post(ApiEndpoints.refuseDevis(id));
    await _store.invalidate(_devisKey(id));
  }

  Future<Map<String, dynamic>> getDevisSuggestion(int missionId) async {
    final res = await NetworkExecutor.run(
      () => _client.get(ApiEndpoints.missionDevisSuggest(missionId)),
    );
    return res.data['data'] as Map<String, dynamic>;
  }

  Future<void> _invalidate({int? missionId, int? devisId}) async {
    await _store.init();
    if (missionId != null) await _store.invalidate(_missionKey(missionId));
    if (devisId != null) await _store.invalidate(_devisKey(devisId));
  }
}
