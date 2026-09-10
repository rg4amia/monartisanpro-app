import 'package:dio/dio.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';

class UserRepository {
  final ApiClient _client = ApiClient();

  Future<Map<String, dynamic>> updateCnmci({
    required int userId,
    String? cnmciNumber,
    String? cardImagePath,
  }) async {
    final formData = FormData();
    if (cnmciNumber != null) {
      formData.fields.add(MapEntry('cnmci_number', cnmciNumber));
    }
    if (cardImagePath != null) {
      formData.files.add(
        MapEntry(
          'cnmci_card',
          await MultipartFile.fromFile(
            cardImagePath,
            filename: 'cnmci_card.jpg',
          ),
        ),
      );
    }

    final response = await _client.postMultipart(
      ApiEndpoints.updateCnmci(userId),
      formData,
    );
    return response.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> updateProfile({
    required int userId,
    String? name,
    bool? nightInterventionAvailable,
    int? sectorId,
    int? tradeId,
    bool clearTradeId = false,
    String? paymentPhone,
    String? preferredPaymentProvider,
  }) async {
    // On ne transmet `name` que si l'appelant modifie réellement ce champ :
    // le backend revalide `name` (min:2) à chaque envoi, donc renvoyer un nom
    // vide ou trop court — cas d'un profil incomplet qui associe seulement son
    // numéro Mobile Money — ferait échouer toute la mise à jour en 422.
    final trimmedName = name?.trim();
    final response = await _client.put(
      ApiEndpoints.updateUser(userId),
      data: {
        if (trimmedName != null && trimmedName.isNotEmpty) 'name': trimmedName,
        if (nightInterventionAvailable != null)
          'intervention_nuit': nightInterventionAvailable,
        if (sectorId != null) 'sector_id': sectorId,
        if (tradeId != null)
          'trade_id': tradeId
        else if (clearTradeId)
          'trade_id': null,
        if (paymentPhone != null) 'payment_phone': paymentPhone,
        if (preferredPaymentProvider != null)
          'preferred_payment_provider': preferredPaymentProvider,
      },
    );
    return response.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> updateLocation({
    required int userId,
    required double lat,
    required double lng,
  }) async {
    final response = await _client.put(
      ApiEndpoints.updateLocation(userId),
      data: {
        'lat': lat,
        'lng': lng,
      },
    );
    return response.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> setRole({
    required int userId,
    required String role,
  }) async {
    final response = await _client.put(
      ApiEndpoints.setRole(userId),
      data: {'role': role},
    );
    return response.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> getDashboardStats() async {
    final response = await _client.get(ApiEndpoints.dashboard);
    return response.data as Map<String, dynamic>;
  }

  Future<Map<String, dynamic>> deleteAccount({required int userId}) async {
    final response = await _client.delete(ApiEndpoints.updateUser(userId));
    return response.data as Map<String, dynamic>;
  }
}
