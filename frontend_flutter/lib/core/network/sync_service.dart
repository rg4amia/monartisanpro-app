import 'dart:async';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:get/get.dart';
import 'package:hive_flutter/hive_flutter.dart';

import 'api_client.dart';

/// Modèle pour une requête en file d'attente
class QueuedRequest {
  final String id;
  final String method;
  final String url;
  final Map<String, dynamic>? data;
  final DateTime timestamp;

  QueuedRequest({
    required this.id,
    required this.method,
    required this.url,
    this.data,
    required this.timestamp,
  });

  Map<String, dynamic> toJson() => {
        'id': id,
        'method': method,
        'url': url,
        'data': data,
        'timestamp': timestamp.toIso8601String(),
      };

  factory QueuedRequest.fromJson(Map<String, dynamic> json) => QueuedRequest(
        id: json['id'],
        method: json['method'],
        url: json['url'],
        data: json['data'] != null ? Map<String, dynamic>.from(json['data']) : null,
        timestamp: DateTime.parse(json['timestamp']),
      );
}

/// Service pour gérer la file d'attente des requêtes et l'état du réseau
class SyncService extends GetxService {
  static const String _queueBoxName = 'offline_sync_queue';

  /// Durée maximale de conservation d'une requête en file (au-delà, on abandonne).
  static const Duration _maxRequestAge = Duration(days: 3);

  Box<Map>? _queueBox;

  final RxBool isOffline = false.obs;
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

    // Vérification initiale
    final results = await Connectivity().checkConnectivity();
    _updateConnectionStatus(results);

    // Écoute des changements de réseau
    _connectivitySubscription = Connectivity().onConnectivityChanged.listen(_updateConnectionStatus);

    // Si on démarre en ligne avec des requêtes en attente (app tuée hors-ligne),
    // on tente de les rejouer immédiatement.
    if (!isOffline.value) {
      unawaited(_syncQueue());
    }

    return this;
  }

  void _updateConnectionStatus(List<ConnectivityResult> results) {
    final offline = results.contains(ConnectivityResult.none) || results.isEmpty;
    if (isOffline.value != offline) {
      isOffline.value = offline;
      if (!offline) {
        _syncQueue(); // Retour de connexion
      }
    }
  }

  /// Met en file d'attente une requête échouée à cause du réseau
  Future<void> enqueueRequest(String method, String url, {Map<String, dynamic>? data}) async {
    if (_queueBox == null) return;
    
    final request = QueuedRequest(
      id: DateTime.now().millisecondsSinceEpoch.toString(),
      method: method,
      url: url,
      data: data,
      timestamp: DateTime.now(),
    );

    await _queueBox!.put(request.id, request.toJson());
  }

  /// Force une tentative de synchronisation (ex: après un login réussi).
  Future<void> flush() => _syncQueue();

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
          debugPrint('[SyncService] Requête expirée abandonnée: ${request.url}');
          continue;
        }

        try {
          switch (request.method.toUpperCase()) {
            case 'POST':
              await _dio.post(request.url, data: request.data);
            case 'PUT':
              await _dio.put(request.url, data: request.data);
            case 'DELETE':
              await _dio.delete(request.url);
          }

          // Succès : on retire de la file
          await _queueBox!.delete(key);
        } on DioException catch (e) {
          final status = e.response?.statusCode;

          if (e.type == DioExceptionType.connectionError ||
              e.type == DioExceptionType.connectionTimeout ||
              e.type == DioExceptionType.receiveTimeout ||
              e.type == DioExceptionType.sendTimeout ||
              status == 401 || // token pas encore rafraîchi → on retentera
              status == 408 ||
              status == 429 ||
              (status != null && status >= 500)) {
            // Problème transitoire : on garde la requête pour un prochain essai.
            debugPrint('[SyncService] Report de ${request.url} (status=$status)');
            continue;
          }

          // 4xx définitif (400/403/404/409/422…) : la requête ne passera
          // jamais, on la retire pour ne pas bloquer la file.
          await _queueBox!.delete(key);
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
    }
  }

  @override
  void onClose() {
    _connectivitySubscription.cancel();
    _queueBox?.close();
    super.onClose();
  }
}
