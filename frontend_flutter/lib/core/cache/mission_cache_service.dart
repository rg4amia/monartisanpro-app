import 'package:hive_flutter/hive_flutter.dart';

import '../../data/models/jalon_model.dart';
import '../../data/models/mission_model.dart';

/// Service de cache local pour les missions avec Hive
///
/// Stratégie de cache :
/// - Cache-first pour améliorer la performance
/// - Expiration après 5 minutes
/// - Synchronisation automatique en arrière-plan
/// - Support mode hors-ligne
class MissionCacheService {
  static const String _missionsBoxName = 'missions_cache';
  static const String _jalonsBoxName = 'jalons_cache';
  static const String _metadataBoxName = 'cache_metadata';

  /// Durée de validité du cache (5 minutes)
  static const Duration _cacheValidity = Duration(minutes: 5);

  Box<Map>? _missionsBox;
  Box<Map>? _jalonsBox;
  Box<Map>? _metadataBox;

  /// Initialise Hive et ouvre les boxes
  Future<void> init() async {
    await Hive.initFlutter();

    // Ouvrir les boxes (pas besoin d'adapters pour Map)
    _missionsBox = await Hive.openBox<Map>(_missionsBoxName);
    _jalonsBox = await Hive.openBox<Map>(_jalonsBoxName);
    _metadataBox = await Hive.openBox<Map>(_metadataBoxName);
  }

  /// Vérifie si le service est initialisé
  bool get isInitialized =>
      _missionsBox != null && _jalonsBox != null && _metadataBox != null;

  // ─────────────────────────────────────────────────────────────────────────────
  // MISSIONS
  // ─────────────────────────────────────────────────────────────────────────────

  /// Cache une liste de missions
  Future<void> cacheMissions(List<MissionModel> missions,
      {String? filter}) async {
    if (!isInitialized) await init();

    final key = filter ?? 'all';
    final data = missions.map((m) => m.toJson()).toList();

    await _missionsBox!.put(key, {'missions': data});
    await _setMetadata('missions_$key', DateTime.now());
  }

  /// Récupère les missions du cache
  List<MissionModel>? getCachedMissions({String? filter}) {
    if (!isInitialized) return null;

    final key = filter ?? 'all';

    // Vérifier l'expiration
    if (!_isValid('missions_$key')) {
      return null;
    }

    final cached = _missionsBox!.get(key);
    if (cached == null) return null;

    try {
      final list = cached['missions'] as List;
      return list
          .map((e) => MissionModel.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    } catch (_) {
      return null;
    }
  }

  /// Cache une mission spécifique
  Future<void> cacheMission(MissionModel mission) async {
    if (!isInitialized) await init();

    await _missionsBox!.put('mission_${mission.id}', mission.toJson());
    await _setMetadata('mission_${mission.id}', DateTime.now());
  }

  /// Récupère une mission du cache
  MissionModel? getCachedMission(int id) {
    if (!isInitialized) return null;

    final key = 'mission_$id';

    // Vérifier l'expiration
    if (!_isValid(key)) {
      return null;
    }

    final cached = _missionsBox!.get(key);
    if (cached == null) return null;

    try {
      return MissionModel.fromJson(Map<String, dynamic>.from(cached));
    } catch (_) {
      return null;
    }
  }

  // ─────────────────────────────────────────────────────────────────────────────
  // JALONS
  // ─────────────────────────────────────────────────────────────────────────────

  /// Cache les jalons d'une mission
  Future<void> cacheJalons(int missionId, List<JalonModel> jalons) async {
    if (!isInitialized) await init();

    final key = 'jalons_$missionId';
    final data = jalons.map((j) => j.toJson()).toList();

    await _jalonsBox!.put(key, {'jalons': data});
    await _setMetadata(key, DateTime.now());
  }

  /// Récupère les jalons du cache
  List<JalonModel>? getCachedJalons(int missionId) {
    if (!isInitialized) return null;

    final key = 'jalons_$missionId';

    // Vérifier l'expiration
    if (!_isValid(key)) {
      return null;
    }

    final cached = _jalonsBox!.get(key);
    if (cached == null) return null;

    try {
      final list = cached['jalons'] as List;
      return list
          .map((e) => JalonModel.fromJson(Map<String, dynamic>.from(e)))
          .toList();
    } catch (_) {
      return null;
    }
  }

  // ─────────────────────────────────────────────────────────────────────────────
  // METADATA & UTILITIES
  // ─────────────────────────────────────────────────────────────────────────────

  /// Stocke la date de mise en cache
  Future<void> _setMetadata(String key, DateTime timestamp) async {
    await _metadataBox!.put(key, {
      'timestamp': timestamp.toIso8601String(),
    });
  }

  /// Vérifie si les données en cache sont encore valides
  bool _isValid(String key) {
    final metadata = _metadataBox!.get(key);
    if (metadata == null) return false;

    try {
      final timestamp = DateTime.parse(metadata['timestamp'] as String);
      final age = DateTime.now().difference(timestamp);
      return age < _cacheValidity;
    } catch (_) {
      return false;
    }
  }

  /// Invalide une entrée spécifique du cache
  Future<void> invalidate(String key) async {
    if (!isInitialized) return;

    await _missionsBox!.delete(key);
    await _jalonsBox!.delete(key);
    await _metadataBox!.delete(key);
  }

  /// Invalide toutes les missions du cache
  Future<void> invalidateAllMissions() async {
    if (!isInitialized) return;

    await _missionsBox!.clear();
    await _metadataBox!.clear();
  }

  /// Invalide tous les jalons du cache
  Future<void> invalidateAllJalons() async {
    if (!isInitialized) return;

    await _jalonsBox!.clear();
  }

  /// Nettoie tout le cache
  Future<void> clearAll() async {
    if (!isInitialized) return;

    await _missionsBox!.clear();
    await _jalonsBox!.clear();
    await _metadataBox!.clear();
  }

  /// Retourne la taille du cache en nombre d'entrées
  int get cacheSize {
    if (!isInitialized) return 0;
    return _missionsBox!.length + _jalonsBox!.length;
  }

  /// Vérifie si des données sont disponibles en cache (valides ou non)
  bool hasCachedData({String? filter}) {
    if (!isInitialized) return false;

    final key = filter ?? 'all';
    return _missionsBox!.containsKey(key);
  }

  /// Retourne l'âge du cache pour une clé donnée
  Duration? getCacheAge(String key) {
    if (!isInitialized) return null;

    final metadata = _metadataBox!.get(key);
    if (metadata == null) return null;

    try {
      final timestamp = DateTime.parse(metadata['timestamp'] as String);
      return DateTime.now().difference(timestamp);
    } catch (_) {
      return null;
    }
  }

  /// Ferme toutes les boxes
  Future<void> close() async {
    await _missionsBox?.close();
    await _jalonsBox?.close();
    await _metadataBox?.close();
  }
}
