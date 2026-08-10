import 'dart:convert';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import '../../core/config/env_config.dart';

class AppSettingsService extends GetxService {
  final RxString blockClient = 'none'.obs;
  final RxString blockArtisan = 'none'.obs;
  final RxString blockFournisseur = 'none'.obs;
  final RxString blockLivreur = 'none'.obs;
  final RxString disabledMessage = 'L\'accès à cet espace est temporairement restreint suite à une opération de maintenance de nos services. Nous vous prions de nous excuser pour la gêne occasionnée et vous remercions de votre patience.'.obs;

  final RxString disabledMessageClient = 'L\'accès à l\'espace client est temporairement indisponible pour maintenance. Veuillez nous excuser pour la gêne occasionnée.'.obs;
  final RxString disabledMessageArtisan = 'L\'accès à l\'espace artisan est temporairement suspendu. Nos équipes interviennent rapidement. Merci de votre patience.'.obs;
  final RxString disabledMessageFournisseur = 'L\'espace fournisseur est en cours de mise à jour technique. L\'accès sera rétabli sous peu.'.obs;
  final RxString disabledMessageLivreur = 'L\'espace de livraison est momentanément inaccessible. Merci de réessayer d\'ici quelques instants.'.obs;

  @override
  void onInit() {
    super.onInit();
    fetchSettings();
  }

  Future<void> fetchSettings() async {
    try {
      final url = Uri.parse('${EnvConfig.baseUrl}/settings/app-access');
      final response = await http.get(url).timeout(const Duration(seconds: 10));

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body)['data'];
        if (data != null) {
          blockClient.value = data['block_client'] ?? 'none';
          blockArtisan.value = data['block_artisan'] ?? 'none';
          blockFournisseur.value = data['block_fournisseur'] ?? 'none';
          blockLivreur.value = data['block_livreur'] ?? 'none';
          disabledMessage.value = data['app_access_disabled_message'] ?? disabledMessage.value;
          disabledMessageClient.value = data['app_access_disabled_message_client'] ?? disabledMessageClient.value;
          disabledMessageArtisan.value = data['app_access_disabled_message_artisan'] ?? disabledMessageArtisan.value;
          disabledMessageFournisseur.value = data['app_access_disabled_message_fournisseur'] ?? disabledMessageFournisseur.value;
          disabledMessageLivreur.value = data['app_access_disabled_message_livreur'] ?? disabledMessageLivreur.value;
        }
      }
    } catch (e) {
      // Ignorer l'erreur silencieusement. Les paramètres resteront à 'none'.
    }
  }

  bool isBlockedAll(String role) {
    String status = 'none';
    if (role.toLowerCase() == 'client') status = blockClient.value;
    if (role.toLowerCase() == 'artisan') status = blockArtisan.value;
    if (role.toLowerCase() == 'fournisseur') status = blockFournisseur.value;
    if (role.toLowerCase() == 'driver' || role.toLowerCase() == 'livreur') status = blockLivreur.value;

    return status == 'all';
  }

  bool isBlocked(String role, {required bool isNewUser}) {
    String status = 'none';
    if (role.toLowerCase() == 'client') status = blockClient.value;
    if (role.toLowerCase() == 'artisan') status = blockArtisan.value;
    if (role.toLowerCase() == 'fournisseur') status = blockFournisseur.value;
    if (role.toLowerCase() == 'driver' || role.toLowerCase() == 'livreur') status = blockLivreur.value;

    if (status == 'all') return true;
    if (status == 'new' && isNewUser) return true;
    if (status == 'old' && !isNewUser) return true;
    return false;
  }

  String getDisabledMessage(String role) {
    if (role.toLowerCase() == 'client') return disabledMessageClient.value;
    if (role.toLowerCase() == 'artisan') return disabledMessageArtisan.value;
    if (role.toLowerCase() == 'fournisseur') return disabledMessageFournisseur.value;
    if (role.toLowerCase() == 'driver' || role.toLowerCase() == 'livreur') return disabledMessageLivreur.value;
    return disabledMessage.value;
  }
}
