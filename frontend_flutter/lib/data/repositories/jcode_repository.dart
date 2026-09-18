import 'package:dio/dio.dart';

import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../models/jcode_item_model.dart';
import '../models/jcode_model.dart';

class JcodeRepository {
  final ApiClient _client = ApiClient();

  Future<JcodeModel> createJcode({
    required int missionId,
    required int fournisseurId,
    required List<JcodeItemModel> items,
    int? montant,
  }) async {
    final res = await _client.post(
      ApiEndpoints.jcodes,
      data: {
        'mission_id': missionId,
        'fournisseur_id': fournisseurId,
        if (montant != null) 'montant': montant,
        'items': items.map((item) => item.toRequestJson()).toList(),
      },
    );
    return JcodeModel.fromJson(
      (res.data as Map<String, dynamic>)['data'] as Map<String, dynamic>,
    );
  }

  Future<JcodeModel?> getActiveJcode() async {
    final res = await _client.get(ApiEndpoints.jcodesActive);
    final data = (res.data as Map<String, dynamic>)['data'];
    if (data == null) return null;
    if (data is List && data.isNotEmpty) {
      return JcodeModel.fromJson(data.first as Map<String, dynamic>);
    }
    if (data is Map<String, dynamic>) {
      return JcodeModel.fromJson(data);
    }
    return null;
  }

  Future<JcodeModel> getJcode(Object identifier) async {
    final res = await _client.get(ApiEndpoints.jcode(identifier));
    return JcodeModel.fromJson(
      (res.data as Map<String, dynamic>)['data'] as Map<String, dynamic>,
    );
  }

  /// Supplier scans a J-Code by numeric ID or business code (ex: PA-AB12).
  Future<Map<String, dynamic>> scanJcode({
    required String identifier,
    required double lat,
    required double lng,
    List<Map<String, dynamic>>? servedItems,
  }) async {
    final res = await _client.post(
      ApiEndpoints.scanJcode(identifier),
      data: {
        'lat': lat,
        'lng': lng,
        if (servedItems != null) 'served_items': servedItems,
      },
    );
    return res.data as Map<String, dynamic>;
  }

  /// Artisan : upload de la photo géolocalisée des matériaux reçus sur
  /// chantier, une fois le J-Code livré par le fournisseur. Notifie le
  /// client côté backend.
  Future<Map<String, dynamic>> uploadPhotoMateriaux({
    required Object identifier,
    required String photoPath,
    required double latitude,
    required double longitude,
  }) async {
    final formData = FormData.fromMap({
      'photo': await MultipartFile.fromFile(
        photoPath,
        filename: photoPath.split('/').last,
      ),
      'latitude': latitude,
      'longitude': longitude,
    });
    final res = await _client.postMultipart(
      ApiEndpoints.jcodePhotoMateriaux(identifier),
      formData,
    );
    return res.data as Map<String, dynamic>;
  }
}
