import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;

import '../storage/storage_service.dart';
import 'api_endpoints.dart';

class RealtimeEvent {
  final int id;
  final String type;
  final Map<String, dynamic> data;

  const RealtimeEvent({
    required this.id,
    required this.type,
    required this.data,
  });
}

/// Service de flux SSE temps réel natif HTTP, résilient aux coupures 3G/4G
/// et 100% compatible avec tout hébergement (Hostinger, cPanel, Nginx).
class RealtimeStreamService {
  RealtimeStreamService({required this.missionId});

  final int missionId;
  final _eventController = StreamController<RealtimeEvent>.broadcast();
  bool _isActive = false;
  int _lastEventId = 0;
  http.Client? _client;

  Stream<RealtimeEvent> get events => _eventController.stream;

  void start() {
    if (_isActive) return;
    _isActive = true;
    _connectLoop();
  }

  void stop() {
    _isActive = false;
    _client?.close();
    _client = null;
  }

  void dispose() {
    stop();
    _eventController.close();
  }

  Future<void> _connectLoop() async {
    while (_isActive) {
      try {
        await _connectAndListen();
      } catch (e) {
        debugPrint('[RealtimeStream] Déconnexion flux : $e');
      }

      if (_isActive) {
        // Reconnexion automatique avec délai de sécurité
        await Future.delayed(const Duration(seconds: 2));
      }
    }
  }

  Future<void> _connectAndListen() async {
    _client?.close();
    _client = http.Client();

    final token = await StorageService.getToken();
    final uri = Uri.parse(
      '${ApiEndpoints.baseUrl}${ApiEndpoints.missionStream(missionId)}?last_event_id=$_lastEventId',
    );

    final request = http.Request('GET', uri)
      ..headers['Accept'] = 'text/event-stream'
      ..headers['Cache-Control'] = 'no-cache';

    if (token != null && token.isNotEmpty) {
      request.headers['Authorization'] = 'Bearer $token';
    }

    if (_lastEventId > 0) {
      request.headers['Last-Event-ID'] = _lastEventId.toString();
    }

    final response = await _client!.send(request);

    if (response.statusCode != 200) {
      throw Exception('Erreur HTTP flux SSE: ${response.statusCode}');
    }

    var currentId = _lastEventId;
    var currentType = 'message';
    final dataLines = <String>[];

    await for (final line in response.stream
        .transform(utf8.decoder)
        .transform(const LineSplitter())) {
      if (!_isActive) break;

      final trimmed = line.trim();
      if (trimmed.isEmpty) {
        if (dataLines.isNotEmpty) {
          final joinedData = dataLines.join('\n');
          try {
            final parsed = jsonDecode(joinedData);
            if (parsed is Map<String, dynamic>) {
              _eventController.add(
                RealtimeEvent(
                  id: currentId,
                  type: currentType,
                  data: parsed,
                ),
              );
            }
          } catch (_) {}
          dataLines.clear();
        }
        currentType = 'message';
        continue;
      }

      if (trimmed.startsWith(':')) {
        // Commentaire SSE / battement de cœur
        continue;
      }

      if (trimmed.startsWith('id:')) {
        final rawId = trimmed.substring(3).trim();
        final parsedId = int.tryParse(rawId);
        if (parsedId != null) {
          currentId = parsedId;
          _lastEventId = parsedId;
        }
      } else if (trimmed.startsWith('event:')) {
        currentType = trimmed.substring(6).trim();
      } else if (trimmed.startsWith('data:')) {
        dataLines.add(trimmed.substring(5).trim());
      }
    }
  }
}
