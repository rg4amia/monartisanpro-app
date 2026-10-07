import 'dart:async';
import 'dart:io';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:get/get.dart' hide Response, FormData, MultipartFile;
import 'package:hive_flutter/hive_flutter.dart';

import '../cache/hive_cipher_provider.dart';
import '../storage/storage_service.dart';
import '../utils/json_readers.dart';
import 'api_client.dart';

/// Une requête en file, ou l'annonce d'un échec, n'appartient qu'au compte
/// qui l'a créée. Sans propriétaire connu, elle n'est à personne : rejouée,
/// elle partirait avec le jeton du compte connecté à ce moment-là.
bool belongsToAccount(int? ownerId, int? currentUserId) =>
    ownerId != null && ownerId == currentUserId;

/// Modèle pour une requête en file d'attente
class QueuedRequest {
  final String id;
  final String method;
  final String url;
  final Map<String, dynamic>? data;
  final bool isMultipart;
  final Map<String, String>? filePaths;
  final DateTime timestamp;

  /// Compte qui a fait l'action : seul lui la rejoue.
  final int? ownerId;

  QueuedRequest({
    required this.id,
    required this.method,
    required this.url,
    this.data,
    this.isMultipart = false,
    this.filePaths,
    required this.timestamp,
    this.ownerId,
  });

  Map<String, dynamic> toJson() => {
        'id': id,
        if (ownerId != null) 'owner_id': ownerId,
        'method': method,
        'url': url,
        'data': data,
        'is_multipart': isMultipart,
        if (filePaths != null) 'file_paths': filePaths,
        'timestamp': timestamp.toIso8601String(),
      };

  /// `null` sur une entrée illisible : une requête abîmée ne bloque pas les
  /// autres. Lue par transtypage direct, elle interrompait tout le rejeu.
  static QueuedRequest? tryParse(dynamic raw) {
    final json = readMap(raw);
    final id = readString(json?['id']);
    final method = readString(json?['method']);
    final url = readString(json?['url']);
    final timestamp = DateTime.tryParse(readString(json?['timestamp']) ?? '');
    if (id == null || method == null || url == null || timestamp == null) {
      return null;
    }

    return QueuedRequest(
      id: id,
      method: method,
      url: url,
      data: readMap(json?['data']),
      isMultipart: readBool(json?['is_multipart']) ?? false,
      filePaths: readMap(json?['file_paths'])
          ?.map((key, value) => MapEntry(key, value.toString())),
      timestamp: timestamp,
      ownerId: readInt(json?['owner_id']),
    );
  }
}

/// Action enregistrée hors connexion qui n'a finalement pas abouti : refusée
/// par le serveur au rejeu, ou jamais transmise dans le délai.
///
/// L'utilisateur a vu « Enregistré hors connexion » : sans cette trace, il
/// croit l'action faite — pour une validation de livraison, un livreur non
/// payé sans le savoir.
class SyncFailure {
  final String id;

  /// Ce que l'utilisateur avait fait (« Validation de la livraison… »).
  final String label;

  /// Pourquoi cela n'a pas abouti, dans les mots du serveur quand il en donne.
  final String reason;
  final DateTime at;

  /// Compte à qui l'annonce est destinée.
  final int? ownerId;

  SyncFailure({
    required this.id,
    required this.label,
    required this.reason,
    required this.at,
    this.ownerId,
  });

  Map<String, dynamic> toJson() => {
        'id': id,
        if (ownerId != null) 'owner_id': ownerId,
        'label': label,
        'reason': reason,
        'at': at.toIso8601String(),
      };

  /// `null` sur une entrée illisible : une trace abîmée ne bloque pas les autres.
  static SyncFailure? tryParse(dynamic raw) {
    final map = readMap(raw);
    final id = readString(map?['id']);
    final label = readString(map?['label']);
    final reason = readString(map?['reason']);
    final at = DateTime.tryParse(readString(map?['at']) ?? '');
    if (id == null || label == null || reason == null || at == null) {
      return null;
    }

    return SyncFailure(
      id: id,
      label: label,
      reason: reason,
      at: at,
      ownerId: readInt(map?['owner_id']),
    );
  }
}

