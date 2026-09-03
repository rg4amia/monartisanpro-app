import 'package:dio/dio.dart';

import '../../core/cache/cache_store.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/network/network_executor.dart';
import '../../core/storage/storage_service.dart';
import '../models/supplier_model.dart';
import '../models/supplier_product_model.dart';

class SupplierCatalogRepository {
  final ApiClient _client = ApiClient();

  static final CacheStore<Map<String, dynamic>> _store =
      CacheStore<Map<String, dynamic>>(
    boxName: 'supplier_catalog_cache',
    fromJson: (j) => j,
    toJson: (m) => m,
  );

  static const Duration _suppliersTtl = Duration(minutes: 5);
  static const Duration _productsTtl = Duration(minutes: 5);

  String get _userScope => 'u${StorageService.getUserId() ?? 0}';

  Future<List<SupplierModel>> getApprovedSuppliers({
    String? search,
    bool forceRefresh = false,
  }) async {
    await _store.init();
    final term = (search ?? '').trim();
    final rows = await _store.readList(
      key: '${_userScope}_suppliers_${term.toLowerCase()}',
      ttl: _suppliersTtl,
      policy: forceRefresh ? CachePolicy.networkFirst : CachePolicy.cacheFirst,
      fetch: () async {
        final res = await NetworkExecutor.run(
          () => _client.get(
            ApiEndpoints.fournisseurs,
            params: {
              if (term.isNotEmpty) 'search': term,
            },
          ),
        );
        return _rawList(res.data);
      },
    );
    return rows.map(SupplierModel.fromJson).toList();
  }

  Future<List<SupplierProductModel>> getSupplierProducts(
    int supplierId, {
    bool forceRefresh = false,
  }) async {
    await _store.init();
    final rows = await _store.readList(
      key: '${_userScope}_products_supplier_$supplierId',
      ttl: _productsTtl,
      policy: forceRefresh ? CachePolicy.networkFirst : CachePolicy.cacheFirst,
      fetch: () async {
        final res = await NetworkExecutor.run(
          () => _client.get(ApiEndpoints.fournisseurArticles(supplierId)),
        );
        return _rawList(res.data);
      },
    );
    return rows.map(SupplierProductModel.fromJson).toList();
  }

  Future<List<SupplierProductModel>> getMyProducts({
    bool forceRefresh = false,
  }) async {
    await _store.init();
    final rows = await _store.readList(
      key: '${_userScope}_my_products',
      ttl: _productsTtl,
      policy: forceRefresh ? CachePolicy.networkFirst : CachePolicy.cacheFirst,
      fetch: () async {
        final res = await NetworkExecutor.run(
          () => _client.get(ApiEndpoints.supplierProducts),
        );
        return _rawList(res.data);
      },
    );
    return rows.map(SupplierProductModel.fromJson).toList();
  }

  Future<SupplierProductModel> createProduct(
    SupplierProductModel product,
  ) async {
    final res = await _client.post(
      ApiEndpoints.supplierProducts,
      data: product.toRequestJson(),
    );
    await _invalidateProductCaches();
    return SupplierProductModel.fromJson(
      (res.data as Map<String, dynamic>)['data'] as Map<String, dynamic>,
    );
  }

  Future<SupplierProductModel> updateProduct(
    SupplierProductModel product,
  ) async {
    final res = await _client.put(
      ApiEndpoints.supplierProduct(product.id),
      data: product.toRequestJson(),
    );
    await _invalidateProductCaches();
    return SupplierProductModel.fromJson(
      (res.data as Map<String, dynamic>)['data'] as Map<String, dynamic>,
    );
  }

  Future<void> archiveProduct(int productId) async {
    await _client.delete(ApiEndpoints.supplierProduct(productId));
    await _invalidateProductCaches();
  }

  Future<String> uploadProductImage(String filePath) async {
    final formData = FormData.fromMap({
      'file': await MultipartFile.fromFile(filePath, filename: 'product.jpg'),
    });
    final res = await _client.postMultipart('/upload', formData);
    return (res.data as Map<String, dynamic>)['url'] as String;
  }

  /// Après une mutation de fiche produit : seul le catalogue « mes produits »
  /// est certain d'avoir changé ; les catalogues par fournisseur sont purgés
  /// de façon large car la clé n'expose pas le fournisseur propriétaire.
  Future<void> _invalidateProductCaches() async {
    await _store.init();
    await _store.invalidate('${_userScope}_my_products');
  }

  /// Extrait la liste `data` de la réponse et normalise chaque élément en
  /// `Map<String, dynamic>` (tolère la relecture Hive `Map<dynamic, dynamic>`).
  static List<Map<String, dynamic>> _rawList(Object? data) {
    final list = (data as Map<String, dynamic>)['data'] as List<dynamic>;
    return list.map((e) => Map<String, dynamic>.from(e as Map)).toList();
  }
}
