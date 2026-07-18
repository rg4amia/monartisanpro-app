import 'dart:convert';
import 'package:get/get.dart';
import 'package:http/http.dart' as http;
import '../../core/utils/constants.dart';

class AppSettingsService extends GetxService {
  final RxString blockClient = 'none'.obs;
  final RxString blockArtisan = 'none'.obs;
  final RxString blockFournisseur = 'none'.obs;
  final RxString blockLivreur = 'none'.obs;
  final RxString disabledMessage = 'L\'accès à cet espace est temporairement restreint suite à une opération de maintenance de nos services. Nous vous prions de nous excuser pour la gêne occasionnée et vous remercions de votre patience.'.obs;

  @override
  void onInit() {
    super.onInit();
    fetchSettings();
  }

  Future<void> fetchSettings() async {
    try {
      final url = Uri.parse('${Constants.apiUrl}/settings/app-access');
      final response = await http.get(url).timeout(const Duration(seconds: 10));

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body)['data'];
        if (data != null) {
          blockClient.value = data['block_client'] ?? 'none';
          blockArtisan.value = data['block_artisan'] ?? 'none';
          blockFournisseur.value = data['block_fournisseur'] ?? 'none';
          blockLivreur.value = data['block_livreur'] ?? 'none';
          disabledMessage.value = data['app_access_disabled_message'] ?? disabledMessage.value;
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
}
