import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../models/communication_model.dart';

class CommunicationRepository {
  final ApiClient _client = ApiClient();

  /// Récupère les communications actives (annonces + astuces le_saviez_vous)
  /// pour le rôle actuel de l'utilisateur connecté.
  Future<Map<String, List<CommunicationModel>>>
      getActiveCommunications() async {
    final res = await _client.get(ApiEndpoints.communicationsActive);
    final data =
        (res.data as Map<String, dynamic>)['data'] as Map<String, dynamic>;

    final annoncesRaw = data['annonces'] as List<dynamic>? ?? [];
    final tipsRaw = data['le_saviez_vous'] as List<dynamic>? ?? [];

    return {
      'annonces': annoncesRaw
          .map((e) => CommunicationModel.fromJson(e as Map<String, dynamic>))
          .toList(),
      'le_saviez_vous': tipsRaw
          .map((e) => CommunicationModel.fromJson(e as Map<String, dynamic>))
          .toList(),
    };
  }
}
