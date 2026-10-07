import '../../core/cache/cache_store.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/network/network_executor.dart';
import '../../core/storage/storage_service.dart';
import '../../core/utils/json_readers.dart';
import '../models/supplier_cashout_model.dart';

class SupplierCashoutRepository {
  final ApiClient _client = ApiClient();

  static final CacheStore<Map<String, dynamic>> _store =
      CacheStore<Map<String, dynamic>>(
    boxName: 'supplier_cashouts_cache',
    fromJson: (j) => j,
    toJson: (m) => m,
  );

  static const Duration _ttl = Duration(minutes: 2);

  String get _key => 'cashouts_u${StorageService.getUserId() ?? 0}';

  Future<Map<String, dynamic>> getCashouts({
    int page = 1,
    bool forceRefresh = false,
  }) async {
    await _store.init();
    final cacheKey = '${_key}_p$page';

    final raw = await _store.readOne(
      key: cacheKey,
      ttl: _ttl,
      policy: forceRefresh ? CachePolicy.networkFirst : CachePolicy.cacheFirst,
      fetch: () async {
        final res = await NetworkExecutor.run(
          () => _client.get(
            ApiEndpoints.supplierCashouts,
            params: {'page': page},
          ),
        );

        final data = requireDataMap(res.data);
        return data;
      },
    );

    final statsJson = readMap(raw['stats']) ?? {};
    final cashoutsData = readMap(raw['cashouts']) ?? {};
    final list = (readList(cashoutsData['data']) ?? [])
        .map(
          (e) => SupplierCashoutModel.fromJson(
            requireMap(e),
          ),
        )
        .toList();

    return {
      'stats': SupplierCashoutStatsModel.fromJson(statsJson),
      'cashouts': list,
      'total': readInt(cashoutsData['total']) ?? list.length,
    };
  }

  Future<SupplierCashoutModel> requestCashout({
    required int montantBrut,
    required String modeRetrait,
    String? beneficiaryName,
    String? beneficiaryPhone,
    String? bankName,
    String? bankAccountNumber,
    String? notes,
  }) async {
    final payload = {
      'montant_brut': montantBrut,
      'mode_retrait': modeRetrait,
      if (beneficiaryName != null && beneficiaryName.isNotEmpty)
        'beneficiary_name': beneficiaryName,
      if (beneficiaryPhone != null && beneficiaryPhone.isNotEmpty)
        'beneficiary_phone': beneficiaryPhone,
      if (bankName != null && bankName.isNotEmpty) 'bank_name': bankName,
      if (bankAccountNumber != null && bankAccountNumber.isNotEmpty)
        'bank_account_number': bankAccountNumber,
      if (notes != null && notes.isNotEmpty) 'notes': notes,
    };

    final res = await _client.post(
      ApiEndpoints.supplierCashouts,
      data: payload,
    );

    // Invalidate all page caches after a cashout request
    await _store.init();
    await _store.clear();

    final cashoutJson =
        requireDataMap(res.data);
    return SupplierCashoutModel.fromJson(cashoutJson);
  }

  String getReceiptUrl(int id) => ApiEndpoints.supplierCashoutReceipt(id);

  static Future<void> clearCache() async {
    await _store.init();
    await _store.clear();
  }
}