/// Suite à donner à une requête rejouée qui échoue.
enum ReplayOutcome {
  /// Panne passagère ou refus temporaire : la requête reste en file.
  retryLater,

  /// Refus définitif du serveur : la requête ne passera jamais.
  rejected,
}

/// Une requête n'est retirée de la file que sur un refus définitif.
///
/// 429 est temporaire : c'est ce que répond le serveur quand la saisie d'un
/// code de commande est suspendue après des codes faux. Le bon code, mis en
/// file hors connexion, doit être représenté une fois la suspension levée.
ReplayOutcome classifyReplayError(DioException e) {
  final status = e.response?.statusCode;

  final transient = e.type == DioExceptionType.connectionError ||
      e.type == DioExceptionType.connectionTimeout ||
      e.type == DioExceptionType.receiveTimeout ||
      e.type == DioExceptionType.sendTimeout ||
      status == 401 || // jeton pas encore rafraîchi
      status == 408 ||
      status == 429 ||
      (status != null && status >= 500);

  return transient ? ReplayOutcome.retryLater : ReplayOutcome.rejected;
}

/// Délai annoncé par le serveur avant un nouvel essai (`Retry-After`, en
/// secondes), borné entre 30 secondes et 1 heure. `null` s'il n'en donne pas.
Duration? retryDelayOf(DioException e) {
  final header = e.response?.headers.value('retry-after');
  final seconds = int.tryParse(header ?? '') ??
      readInt(readMap(e.response?.data)?['retry_after']);
  if (seconds == null || seconds <= 0) return null;

  return Duration(seconds: seconds.clamp(30, 3600));
}

/// Motif d'un refus, dans les mots du serveur quand il en donne.
String rejectionReasonOf(DioException e) {
  final body = e.response?.data;

  return readApiMessage(body) ??
      readString(readMap(body)?['error']) ??
      'Le serveur a refusé cette action.';
}

/// Nomme, pour l'utilisateur, l'action portée par une requête en file.
String describeQueuedAction(String url) {
  final order =
      RegExp(r'/orders/(\d+)/(verify-pickup|verify-delivery)').firstMatch(url);
  if (order != null) {
    final what =
        order.group(2) == 'verify-pickup' ? 'du retrait' : 'de la livraison';

    return 'Validation $what de la commande #${order.group(1)}';
  }

  if (RegExp(r'/jalons/\d+/photos').hasMatch(url)) {
    return "Envoi des photos d'une étape de chantier";
  }

  return 'Action enregistrée hors connexion';
}

/// Service pour gérer la file d'attente des requêtes et l'état du réseau
class SyncService extends GetxService {
  SyncService({int? Function()? currentUserId})
      : _currentUserId = currentUserId ?? StorageService.getUserId;

  /// Compte connecté, relu à chaque usage : il change sans que le service
  /// soit recréé.
  final int? Function() _currentUserId;

  // Boîtes chiffrées : le corps d'une validation mise en file porte le code
  // de retrait ou de réception (Règle d'or 38). Les anciennes boîtes, en
  // clair, sont reprises puis supprimées par [_migrateLegacyBoxes].
  static const String _queueBoxName = 'offline_sync_queue_v2';
  static const String _failuresBoxName = 'offline_sync_failures_v2';
  static const String _legacyQueueBoxName = 'offline_sync_queue';
  static const String _legacyFailuresBoxName = 'offline_sync_failures';

  /// Durée maximale de conservation d'une requête en file (au-delà, on abandonne).
  static const Duration _maxRequestAge = Duration(days: 3);

  Box<Map>? _queueBox;
  Box<Map>? _failuresBox;

  /// Nouvel essai programmé quand le serveur demande d'attendre (429).
  Timer? _retryTimer;

