import 'package:dio/dio.dart';
import 'package:get/get.dart' hide Response, MultipartFile, FormData;
import 'package:image_picker/image_picker.dart';

import '../../core/cache/mission_cache_service.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/network/network_executor.dart';
import '../../core/network/sync_service.dart';
import '../models/jalon_model.dart';
import '../models/mission_model.dart';

class MissionRepository {
  final ApiClient _client = ApiClient();
  final MissionCacheService _cache = MissionCacheService();

  /// Initialise le cache
  Future<void> initCache() async {
    if (!_cache.isInitialized) {
      await _cache.init();
    }
  }

  /// Récupère la liste des missions avec filtre optionnel par statut
  ///
  /// Stratégie cache-first :
  /// 1. Retourne les données en cache si valides (< 5 min)
  /// 2. Sinon, appelle l'API et met à jour le cache
  /// 3. En cas d'erreur réseau, retourne le cache expiré si disponible
  Future<List<MissionModel>> getMissions({
    String? status,
    bool forceRefresh = false,
  }) async {
    await initCache();

    // 1. Vérifier le cache si pas de forceRefresh
    if (!forceRefresh) {
      final cached = _cache.getCachedMissions(filter: status);
      if (cached != null) {
        return cached;
      }
    }

    // 2. Appeler l'API
    try {
      final res = await NetworkExecutor.run(
        () => _client.get(
          ApiEndpoints.missions,
          params: status != null ? {'status': status} : null,
        ),
      );

      // Support pour différents formats de réponse backend
      final data = res.data;
      final List<dynamic> list;

      if (data is Map && data.containsKey('data')) {
        list = data['data'] as List<dynamic>;
      } else if (data is List) {
        list = data;
      } else {
        throw Exception('Format de réponse inattendu pour getMissions');
      }

      final missions = list
          .map((e) => MissionModel.fromJson(e as Map<String, dynamic>))
          .toList();

      // 3. Mettre à jour le cache
      await _cache.cacheMissions(missions, filter: status);

      return missions;
    } catch (e) {
      // 4. En cas d'erreur, retourner le cache expiré si disponible
      final fallbackCache = _cache.getCachedMissions(filter: status, ignoreExpiration: true);
      if (fallbackCache != null) {
        // Le cache existe mais est expiré, on le retourne quand même
        return fallbackCache;
      }
      rethrow;
    }
  }

  /// Récupère une mission spécifique par ID
  ///
  /// Stratégie cache-first avec fallback
  Future<MissionModel> getMission(int id, {bool forceRefresh = false}) async {
    await initCache();

    // 1. Vérifier le cache si pas de forceRefresh
    if (!forceRefresh) {
      final cached = _cache.getCachedMission(id);
      if (cached != null) {
        return cached;
      }
    }

    // 2. Appeler l'API
    try {
      final res = await NetworkExecutor.run(
        () => _client.get(ApiEndpoints.mission(id)),
      );

      final data = res.data;
      final Map<String, dynamic> missionData;

      if (data is Map && data.containsKey('data')) {
        missionData = data['data'] as Map<String, dynamic>;
      } else if (data is Map) {
        missionData = data as Map<String, dynamic>;
      } else {
        throw Exception('Format de réponse inattendu pour getMission');
      }

      final mission = MissionModel.fromJson(missionData);

      // 3. Mettre à jour le cache
      await _cache.cacheMission(mission);

      return mission;
    } catch (e) {
      // 4. Fallback sur cache expiré
      final fallbackCache = _cache.getCachedMission(id, ignoreExpiration: true);
      if (fallbackCache != null) {
        return fallbackCache;
      }
      rethrow;
    }
  }

  /// Crée une nouvelle mission
  Future<MissionModel> createMission({
    required int artisanId,
    required String description,
    required String category,
    required String urgency,
    int? sectorId,
    int? tradeId,
    double? lat,
    double? lng,
    String? location,
    List<String>? photos,
    Map<String, dynamic>? additionalData,
  }) async {
    final payload = {
      'artisan_id': artisanId,
      'description': description,
      'category': category,
      'urgency': urgency,
      if (sectorId != null) 'sector_id': sectorId,
      if (tradeId != null) 'trade_id': tradeId,
      if (lat != null) 'lat': lat,
      if (lng != null) 'lng': lng,
      if (location != null) 'location_address': location,
      if (photos != null) 'photos': photos,
      if (additionalData != null) ...additionalData,
    };

    final res = await _client.post(ApiEndpoints.missions, data: payload);

    final data = res.data;
    final Map<String, dynamic> missionData;

    if (data is Map && data.containsKey('data')) {
      missionData = data['data'] as Map<String, dynamic>;
    } else if (data is Map) {
      missionData = data as Map<String, dynamic>;
    } else {
      throw Exception('Format de réponse inattendu pour createMission');
    }

    return MissionModel.fromJson(missionData);
  }

