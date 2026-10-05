import 'dart:async';
import 'dart:io';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:get/get.dart' hide Response, FormData, MultipartFile;
import 'package:hive_flutter/hive_flutter.dart';

import '../utils/json_readers.dart';
import 'api_client.dart';

/// Modèle pour une requête en file d'attente
class QueuedRequest {
  final String id;
  final String method;
  final String url;
  final Map<String, dynamic>? data;
  final bool isMultipart;
  final Map<String, String>? filePaths;
  final DateTime timestamp;

  QueuedRequest({
    required this.id,
    required this.method,
    required this.url,
    this.data,
    this.isMultipart = false,
    this.filePaths,
    required this.timestamp,
  });

  Map<String, dynamic> toJson() => {
        'id': id,
        'method': method,
        'url': url,
        'data': data,
        'is_multipart': isMultipart,
        if (filePaths != null) 'file_paths': filePaths,
        'timestamp': timestamp.toIso8601String(),
      };

  factory QueuedRequest.fromJson(Map<String, dynamic> json) => QueuedRequest(
        id: json['id'] as String,
        method: json['method'] as String,
        url: json['url'] as String,
        data: json['data'] != null
            ? Map<String, dynamic>.from(json['data'] as Map)
            : null,
        isMultipart: json['is_multipart'] == true,
        filePaths: json['file_paths'] != null
            ? Map<String, String>.from(json['file_paths'] as Map)
            : null,
        timestamp: DateTime.parse(json['timestamp'] as String),
      );
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

  SyncFailure({
    required this.id,
    required this.label,
    required this.reason,
    required this.at,
  });

  Map<String, dynamic> toJson() => {
        'id': id,
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

    return SyncFailure(id: id, label: label, reason: reason, at: at);
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
    final what = order.group(2) == 'verify-pickup' ? 'du retrait' : 'de la livraison';

    return 'Validation $what de la commande #${order.group(1)}';
  }

  if (RegExp(r'/jalons/\d+/photos').hasMatch(url)) {
    return "Envoi des photos d'une étape de chantier";
  }

  return 'Action enregistrée hors connexion';
}

/// Service pour gérer la file d'attente des requêtes et l'état du réseau
class SyncService extends GetxService {
  static const String _queueBoxName = 'offline_sync_queue';
  static const String _failuresBoxName = 'offline_sync_failures';

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
  late StreamSubscription<List<ConnectivityResult>> _connectivitySubscription;

  /// On rejoue les requêtes via le client applicatif : il porte le baseUrl
  /// courant (découverte réseau) ET le header `Authorization` via ses
  /// intercepteurs. Une instance `Dio()` nue enverrait des requêtes sans
  /// hôte ni token.
  Dio get _dio => ApiClient().dio;

  /// Empêche deux passes de synchro simultanées.
  bool _syncing = false;

  Future<SyncService> init() async {
    await Hive.initFlutter();
    _queueBox = await Hive.openBox<Map>(_queueBoxName);
    _failuresBox = await Hive.openBox<Map>(_failuresBoxName);
    failures.assignAll(
      _failuresBox!.values.map(SyncFailure.tryParse).whereType<SyncFailure>(),
    );

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
    );

    await _queueBox!.put(request.id, request.toJson());
    pendingCount.value = _queueBox!.length;
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
    );

    await _queueBox!.put(request.id, request.toJson());
    pendingCount.value = _queueBox!.length;
  }

  /// Force une tentative de synchronisation (ex: après un login réussi).
  Future<void> flush() => _syncQueue();

  /// Consigne une action qui n'a pas abouti, pour l'annoncer à l'utilisateur.
  Future<void> recordFailure(QueuedRequest request, String reason) async {
    final failure = SyncFailure(
      id: request.id,
      label: describeQueuedAction(request.url),
      reason: reason,
      at: DateTime.now(),
    );

    failures.add(failure);
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

        final request =
            QueuedRequest.fromJson(Map<String, dynamic>.from(rawData));

        // Abandon des requêtes trop anciennes pour ne pas rejouer une
        // mutation obsolète (statut déjà changé, jalon déjà soumis…).
        if (DateTime.now().difference(request.timestamp) > _maxRequestAge) {
          await _queueBox!.delete(key);
          await recordFailure(
            request,
            "Elle n'a pas pu être transmise dans les ${_maxRequestAge.inDays} jours.",
          );
          pendingCount.value = _queueBox!.length;
          debugPrint(
            '[SyncService] Requête expirée abandonnée: ${request.url}',
          );
          continue;
        }

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
      if (_queueBox != null) {
        pendingCount.value = _queueBox!.length;
      }
    }
  }

  @override
  void onClose() {
    _connectivitySubscription.cancel();
    _retryTimer?.cancel();
    _queueBox?.close();
    _failuresBox?.close();
    super.onClose();
  }
}
