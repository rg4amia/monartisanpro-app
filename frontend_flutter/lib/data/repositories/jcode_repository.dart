import 'package:dio/dio.dart';

import '../../core/cache/cache_store.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/network/network_executor.dart';
import '../../core/storage/storage_service.dart';
import '../models/jcode_item_model.dart';
import '../models/jcode_model.dart';
import '../models/jcode_redemption_model.dart';

class JcodeRepository {
  final ApiClient _client = ApiClient();

  static final CacheStore<Map<String, dynamic>> _store =
      CacheStore<Map<String, dynamic>>(
    boxName: 'jcodes_cache',
    fromJson: (j) => j,
    toJson: (m) => m,
  );

  static const Duration _activeTtl = Duration(minutes: 1);
  static const Duration _detailTtl = Duration(minutes: 2);

  String get _scope => 'u${StorageService.getUserId() ?? 0}';

  Future<JcodeModel> createJcode({
    required int missionId,
    int? fournisseurId,
    required List<JcodeItemModel> items,
    int? montant,
  }) async {
    final res = await _client.post(
      ApiEndpoints.jcodes,
      data: {
        'mission_id': missionId,
        'fournisseur_id': fournisseurId,
        if (montant != null) 'montant': montant,
        'items': items.map((item) => item.toRequestJson()).toList(),
      },
    );
    // Invalidate active-jcode cache after creation
    await _store.init();
    await _store.invalidate('${_scope}_active');
    return JcodeModel.fromJson(
      (res.data as Map<String, dynamic>)['data'] as Map<String, dynamic>,
    );
  }

  /// Returns null when the server has no active J-Code for this user.
  /// Uses NetworkExecutor for resilient reads; result cached for [_activeTtl].
  Future<JcodeModel?> getActiveJcode({bool forceRefresh = false}) async {
    try {
      final res = await NetworkExecutor.run(
        () => _client.get(ApiEndpoints.jcodesActive),
      );
      final data = (res.data as Map<String, dynamic>)['data'];
      final json = data is List && data.isNotEmpty
          ? data.first as Map<String, dynamic>
          : data is Map<String, dynamic>
              ? data
              : null;

      await _store.init();
      if (json == null) {
        // Plus de J-Code actif : un repli ultérieur ne doit pas ressusciter
        // l'ancien.
        await _store.invalidate('${_scope}_active');
        return null;
      }

      // Alimente le repli hors ligne ci-dessous.
      await _store.writeOne('${_scope}_active', json);
      return JcodeModel.fromJson(json);
    } catch (_) {
      // Panne réseau : dernier J-Code actif connu, sinon l'erreur remonte.
      // Renvoyer `null` ferait passer une panne pour « aucun J-Code actif »
      // (Règle d'or 29).
      await _store.init();
      final raw = _store.peekOne(
        '${_scope}_active',
        ignoreExpiration: true,
        ttl: _activeTtl,
      );
      if (raw != null) return JcodeModel.fromJson(raw);
      rethrow;
    }
  }

  Future<JcodeModel> getJcode(Object identifier) async {
    await _store.init();

    final raw = await _store.readOne(
      key: '${_scope}_jcode_$identifier',
      ttl: _detailTtl,
      policy: CachePolicy.cacheFirst,
      fetch: () async {
        final res = await NetworkExecutor.run(
          () => _client.get(ApiEndpoints.jcode(identifier)),
        );
        return (res.data as Map<String, dynamic>)['data']
            as Map<String, dynamic>;
      },
    );

    return JcodeModel.fromJson(raw);
  }

  Future<List<JcodeRedemptionModel>> getRedemptions(Object identifier) async {
    final res = await NetworkExecutor.run(
      () => _client.get(ApiEndpoints.jcodeRedemptions(identifier)),
    );
    final data = (res.data as Map<String, dynamic>)['data'];
    if (data is List) {
      return data
          .whereType<Map<String, dynamic>>()
          .map(JcodeRedemptionModel.fromJson)
          .toList();
    }
    return const [];
  }

  /// Supplier scans a J-Code by numeric ID or business code (ex: PA-AB12).
  Future<Map<String, dynamic>> scanJcode({
    required String identifier,
    required double lat,
    required double lng,
    List<Map<String, dynamic>>? servedItems,
    String? recuPhotoPath,
  }) async {
    // Invalidate cached jcode detail after a scan
    await _store.init();
    await _store.invalidate('${_scope}_jcode_$identifier');
    await _store.invalidate('${_scope}_active');

    if (recuPhotoPath != null && recuPhotoPath.isNotEmpty) {
      final formDataMap = <String, dynamic>{
        'lat': lat,
        'lng': lng,
        'recu_photo': await MultipartFile.fromFile(
          recuPhotoPath,
          filename: recuPhotoPath.split('/').last,
        ),
      };
      if (servedItems != null) {
        for (var i = 0; i < servedItems.length; i++) {
          formDataMap['served_items[$i][jcode_item_id]'] =
              servedItems[i]['jcode_item_id'];
          formDataMap['served_items[$i][quantity_served]'] =
              servedItems[i]['quantity_served'];
        }
      }
      final res = await _client.postMultipart(
        ApiEndpoints.scanJcode(identifier),
        FormData.fromMap(formDataMap),
      );
      return res.data as Map<String, dynamic>;
    }

    final res = await _client.post(
      ApiEndpoints.scanJcode(identifier),
      data: {
        'lat': lat,
        'lng': lng,
        if (servedItems != null) 'served_items': servedItems,
      },
    );
    return res.data as Map<String, dynamic>;
  }

  /// Artisan : upload de la photo géolocalisée des matériaux reçus sur
  /// chantier, une fois le J-Code livré par le fournisseur. Notifie le
  /// client côté backend.
  Future<Map<String, dynamic>> uploadPhotoMateriaux({
    required Object identifier,
    required String photoPath,
    required double latitude,
    required double longitude,
  }) async {
    final formData = FormData.fromMap({
      'photo': await MultipartFile.fromFile(
        photoPath,
        filename: photoPath.split('/').last,
      ),
      'latitude': latitude,
      'longitude': longitude,
    });
    final res = await _client.postMultipart(
      ApiEndpoints.jcodePhotoMateriaux(identifier),
      formData,
    );
    return res.data as Map<String, dynamic>;
  }

  static Future<void> clearCache() async {
    await _store.init();
    await _store.clear();
  }
}