  /// Upload un fichier (image/vidéo) et retourne son URL publique
  Future<String> uploadFile(String filePath) async {
    final formData = FormData.fromMap({
      'file': await MultipartFile.fromFile(
        filePath,
        filename: filePath.split('/').last,
      ),
    });
    final res = await _client.postMultipart('/upload', formData);
    return res.data['url'] as String;
  }

  /// Demande une estimation IA Gemini pour une mission
  Future<Map<String, dynamic>> estimate({
    required String description,
    required String category,
  }) async {
    final res = await NetworkExecutor.run(
      () => _client.post(
        ApiEndpoints.missionEstimate,
        data: {'description': description, 'category': category},
      ),
      maxRetries: 2, // Moins de tentatives pour l'IA (coût API)
    );

    final data = res.data;

    if (data is Map) {
      // Si la réponse contient un wrapper 'data', l'extraire
      if (data.containsKey('data')) {
        return data['data'] as Map<String, dynamic>;
      }
      return data as Map<String, dynamic>;
    }

    throw Exception('Format de réponse inattendu pour estimate');
  }

  /// Met à jour le statut d'une mission
  Future<void> updateStatus(int id, String status) async {
    try {
      await _client.put(ApiEndpoints.missionStatus(id), data: {'status': status});
    } on DioException catch (e) {
      if (e.type == DioExceptionType.connectionTimeout || 
          e.type == DioExceptionType.receiveTimeout || 
          e.type == DioExceptionType.connectionError) {
        
        final syncService = Get.find<SyncService>();
        await syncService.enqueueRequest('PUT', ApiEndpoints.missionStatus(id), data: {'status': status});
        
        // Mettre à jour localement la mission en cache en attendant la synchro
        final cached = _cache.getCachedMission(id, ignoreExpiration: true);
        if (cached != null) {
          // Astuce : on modifie le JSON et on recache (simplification)
          final json = cached.toJson();
          json['status'] = status;
          await _cache.cacheMission(MissionModel.fromJson(json));
          await _cache.invalidate('all'); // Pour forcer la vue liste à se rafraîchir localement
        }
      } else {
        rethrow;
      }
    }
  }

  /// Artisan accepte une demande de devis directe
  Future<MissionModel> acceptRequest(int missionId) async {
    final res = await _client.post('/missions/$missionId/accept-request');
    final data = res.data;
    final Map<String, dynamic> missionData;
    if (data is Map && data.containsKey('data')) {
      missionData = data['data'] as Map<String, dynamic>;
    } else {
      missionData = data as Map<String, dynamic>;
    }
    return MissionModel.fromJson(missionData);
  }

  /// Artisan refuse une demande de devis directe
  Future<MissionModel> rejectRequest(int missionId) async {
    final res = await _client.post('/missions/$missionId/reject-request');
    final data = res.data;
    final Map<String, dynamic> missionData;
    if (data is Map && data.containsKey('data')) {
      missionData = data['data'] as Map<String, dynamic>;
    } else {
      missionData = data as Map<String, dynamic>;
    }
    return MissionModel.fromJson(missionData);
  }

  Future<Map<String, dynamic>> validateReferentMission({
    required int missionId,
    required double latitude,
    required double longitude,
    required List<XFile> photos,
    String? notes,
  }) async {
    final formData = FormData.fromMap({
      'latitude': latitude,
      'longitude': longitude,
      if (notes != null && notes.trim().isNotEmpty) 'notes': notes.trim(),
      'photos': [
        for (final photo in photos)
          await MultipartFile.fromFile(
            photo.path,
            filename: photo.name,
          ),
      ],
    });

    final res = await _client.postMultipart(
      ApiEndpoints.missionReferentValidate(missionId),
      formData,
    );

    return res.data as Map<String, dynamic>;
  }

  /// Récupère les jalons d'une mission
  ///
  /// Stratégie cache-first avec fallback
  Future<List<JalonModel>> getJalons(
    int missionId, {
    bool forceRefresh = false,
  }) async {
    await initCache();

    // 1. Vérifier le cache si pas de forceRefresh
    if (!forceRefresh) {
      final cached = _cache.getCachedJalons(missionId);
      if (cached != null) {
        return cached;
      }
    }

    // 2. Appeler l'API
    try {
      final res = await NetworkExecutor.run(
        () => _client.get(ApiEndpoints.missionJalons(missionId)),
      );

      final data = res.data;
      final List<dynamic> list;

      if (data is Map && data.containsKey('data')) {
        list = data['data'] as List<dynamic>;
      } else if (data is List) {
        list = data;
      } else {
        throw Exception('Format de réponse inattendu pour getJalons');
      }

      final jalons = list
          .map((e) => JalonModel.fromJson(e as Map<String, dynamic>))
          .toList();

      // 3. Mettre à jour le cache
      await _cache.cacheJalons(missionId, jalons);

      return jalons;
    } catch (e) {
      // 4. Fallback sur cache expiré
      final fallbackCache = _cache.getCachedJalons(missionId, ignoreExpiration: true);
      if (fallbackCache != null) {
        return fallbackCache;
      }
      rethrow;
    }
  }