  final RxBool isOffline = false.obs;

  /// Nombre de requêtes en attente de rejeu.
  final RxInt pendingCount = 0.obs;

  /// Actions enregistrées hors connexion qui n'ont pas abouti : refusées par
  /// le serveur au rejeu, ou jamais transmises dans le délai.
  ///
  /// Conservées jusqu'à ce que l'utilisateur les ait lues ([dismissFailure]) et
  /// affichées par `OfflineBanner`. Elles n'étaient signalées nulle part : pour
  /// une validation de livraison, un livreur non payé sans que personne ne le
  /// sache.
  final RxList<SyncFailure> failures = <SyncFailure>[].obs;
  StreamSubscription<List<ConnectivityResult>>? _connectivitySubscription;

  /// On rejoue les requêtes via le client applicatif : il porte le baseUrl
  /// courant (découverte réseau) ET le header `Authorization` via ses
  /// intercepteurs. Une instance `Dio()` nue enverrait des requêtes sans
  /// hôte ni token.
  Dio get _dio => ApiClient().dio;

  /// Empêche deux passes de synchro simultanées.
  bool _syncing = false;

  Future<SyncService> init() async {
    await openStorage();

    // Vérification initiale
    final results = await Connectivity().checkConnectivity();
    _updateConnectionStatus(results);

    // Écoute des changements de réseau
    _connectivitySubscription =
        Connectivity().onConnectivityChanged.listen(_updateConnectionStatus);

    // Si on démarre en ligne avec des requêtes en attente (app tuée hors-ligne),
    // on tente de les rejouer immédiatement.
    if (!isOffline.value) {
      unawaited(_syncQueue());
    }

    return this;
  }

  /// Ouvre les boîtes chiffrées de la file et reprend celles des versions
  /// précédentes. Séparé de [init], qui écoute en plus l'état du réseau.
  @visibleForTesting
  Future<void> openStorage() async {
    await Hive.initFlutter();
    final cipher = await HiveCipherProvider.cipher();
    _queueBox = await _openEncrypted(_queueBoxName, cipher);
    _failuresBox = await _openEncrypted(_failuresBoxName, cipher);
    await _migrateLegacyBoxes();
    _reloadForCurrentAccount();
  }

  /// Fichier de la file sur le disque.
  @visibleForTesting
  String? get queueStoragePath => _queueBox?.path;

  static Future<Box<Map>> _openEncrypted(
    String name,
    HiveAesCipher cipher,
  ) async {
    try {
      return await Hive.openBox<Map>(name, encryptionCipher: cipher);
    } catch (_) {
      // Boîte illisible (clé de chiffrement perdue après une restauration).
      await Hive.deleteBoxFromDisk(name);
      return Hive.openBox<Map>(name, encryptionCipher: cipher);
    }
  }

  /// Reprend les boîtes en clair des versions précédentes, puis les supprime.
  ///
  /// Leurs entrées ne portaient pas de propriétaire : elles sont attribuées au
  /// compte connecté à la mise à jour, et abandonnées si personne ne l'est.
  Future<void> _migrateLegacyBoxes() async {
    final pairs = {
      _legacyQueueBoxName: _queueBox,
      _legacyFailuresBoxName: _failuresBox,
    };

    for (final pair in pairs.entries) {
      try {
        if (!await Hive.boxExists(pair.key)) continue;

        final legacy = await Hive.openBox<Map>(pair.key);
        final owner = _currentUserId();
        if (owner != null) {
          for (final entry in legacy.toMap().entries) {
            await pair.value?.put(entry.key, {
              ...Map<String, dynamic>.from(entry.value),
              'owner_id': owner,
            });
          }
        }
        await legacy.deleteFromDisk();
      } catch (_) {
        try {
          await Hive.deleteBoxFromDisk(pair.key);
        } catch (_) {
          // Rien de plus à tenter : la boîte sera reprise au prochain démarrage.
        }
      }
    }
  }

