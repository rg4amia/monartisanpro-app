import 'package:dio/dio.dart';

import '../../core/cache/mission_cache_service.dart';
import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../models/jalon_model.dart';
import '../models/mission_model.dart';

class MissionRepository {
  final ApiClient _client = ApiClient();
  final MissionCacheService _cache = MissionCacheService();

  /// Nombre maximum de tentatives pour les requêtes
  static const int _maxRetries = 3;

  /// Délai entre les tentatives (en millisecondes)
  static const int _retryDelay = 1000;

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
      final res = await _executeWithRetry(
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
      final fallbackCache = _cache.getCachedMissions(filter: status);
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
      final res = await _executeWithRetry(
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
      final fallbackCache = _cache.getCachedMission(id);
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
    String? location,
    Map<String, dynamic>? additionalData,
  }) async {
    final payload = {
      'artisan_id': artisanId,
      'description': description,
      'category': category,
      'urgency': urgency,
      if (location != null) 'location': location,
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

  /// Demande une estimation IA Gemini pour une mission
  Future<Map<String, dynamic>> estimate({
    required String description,
    required String category,
  }) async {
    final res = await _executeWithRetry(
      () => _client.post(ApiEndpoints.missionEstimate, data: {
        'description': description,
        'category': category,
      }),
      retries: 2, // Moins de tentatives pour l'IA (coût API)
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
    await _client.put(
      ApiEndpoints.missionStatus(id),
      data: {'status': status},
    );
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
      final res = await _executeWithRetry(
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
      final fallbackCache = _cache.getCachedJalons(missionId);
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

    await _client.put(
      ApiEndpoints.submitJalon(jalonId),
      data: payload,
    );

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
    await _client.post(
      ApiEndpoints.validateOtp(jalonId),
      data: {'otp': otp},
    );

    // Invalider le cache pour forcer un refresh
    if (missionId != null) {
      await _cache.invalidate('jalons_$missionId');
      await _cache.invalidate('mission_$missionId');
      // Invalider aussi la liste des missions
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

    return {
      'size': _cache.cacheSize,
      'isInitialized': _cache.isInitialized,
    };
  }

  /// Exécute une requête avec mécanisme de retry automatique
  ///
  /// Retry automatique en cas d'erreur réseau (timeout, connexion)
  /// Ne retry PAS en cas d'erreur métier (4xx sauf 408/429)
  Future<Response> _executeWithRetry(
    Future<Response> Function() request, {
    int retries = _maxRetries,
  }) async {
    int attempt = 0;
    DioException? lastError;

    while (attempt < retries) {
      try {
        return await request();
      } on DioException catch (e) {
        lastError = e;

        // Ne pas retry pour les erreurs client (sauf timeout et rate limit)
        if (e.response != null) {
          final statusCode = e.response!.statusCode;

          // Retry pour 408 (Request Timeout) et 429 (Too Many Requests)
          if (statusCode == 408 || statusCode == 429) {
            attempt++;
            if (attempt < retries) {
              // Délai exponentiel pour 429
              final delay = statusCode == 429
                  ? _retryDelay * (1 << attempt) // 2s, 4s, 8s...
                  : _retryDelay;
              await Future.delayed(Duration(milliseconds: delay));
              continue;
            }
          }

          // Ne pas retry pour les autres erreurs 4xx
          if (statusCode != null && statusCode >= 400 && statusCode < 500) {
            rethrow;
          }
        }

        // Retry pour erreurs réseau et timeouts
        final shouldRetry = e.type == DioExceptionType.connectionTimeout ||
            e.type == DioExceptionType.receiveTimeout ||
            e.type == DioExceptionType.sendTimeout ||
            e.type == DioExceptionType.connectionError;

        if (shouldRetry && attempt < retries - 1) {
          attempt++;
          await Future.delayed(const Duration(milliseconds: _retryDelay));
          continue;
        }

        // Dernière tentative échouée
        rethrow;
      }
    }

    // Si on arrive ici, toutes les tentatives ont échoué
    throw lastError ?? DioException(
      requestOptions: RequestOptions(path: ''),
      error: 'Toutes les tentatives ont échoué',
    );
  }
}
