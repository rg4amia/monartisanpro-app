import 'dart:async';
import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:dio/dio.dart';
import 'package:get/get.dart';
import 'package:hive_flutter/hive_flutter.dart';

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
  Box<Map>? _queueBox;

  final RxBool isOffline = false.obs;
  late StreamSubscription<List<ConnectivityResult>> _connectivitySubscription;
  final Dio _dio = Dio(); // Une instance basique ou injectée selon l'archi globale

  Future<SyncService> init() async {
    await Hive.initFlutter();
    _queueBox = await Hive.openBox<Map>(_queueBoxName);

    // Vérification initiale
    final results = await Connectivity().checkConnectivity();
    _updateConnectionStatus(results);

    // Écoute des changements de réseau
    _connectivitySubscription = Connectivity().onConnectivityChanged.listen(_updateConnectionStatus);

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

  /// Tente de rejouer toutes les requêtes en file d'attente
  Future<void> _syncQueue() async {
    if (_queueBox == null || _queueBox!.isEmpty) return;

    final keys = _queueBox!.keys.toList();
    for (final key in keys) {
      final rawData = _queueBox!.get(key);
      if (rawData == null) continue;

      final request = QueuedRequest.fromJson(Map<String, dynamic>.from(rawData));

      try {
        if (request.method.toUpperCase() == 'POST') {
          await _dio.post(request.url, data: request.data);
        } else if (request.method.toUpperCase() == 'PUT') {
          await _dio.put(request.url, data: request.data);
        }
        
        // Succès : on retire de la file
        await _queueBox!.delete(key);
      } catch (e) {
        // En cas d'erreur de validation (422) ou autre (404), on pourrait décider de l'enlever
        // Mais si c'est un problème serveur temporaire, on le garde.
        if (e is DioException && e.type != DioExceptionType.connectionError) {
           await _queueBox!.delete(key); // Requête invalide, ne pas bloquer la queue éternellement
        }
      }
    }
  }

  @override
  void onClose() {
    _connectivitySubscription.cancel();
    _queueBox?.close();
    super.onClose();
  }
}
