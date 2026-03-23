import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../models/supplier_model.dart';
import '../models/supplier_product_model.dart';

class SupplierCatalogRepository {
  final ApiClient _client = ApiClient();

  Future<List<SupplierModel>> getApprovedSuppliers({String? search}) async {
    final res = await _client.get(
      ApiEndpoints.fournisseurs,
      params: {
        if (search != null && search.trim().isNotEmpty) 'search': search
      },
    );

    final list = res.data['data'] as List<dynamic>;
    return list
        .map((e) => SupplierModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<List<SupplierProductModel>> getSupplierProducts(int supplierId) async {
    final res = await _client.get(ApiEndpoints.fournisseurArticles(supplierId));
    final list = res.data['data'] as List<dynamic>;
    return list
        .map((e) => SupplierProductModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<List<SupplierProductModel>> getMyProducts() async {
    final res = await _client.get(ApiEndpoints.supplierProducts);
    final list = res.data['data'] as List<dynamic>;
    return list
        .map((e) => SupplierProductModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<SupplierProductModel> createProduct(
      SupplierProductModel product) async {
    final res = await _client.post(
      ApiEndpoints.supplierProducts,
      data: product.toRequestJson(),
    );
    return SupplierProductModel.fromJson(
        res.data['data'] as Map<String, dynamic>);
  }

  Future<SupplierProductModel> updateProduct(
      SupplierProductModel product) async {
    final res = await _client.put(
      ApiEndpoints.supplierProduct(product.id),
      data: product.toRequestJson(),
    );
    return SupplierProductModel.fromJson(
        res.data['data'] as Map<String, dynamic>);
  }

  Future<void> archiveProduct(int productId) async {
    await _client.delete(ApiEndpoints.supplierProduct(productId));
  }
}
