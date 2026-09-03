import '../../core/cache/cache_store.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/network/network_executor.dart';
import '../../core/storage/storage_service.dart';
import '../models/communication_model.dart';

class CommunicationRepository {
  final ApiClient _client = ApiClient();

  static final CacheStore<Map<String, dynamic>> _store =
      CacheStore<Map<String, dynamic>>(
    boxName: 'communications_cache',
    fromJson: (j) => j,
    toJson: (m) => m,
  );

  static const Duration _ttl = Duration(minutes: 10);

  /// Les communications sont filtrées par rôle côté serveur : la clé porte
  /// l'utilisateur pour éviter toute fuite entre comptes sur un même appareil.
  String get _key => 'active_u${StorageService.getUserId() ?? 0}';

  /// Récupère les communications actives (annonces + astuces le_saviez_vous)
  /// pour le rôle actuel de l'utilisateur connecté.
  Future<Map<String, List<CommunicationModel>>> getActiveCommunications({
    bool forceRefresh = false,
  }) async {
    await _store.init();

    final data = await _store.readOne(
      key: _key,
      ttl: _ttl,
      policy: forceRefresh ? CachePolicy.networkFirst : CachePolicy.cacheFirst,
      fetch: () async {
        final res = await NetworkExecutor.run(
          () => _client.get(ApiEndpoints.communicationsActive),
        );
        return (res.data as Map<String, dynamic>)['data']
            as Map<String, dynamic>;
      },
    );

    return {
      'annonces': _parseList(data['annonces']),
      'le_saviez_vous': _parseList(data['le_saviez_vous']),
    };
  }

  Future<void> invalidateCache() async {
    await _store.init();
    await _store.invalidate(_key);
  }

  /// Tolère aussi bien la réponse réseau (`Map<String, dynamic>`) que la
  /// relecture Hive (`Map<dynamic, dynamic>` sur les nœuds imbriqués).
  static List<CommunicationModel> _parseList(Object? raw) {
    if (raw is! List) return const [];
    return raw
        .map(
          (e) => CommunicationModel.fromJson(
            Map<String, dynamic>.from(e as Map),
          ),
        )
        .toList();
  }
}
