import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/network/network_executor.dart';
import '../../core/utils/json_readers.dart';

/// Tableau de bord et suivi des litiges de l'espace fournisseur.
///
/// Données légères, rafraîchies à chaque ouverture d'écran : pas besoin de
/// cache local ici (contrairement au catalogue ou aux commandes suivies hors
/// ligne), même pattern que `ArtisanRepository.getScore`.
class SupplierDashboardRepository {
  final ApiClient _client = ApiClient();

  Future<Map<String, dynamic>> getDashboard() async {
    final res = await NetworkExecutor.run(
      () => _client.get(ApiEndpoints.supplierDashboard),
    );
    return requireDataMap(res.data);
  }

  Future<Map<String, dynamic>> getLitiges() async {
    final res = await NetworkExecutor.run(
      () => _client.get(ApiEndpoints.supplierLitiges),
    );
    return requireDataMap(res.data);
  }
}
