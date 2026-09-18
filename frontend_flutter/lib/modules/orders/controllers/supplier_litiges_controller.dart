import 'package:get/get.dart';

import '../../../data/repositories/supplier_dashboard_repository.dart';

/// Litiges concernant le fournisseur : ses commandes e-commerce contestées et
/// les chantiers où il a livré des matériaux via J-Code, désormais en litige.
///
/// Purement informatif : l'arbitrage reste une action admin du backoffice.
class SupplierLitigesController extends GetxController {
  SupplierLitigesController({SupplierDashboardRepository? repository})
      : _repo = repository ?? SupplierDashboardRepository();

  final SupplierDashboardRepository _repo;

  final isLoading = false.obs;
  final errorMsg = RxnString();

  final orderLitiges = <Map<String, dynamic>>[].obs;
  final missionLitiges = <Map<String, dynamic>>[].obs;

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load() async {
    isLoading.value = true;
    errorMsg.value = null;

    try {
      final data = await _repo.getLitiges();

      orderLitiges.value = ((data['order_litiges'] as List?) ?? const [])
          .whereType<Map>()
          .map((e) => e.cast<String, dynamic>())
          .toList();
      missionLitiges.value = ((data['mission_litiges'] as List?) ?? const [])
          .whereType<Map>()
          .map((e) => e.cast<String, dynamic>())
          .toList();
    } catch (_) {
      errorMsg.value =
          'Impossible de charger les litiges. Vérifiez votre connexion.';
    } finally {
      isLoading.value = false;
    }
  }
}