  /// Requêtes en file du compte connecté.
  int _ownedPendingCount() {
    final box = _queueBox;
    if (box == null) return 0;

    final userId = _currentUserId();

    return box.values
        .where((raw) => belongsToAccount(readInt(raw['owner_id']), userId))
        .length;
  }

  /// Ne montre que ce qui appartient au compte connecté.
  void _reloadForCurrentAccount() {
    final userId = _currentUserId();

    failures.assignAll(
      (_failuresBox?.values ?? const <Map>[])
          .map(SyncFailure.tryParse)
          .whereType<SyncFailure>()
          .where((f) => belongsToAccount(f.ownerId, userId)),
    );
    pendingCount.value = _ownedPendingCount();
  }

  /// Fin de session : plus rien n'est montré ni rejoué. Les actions en file
  /// restent, chiffrées, pour leur propriétaire s'il se reconnecte.
  void onSessionEnded() {
    _retryTimer?.cancel();
    failures.clear();
    pendingCount.value = 0;
  }

  /// Compte supprimé : ses actions en file et ses annonces disparaissent.
  Future<void> purgeAccount(int userId) async {
    for (final box in [_queueBox, _failuresBox]) {
      if (box == null) continue;

      final keys = box.keys
          .where((key) => readInt(box.get(key)?['owner_id']) == userId)
          .toList();
      await box.deleteAll(keys);
    }
    _reloadForCurrentAccount();
  }

  void _updateConnectionStatus(List<ConnectivityResult> results) {
    final offline =
        results.contains(ConnectivityResult.none) || results.isEmpty;
    if (isOffline.value != offline) {
      isOffline.value = offline;
      if (!offline) {
        _syncQueue(); // Retour de connexion
      }
    }
  }

  /// Met en file d'attente une requête échouée à cause du réseau
  Future<void> enqueueRequest(
    String method,
    String url, {
    Map<String, dynamic>? data,
  }) async {
    if (_queueBox == null) return;

    final request = QueuedRequest(
      id: DateTime.now().millisecondsSinceEpoch.toString(),
      method: method,
      url: url,
      data: data,
      timestamp: DateTime.now(),
      ownerId: _currentUserId(),
    );

    await _queueBox!.put(request.id, request.toJson());
    pendingCount.value = _ownedPendingCount();
  }

  /// Met en file d'attente une requête multipart (avec fichiers locaux)
  Future<void> enqueueMultipartRequest(
    String url, {
    Map<String, dynamic>? data,
    Map<String, String>? filePaths,
  }) async {
    if (_queueBox == null) return;

    final request = QueuedRequest(
      id: DateTime.now().millisecondsSinceEpoch.toString(),
      method: 'POST',
      url: url,
      data: data,
      isMultipart: true,
      filePaths: filePaths,
      timestamp: DateTime.now(),
      ownerId: _currentUserId(),
    );

    await _queueBox!.put(request.id, request.toJson());
    pendingCount.value = _ownedPendingCount();
  }

  /// Force une tentative de synchronisation (ex: après un login réussi).
  Future<void> flush() {
    _reloadForCurrentAccount();

    return _syncQueue();
  }

  /// Consigne une action qui n'a pas abouti, pour l'annoncer à l'utilisateur.
  Future<void> recordFailure(QueuedRequest request, String reason) async {
    final failure = SyncFailure(
      id: request.id,
      label: describeQueuedAction(request.url),
      reason: reason,
      at: DateTime.now(),
      ownerId: request.ownerId,
    );

    // Consignée pour son propriétaire ; montrée seulement s'il est connecté.
    if (request.ownerId == _currentUserId()) failures.add(failure);
    await _failuresBox?.put(failure.id, failure.toJson());
  }

  /// L'utilisateur a lu l'annonce : elle disparaît.
  Future<void> dismissFailure(String id) async {
    failures.removeWhere((f) => f.id == id);
    await _failuresBox?.delete(id);
  }