  /// Soumet un jalon pour validation client
  ///
  /// [jalonId] - ID du jalon à soumettre
  /// [photos] - Liste de photos géolocalisées (optionnel)
  /// [missionId] - ID de la mission (pour invalider le cache)
  ///
  /// Format photos: [{"url": "...", "lat": 5.3, "lng": -4.0, "taken_at": "2025-03-07T10:00:00Z"}]
  ///
  /// Invalide le cache des jalons après soumission
  Future<void> submitJalon(
    int jalonId, {
    List<Map<String, dynamic>>? photos,
    int? missionId,
  }) async {
    final payload = photos != null ? {'photos': photos} : null;

    try {
      await _client.put(ApiEndpoints.submitJalon(jalonId), data: payload);
    } on DioException catch (e) {
      if (e.type == DioExceptionType.connectionTimeout || 
          e.type == DioExceptionType.receiveTimeout || 
          e.type == DioExceptionType.connectionError) {
        
        final syncService = Get.find<SyncService>();
        await syncService.enqueueRequest('PUT', ApiEndpoints.submitJalon(jalonId), data: payload);
        
        if (missionId != null) {
          // On invalide le cache des jalons pour forcer un refresh local (ou on pourrait le modifier localement)
          await _cache.invalidate('jalons_$missionId');
        }
      } else {
        rethrow;
      }
    }

    // Invalider le cache des jalons pour forcer un refresh
    if (missionId != null) {
      await _cache.invalidate('jalons_$missionId');
    }
  }

  /// Demande l'envoi d'un OTP au client pour valider un jalon
  Future<void> requestOtp(int jalonId) async {
    await _client.post(ApiEndpoints.requestOtp(jalonId));
  }

  /// Valide un OTP et libère le paiement du jalon
  ///
  /// Invalide le cache des jalons et de la mission après validation
  Future<void> validateOtp(int jalonId, String otp, {int? missionId}) async {
    await _client.post(ApiEndpoints.validateOtp(jalonId), data: {'otp': otp});

    // Invalider le cache pour forcer un refresh
    if (missionId != null) {
      await _cache.invalidate('jalons_$missionId');
      await _cache.invalidate('mission_$missionId');
      // Invalider aussi la liste des missions
      await _cache.invalidate('all');
      await _cache.invalidate('en_cours');
    }
  }

  /// Upload de preuves supplémentaires pour un jalon (artisan)
  Future<void> uploadJalonPhotos(int jalonId, List<Map<String, dynamic>> localFiles, {int? missionId}) async {
    final Map<String, dynamic> formMap = {};
    for (int i = 0; i < localFiles.length; i++) {
      final fileMap = localFiles[i];
      final path = fileMap['url'] as String;
      formMap['photos[$i][photo]'] = await MultipartFile.fromFile(
        path,
        filename: path.split('/').last,
      );
      formMap['photos[$i][latitude]'] = fileMap['lat'];
      formMap['photos[$i][longitude]'] = fileMap['lng'];
      if (fileMap['description'] != null) {
        formMap['photos[$i][description]'] = fileMap['description'];
      }
    }
    final formData = FormData.fromMap(formMap);
    await _client.postMultipart(ApiEndpoints.uploadJalonPhotos(jalonId), formData);

    if (missionId != null) {
      await _cache.invalidate('jalons_$missionId');
    }
  }

  /// Le client accepte directement les preuves de travail et valide le jalon sans OTP
  Future<void> acceptJalonProofs(int jalonId, {int? missionId}) async {
    await _client.post(ApiEndpoints.acceptJalonProofs(jalonId));

    if (missionId != null) {
      await _cache.invalidate('jalons_$missionId');
      await _cache.invalidate('mission_$missionId');
      await _cache.invalidate('all');
      await _cache.invalidate('en_cours');
    }
  }

  /// Invalide tout le cache (utile après déconnexion)
  Future<void> clearCache() async {
    await initCache();
    await _cache.clearAll();
  }

  /// Retourne des informations sur le cache
  Future<Map<String, dynamic>> getCacheInfo() async {
    await initCache();

    return {'size': _cache.cacheSize, 'isInitialized': _cache.isInitialized};
  }

}
