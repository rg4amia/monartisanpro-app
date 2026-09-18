import 'package:get/get.dart';

import '../../../data/repositories/supplier_dashboard_repository.dart';

/// Statistiques d'activité du fournisseur (commandes, chiffre d'affaires
/// livré, catalogue) et ses commandes les plus récentes.
class SupplierDashboardController extends GetxController {
  SupplierDashboardController({SupplierDashboardRepository? repository})
      : _repo = repository ?? SupplierDashboardRepository();

  final SupplierDashboardRepository _repo;

  final isLoading = false.obs;
  final errorMsg = RxnString();

  final totalOrders = 0.obs;
  final pendingOrders = 0.obs;
  final totalRevenue = 0.obs;
  final catalogCount = 0.obs;
  final recentOrders = <Map<String, dynamic>>[].obs;

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load() async {
    isLoading.value = true;
    errorMsg.value = null;

    try {
      final data = await _repo.getDashboard();
      final stats =
          (data['stats'] as Map?)?.cast<String, dynamic>() ?? const {};

      totalOrders.value = (stats['total_orders'] as num?)?.toInt() ?? 0;
      pendingOrders.value = (stats['pending_orders'] as num?)?.toInt() ?? 0;
      totalRevenue.value = (stats['total_revenue'] as num?)?.toInt() ?? 0;
      catalogCount.value = (stats['catalog_count'] as num?)?.toInt() ?? 0;

      recentOrders.value = ((data['recent_orders'] as List?) ?? const [])
          .whereType<Map>()
          .map((e) => e.cast<String, dynamic>())
          .toList();
    } catch (_) {
      errorMsg.value =
          'Impossible de charger le tableau de bord. Vérifiez votre connexion.';
    } finally {
      isLoading.value = false;
    }
  }
}