  void _scheduleRetry(Duration delay) {
    _retryTimer?.cancel();
    _retryTimer = Timer(delay, _syncQueue);
  }

  /// Tente de rejouer toutes les requêtes en file d'attente.
  Future<void> _syncQueue() async {
    if (_queueBox == null || _queueBox!.isEmpty || _syncing) return;
    _syncing = true;

    try {
      final keys = _queueBox!.keys.toList();
      for (final key in keys) {
        final rawData = _queueBox!.get(key);
        if (rawData == null) continue;

        final request = QueuedRequest.tryParse(rawData);
        if (request == null) {
          await _queueBox!.delete(key);
          continue;
        }

        // Seul le compte qui a fait l'action la rejoue : sinon elle partirait
        // avec le jeton d'un autre. Elle expire tout de même, pour lui.
        final owned = belongsToAccount(request.ownerId, _currentUserId());

        // Abandon des requêtes trop anciennes pour ne pas rejouer une
        // mutation obsolète (statut déjà changé, jalon déjà soumis…).
        if (DateTime.now().difference(request.timestamp) > _maxRequestAge) {
          await _queueBox!.delete(key);
          await recordFailure(
            request,
            "Elle n'a pas pu être transmise dans les ${_maxRequestAge.inDays} jours.",
          );
          debugPrint(
            '[SyncService] Requête expirée abandonnée: ${request.url}',
          );
          continue;
        }

        if (!owned) continue;

        try {
          if (request.isMultipart) {
            final formMap = <String, dynamic>{};
            if (request.data != null) {
              formMap.addAll(request.data!);
            }
            if (request.filePaths != null) {
              for (final entry in request.filePaths!.entries) {
                final file = File(entry.value);
                if (await file.exists()) {
                  formMap[entry.key] = await MultipartFile.fromFile(
                    entry.value,
                    filename: entry.value.split(RegExp(r'[/\\]')).last,
                  );
                } else {
                  debugPrint(
                    '[SyncService] Fichier local introuvable pour rejeu: ${entry.value}',
                  );
                }
              }
            }
            final formData = FormData.fromMap(formMap);
            await _dio.post(request.url, data: formData);
          } else {
            switch (request.method.toUpperCase()) {
              case 'POST':
                await _dio.post(request.url, data: request.data);
              case 'PUT':
                await _dio.put(request.url, data: request.data);
              case 'DELETE':
                await _dio.delete(request.url);
            }
          }

          // Succès : on retire de la file
          await _queueBox!.delete(key);
        } on DioException catch (e) {
          final status = e.response?.statusCode;

          if (classifyReplayError(e) == ReplayOutcome.retryLater) {
            // Problème transitoire : on garde la requête pour un prochain
            // essai. Rien d'autre ne relance la file tant que le réseau ne
            // change pas : si le serveur donne un délai, on s'y tient.
            final delay = retryDelayOf(e);
            if (delay != null) _scheduleRetry(delay);
            debugPrint(
              '[SyncService] Report de ${request.url} (status=$status)',
            );
            continue;
          }

          // 4xx définitif (400/403/404/409/422…) : la requête ne passera
          // jamais, on la retire pour ne pas bloquer la file — et on le dit.
          await _queueBox!.delete(key);
          await recordFailure(request, rejectionReasonOf(e));
          debugPrint(
            '[SyncService] Requête rejetée définitivement '
            '(${status ?? e.type}): ${request.url}',
          );
        } catch (e) {
          // Erreur inattendue (parsing, etc.) : on ne bloque pas la file mais
          // on garde la requête pour investigation via l'âge maximal.
          debugPrint('[SyncService] Erreur inattendue sur ${request.url}: $e');
        }
      }
    } finally {
      _syncing = false;
      pendingCount.value = _ownedPendingCount();
    }
  }

  @override
  void onClose() {
    _connectivitySubscription?.cancel();
    _retryTimer?.cancel();
    _queueBox?.close();
    _failuresBox?.close();
    super.onClose();
  }
}
