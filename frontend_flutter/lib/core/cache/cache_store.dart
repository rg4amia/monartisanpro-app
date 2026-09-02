import 'package:flutter/foundation.dart';
import 'package:hive_flutter/hive_flutter.dart';

import 'hive_cipher_provider.dart';

/// Stratégie de lecture d'un dépôt caché.
enum CachePolicy {
  /// Renvoie le cache s'il est frais, sinon réseau (fallback cache périmé).
  cacheFirst,

  /// Tente le réseau d'abord, retombe sur le cache (même périmé) en cas d'échec.
  networkFirst,

  /// Ne consulte jamais le réseau.
  cacheOnly,
}

/// Dépôt de cache local générique, chiffré (AES-256) via Hive.
///
/// Généralise `MissionCacheService` : une box de données + une box de
/// métadonnées (horodatage) partagée, avec expiration par TTL, fallback sur
/// données périmées et self-healing en cas de box corrompue.
///
/// ```dart
/// final store = CacheStore<ArtisanModel>(
///   boxName: 'artisans_cache',
///   fromJson: ArtisanModel.fromJson,
///   toJson: (a) => a.toJson(),
/// );
/// await store.init();
/// final list = await store.readList(
///   key: 'nearby_5.3_-4.0',
///   ttl: const Duration(minutes: 3),
///   policy: CachePolicy.cacheFirst,
///   fetch: () => repo.fetchNearbyFromApi(),
/// );
/// ```
class CacheStore<T> {
  CacheStore({
    required this.boxName,
    required this.fromJson,
    required this.toJson,
  }) {
    _registry.add(this);
  }

  final String boxName;
  final T Function(Map<String, dynamic> json) fromJson;
  final Map<String, dynamic> Function(T value) toJson;

  static const String _metadataBoxName = 'cache_metadata';

  /// Toutes les instances créées, pour un nettoyage global (déconnexion).
  static final List<CacheStore<dynamic>> _registry = [];

  /// Vide tous les `CacheStore` connus. À appeler à la déconnexion pour ne pas
  /// exposer les données d'un compte au compte suivant sur le même appareil.
  static Future<void> wipeAll() async {
    for (final store in _registry) {
      try {
        await store.init();
        await store.clear();
      } catch (_) {
        // best-effort
      }
    }
  }

  Box<Map>? _box;
  Box<Map>? _metaBox;

  bool get isInitialized => _box != null && _metaBox != null;

  Future<void> init() async {
    if (isInitialized) return;
    await Hive.initFlutter();
    final cipher = await HiveCipherProvider.cipher();

    _box = await _openHealing(boxName, cipher);
    _metaBox = await _openHealing(_metadataBoxName, cipher);
  }

  static Future<Box<Map>> _openHealing(
    String name,
    HiveAesCipher cipher,
  ) async {
    try {
      return await Hive.openBox<Map>(name, encryptionCipher: cipher);
    } catch (_) {
      // Box corrompue ou transition depuis un cache non chiffré.
      await Hive.deleteBoxFromDisk(name);
      return Hive.openBox<Map>(name, encryptionCipher: cipher);
    }
  }

  // ── Écriture ───────────────────────────────────────────────────────────────

  Future<void> writeOne(String key, T value) async {
    if (!isInitialized) await init();
    await _box!.put(key, toJson(value));
    await _touch(key);
  }

  Future<void> writeList(String key, List<T> values) async {
    if (!isInitialized) await init();
    await _box!.put(key, {'items': values.map(toJson).toList()});
    await _touch(key);
  }

  // ── Lecture brute (cache seul) ─────────────────────────────────────────────

  T? peekOne(String key, {bool ignoreExpiration = false, Duration? ttl}) {
    if (!isInitialized) return null;
    if (!ignoreExpiration && !isFresh(key, ttl)) return null;
    final raw = _box!.get(key);
    if (raw == null) return null;
    try {
      return fromJson(Map<String, dynamic>.from(raw));
    } catch (_) {
      return null;
    }
  }

