import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../models/devis_model.dart';

class DevisRepository {
  final ApiClient _client = ApiClient();

  Future<List<DevisModel>> getMissionDevis(int missionId) async {
    final res = await _client.get(ApiEndpoints.missionDevis(missionId));
    final list = res.data['data'] as List<dynamic>;
    return list
        .map((e) => DevisModel.fromJson(e as Map<String, dynamic>))
        .toList();
  }

  Future<DevisModel> getDevis(int id) async {
    final res = await _client.get(ApiEndpoints.devis(id));
    return DevisModel.fromJson(res.data['data'] as Map<String, dynamic>);
  }

  Future<DevisModel> createDevis({
    required int missionId,
    required List<DevisLigne> lignes,
    required List<DevisJalon> jalons,
  }) async {
    final res = await _client.post(ApiEndpoints.missionDevis(missionId), data: {
      'lignes_json': lignes.map((l) => l.toJson()).toList(),
      'jalons_json': jalons.map((j) => j.toJson()).toList(),
    });
    return DevisModel.fromJson(res.data['data'] as Map<String, dynamic>);
  }

  Future<DevisModel> updateDevis({
    required int id,
    required List<DevisLigne> lignes,
    required List<DevisJalon> jalons,
  }) async {
    final res = await _client.put(ApiEndpoints.devis(id), data: {
      'lignes_json': lignes.map((l) => l.toJson()).toList(),
      'jalons_json': jalons.map((j) => j.toJson()).toList(),
    });
    return DevisModel.fromJson(res.data['data'] as Map<String, dynamic>);
  }

  Future<DevisModel> acceptDevis(int id, {required int transactionId}) async {
    final res = await _client.post(
      ApiEndpoints.acceptDevis(id),
      data: {'transaction_id': transactionId},
    );
    return DevisModel.fromJson(res.data['data'] as Map<String, dynamic>);
  }

  Future<void> refuseDevis(int id) async {
    await _client.post(ApiEndpoints.refuseDevis(id));
  }

  Future<Map<String, dynamic>> getDevisSuggestion(int missionId) async {
    final res = await _client.get(ApiEndpoints.missionDevisSuggest(missionId));
    return res.data['data'] as Map<String, dynamic>;
  }
}
