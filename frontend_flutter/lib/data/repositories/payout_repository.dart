import '../../core/cache/cache_store.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/network/network_executor.dart';
import '../../core/storage/storage_service.dart';
import '../../core/utils/json_readers.dart';
import '../models/payout_model.dart';

/// Versements Mobile Money reçus (artisan, livreur) et retraits des gains
/// livreur (Chantier 10).
class PayoutRepository {
  PayoutRepository({ApiClient? client}) : _client = client ?? ApiClient();

  final ApiClient _client;

  static final CacheStore<Map<String, dynamic>> _store =
      CacheStore<Map<String, dynamic>>(
    boxName: 'payouts_cache',
    fromJson: (j) => j,
    toJson: (m) => m,
  );

  static const Duration _payoutsTtl = Duration(minutes: 2);
  static const Duration _cashoutsTtl = Duration(minutes: 2);

  String get _scope => 'u${StorageService.getUserId() ?? 0}';

  Future<List<PayoutModel>> getPayouts({bool forceRefresh = false}) async {
    await _store.init();
    final rows = await _store.readList(
      key: '${_scope}_payouts',
      ttl: _payoutsTtl,
      policy: forceRefresh ? CachePolicy.networkFirst : CachePolicy.cacheFirst,
      fetch: () async {
        final res = await NetworkExecutor.run(
          () => _client.get(ApiEndpoints.payouts),
        );
        return readDataList(res.data)
            .map((e) => Map<String, dynamic>.from(e))
            .toList();
      },
    );
    return rows.map(PayoutModel.fromJson).toList();
  }

  /// Relance d'un virement échoué par son bénéficiaire. Renvoie le versement
  /// à jour et le message du serveur (succès ou nouvel échec).
  Future<({PayoutModel payout, String message})> retryPayout(int id) async {
    final res = await _client.post(ApiEndpoints.payoutRetry(id));
    // Invalidate payout list after a retry
    await _store.invalidate('${_scope}_payouts');
    final body = readMap(res.data) ?? const {};

    return (
      payout: PayoutModel.fromJson(readMap(body['data']) ?? const {}),
      message: readApiMessage(body) ?? 'Relance effectuée.',
    );
  }

  /// Moyen de remboursement choisi par le client pour un remboursement après
  /// litige non abouti (opérateur + numéro `+225…`). Renvoie le versement à
  /// jour et le message du serveur.
  Future<({PayoutModel payout, String message})> updateDestination(
    int id, {
    required String provider,
    required String phone,
  }) async {
    final res = await _client.put(
      ApiEndpoints.payoutDestination(id),
      data: {'provider': provider, 'phone': phone},
    );
    await _store.init();
    await _store.invalidate('${_scope}_payouts');
    final body = readMap(res.data) ?? const {};

    return (
      payout: PayoutModel.fromJson(readMap(body['data']) ?? const {}),
      message: readApiMessage(body) ?? 'Moyen de remboursement enregistré.',
    );
  }

  Future<({DriverCashoutStats stats, List<DriverCashoutModel> cashouts})>
      getDriverCashouts({bool forceRefresh = false}) async {
    await _store.init();
    final raw = await _store.readOne(
      key: '${_scope}_driver_cashouts',
      ttl: _cashoutsTtl,
      policy: forceRefresh ? CachePolicy.networkFirst : CachePolicy.cacheFirst,
      fetch: () async {
        final res = await NetworkExecutor.run(
          () => _client.get(ApiEndpoints.driverCashouts),
        );
        return readMap(readMap(res.data)?['data']) ?? const {};
      },
    );

    return (
      stats: DriverCashoutStats.fromJson(readMap(raw['stats']) ?? const {}),
      cashouts: readMapList(raw['cashouts'])
          .map(DriverCashoutModel.fromJson)
          .toList(),
    );
  }

  Future<({DriverCashoutModel cashout, String message})> requestDriverCashout({
    required int montant,
    required String mode,
    String? beneficiaryPhone,
    String? bankName,
    String? bankAccountNumber,
  }) async {
    final res = await _client.post(
      ApiEndpoints.driverCashouts,
      data: {
        'montant_brut': montant,
        'mode_retrait': mode,
        if (beneficiaryPhone != null && beneficiaryPhone.isNotEmpty)
          'beneficiary_phone': beneficiaryPhone,
        if (bankName != null && bankName.isNotEmpty) 'bank_name': bankName,
        if (bankAccountNumber != null && bankAccountNumber.isNotEmpty)
          'bank_account_number': bankAccountNumber,
      },
    );
    // Invalidate driver cashouts list after a withdrawal request
    await _store.invalidate('${_scope}_driver_cashouts');
    final body = readMap(res.data) ?? const {};

    return (
      cashout: DriverCashoutModel.fromJson(readMap(body['data']) ?? const {}),
      message: readApiMessage(body) ?? 'Demande de retrait enregistrée.',
    );
  }

  static Future<void> clearCache() async {
    await _store.init();
    await _store.clear();
  }
}