  List<T>? peekList(
    String key, {
    bool ignoreExpiration = false,
    Duration? ttl,
  }) {
    if (!isInitialized) return null;
    if (!ignoreExpiration && !isFresh(key, ttl)) return null;
    final raw = _box!.get(key);
    if (raw == null || raw['items'] is! List) return null;
    try {
      return (raw['items'] as List)
          .map((e) => fromJson(Map<String, dynamic>.from(e as Map)))
          .toList();
    } catch (_) {
      return null;
    }
  }

  // ── Lecture orchestrée (cache + réseau) ───────────────────────────────────

  Future<T> readOne({
    required String key,
    required Future<T> Function() fetch,
    Duration? ttl,
    CachePolicy policy = CachePolicy.cacheFirst,
  }) =>
      _read<T>(
        key: key,
        fetch: fetch,
        ttl: ttl,
        policy: policy,
        peek: (ignoreExp) =>
            peekOne(key, ignoreExpiration: ignoreExp, ttl: ttl),
        persist: (v) => writeOne(key, v),
      );

  Future<List<T>> readList({
    required String key,
    required Future<List<T>> Function() fetch,
    Duration? ttl,
    CachePolicy policy = CachePolicy.cacheFirst,
  }) =>
      _read<List<T>>(
        key: key,
        fetch: fetch,
        ttl: ttl,
        policy: policy,
        peek: (ignoreExp) =>
            peekList(key, ignoreExpiration: ignoreExp, ttl: ttl),
        persist: (v) => writeList(key, v),
      );

  Future<R> _read<R>({
    required String key,
    required Future<R> Function() fetch,
    required Duration? ttl,
    required CachePolicy policy,
    required R? Function(bool ignoreExpiration) peek,
    required Future<void> Function(R value) persist,
  }) async {
    if (policy == CachePolicy.cacheOnly) {
      final cached = peek(true);
      if (cached != null) return cached;
      throw StateError('Aucune donnée en cache pour "$key"');
    }

    if (policy == CachePolicy.cacheFirst) {
      final fresh = peek(false);
      if (fresh != null) return fresh;
    }

    try {
      final value = await fetch();
      await persist(value);
      return value;
    } catch (e) {
      final stale = peek(true);
      if (stale != null) {
        debugPrint(
          '[CacheStore:$boxName] réseau KO, fallback cache périmé ($key)',
        );
        return stale;
      }
      rethrow;
    }
  }

  // ── Métadonnées / invalidation ───────────────────────────────────────────

  Future<void> _touch(String key) => _metaBox!
      .put('${boxName}_$key', {'ts': DateTime.now().toIso8601String()});

  bool isFresh(String key, Duration? ttl) {
    if (ttl == null) return false; // sans TTL, jamais "frais" → réseau
    final meta = _metaBox?.get('${boxName}_$key');
    if (meta == null) return false;
    try {
      final ts = DateTime.parse(meta['ts'] as String);
      return DateTime.now().difference(ts) < ttl;
    } catch (_) {
      return false;
    }
  }

  Duration? ageOf(String key) {
    final meta = _metaBox?.get('${boxName}_$key');
    if (meta == null) return null;
    try {
      return DateTime.now().difference(DateTime.parse(meta['ts'] as String));
    } catch (_) {
      return null;
    }
  }

  Future<void> invalidate(String key) async {
    if (!isInitialized) return;
    await _box!.delete(key);
    await _metaBox!.delete('${boxName}_$key');
  }

  /// Vide entièrement la box (ex: à la déconnexion).
  Future<void> clear() async {
    if (!isInitialized) return;
    await _box!.clear();
    // On ne touche pas aux métadonnées des autres box : nettoyage ciblé.
    final keys = _metaBox!.keys
        .whereType<String>()
        .where((k) => k.startsWith('${boxName}_'))
        .toList();
    await _metaBox!.deleteAll(keys);
  }

  Future<void> close() async {
    await _box?.close();
    await _metaBox?.close();
  }
}
