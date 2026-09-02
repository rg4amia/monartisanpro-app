import 'dart:async';
import 'dart:io';

import 'package:flutter/foundation.dart';

/// Service de découverte réseau automatique.
///
/// Au démarrage, tente de joindre le serveur de production.
/// En cas d'échec, scanne le sous-réseau local pour trouver
/// le serveur Laravel (port 8000) et met en cache le résultat.
///
/// Utilisation :
///   await EnvConfig.init();      // dans main()
///   final url = EnvConfig.baseUrl; // partout ailleurs (synchrone)
class NetworkDiscoveryService {
  NetworkDiscoveryService._();

  /// Domaine de production. Surchargables au build :
  ///   flutter build apk --dart-define=API_HOST=prosartisan.net
  static const String _productionHost = String.fromEnvironment(
    'API_HOST',
    defaultValue: 'prosartisan.net',
  );
  static const String _productionBaseUrl = 'https://$_productionHost/api/v1';
  static const int _serverPort = 8000;
  static const String _apiSuffix = '/api/v1';

  static const Duration _probeTimeout = Duration(seconds: 8);
  static const Duration _localProbeTimeout = Duration(milliseconds: 800);

  /// URL résolue, accessible après [discover].
  static String? _resolvedUrl;

  /// Mode détecté : "production", "local", "emulator", ou "unknown".
  static String _mode = 'unknown';

  /// Getter synchrone — renvoie l'URL résolue ou le fallback production.
  static String get resolvedUrl => _resolvedUrl ?? _productionBaseUrl;

  /// Mode courant détecté.
  static String get mode => _mode;

  /// Point d'entrée principal — appeler une seule fois au démarrage.
  static Future<String> discover() async {
    // En environnement Web, pas de découverte réseau nécessaire.
    if (kIsWeb) {
      _resolvedUrl = 'http://localhost:$_serverPort$_apiSuffix';
      _mode = 'local';
      _log('Web détecté → localhost:$_serverPort');
      return _resolvedUrl!;
    }

    // Build de production : Forcer la production directement sans scan local (sécurité et priorité absolue pour les utilisateurs finaux)
    const bool isProduction = bool.fromEnvironment('dart.vm.product');
    if (isProduction) {
      _resolvedUrl = _productionBaseUrl;
      _mode = 'production';
      _log('✅ Build release : production forcée → $_productionBaseUrl');
      return _resolvedUrl!;
    }

    // ── 1. Émulateur Android ───────────────────────────────────────────────
    if (Platform.isAndroid) {
      const emulatorHost = '10.0.2.2';
      if (await _isServerReachable(emulatorHost, _serverPort, _localProbeTimeout)) {
        _resolvedUrl = 'http://$emulatorHost:$_serverPort$_apiSuffix';
        _mode = 'emulator';
        _log('✅ Émulateur Android détecté → $_resolvedUrl');
        return _resolvedUrl!;
      }
    }

    // ── 2. Découverte du sous-réseau local (Dev local) ──────────────────────
    final localUrl = await _discoverOnLocalNetwork();
    if (localUrl != null) {
      _resolvedUrl = localUrl;
      _mode = 'local';
      _log('✅ Serveur local trouvé → $_resolvedUrl');
      return _resolvedUrl!;
    }

    // ── 3. Tester la production si aucun serveur local n'est trouvé ─────────
    _log('Tentative de connexion à $_productionHost...');
    if (await _isServerReachable(_productionHost, 443, _probeTimeout)) {
      _resolvedUrl = _productionBaseUrl;
      _mode = 'production';
      _log('✅ Serveur de production joignable → $_productionBaseUrl');
      return _resolvedUrl!;
    }

    // ── 4. iOS Simulator fallback ──────────────────────────────────────────
    if (Platform.isIOS) {
      _resolvedUrl = 'http://localhost:$_serverPort$_apiSuffix';
      _mode = 'local';
      _log('⚠️ Fallback iOS Simulator → $_resolvedUrl');
      return _resolvedUrl!;
    }

    // ── 5. Fallback final → production ─────────────────────────────────────
    _resolvedUrl = _productionBaseUrl;
    _mode = 'production';
    _log('⚠️ Aucun serveur local trouvé, fallback → production');
    return _resolvedUrl!;
  }

  /// Force une re-découverte (ex: après un changement de réseau WiFi).
  static Future<String> rediscover() async {
    _resolvedUrl = null;
    _mode = 'unknown';
    _log('🔄 Re-découverte réseau déclenchée...');
    return discover();
  }

  /// Scanne les adresses du sous-réseau local en parallèle.
  static Future<String?> _discoverOnLocalNetwork() async {
    try {
      final interfaces = await NetworkInterface.list(
        type: InternetAddressType.IPv4,
        includeLoopback: false,
      );

      for (final iface in interfaces) {
        for (final addr in iface.addresses) {
          if (addr.isLoopback) continue;

          final parts = addr.address.split('.');
          if (parts.length != 4) continue;

          final subnet = '${parts[0]}.${parts[1]}.${parts[2]}';
          _log('Scan du sous-réseau $subnet.0/24 sur le port $_serverPort...');

          // Scanner l'ensemble des IP du sous-réseau (1..254)
          final hostCandidates = List<int>.generate(254, (i) => i + 1);

          // Exclure notre propre IP
          final selfHost = int.tryParse(parts[3]);
          if (selfHost != null) {
            hostCandidates.remove(selfHost);
          }

          // Lancer toutes les sondes en parallèle et prendre la première réponse.
          final completer = Completer<String?>();
          int pending = hostCandidates.length;

          for (final host in hostCandidates) {
            final ip = '$subnet.$host';
            _probeHost(ip).then((reachable) {
              if (reachable && !completer.isCompleted) {
                completer.complete('http://$ip:$_serverPort$_apiSuffix');
              }
              pending--;
              if (pending == 0 && !completer.isCompleted) {
                completer.complete(null);
              }
            });
          }

          // Timeout global pour le scan du sous-réseau
          final result = await completer.future.timeout(
            const Duration(seconds: 4),
            onTimeout: () => null,
          );

          if (result != null) return result;
        }
      }
    } catch (e) {
      _log('Erreur scan réseau : $e');
    }
    return null;
  }

  /// Sonde un hôte spécifique sur le port du serveur Laravel.
  static Future<bool> _probeHost(String ip) async {
    return _isServerReachable(ip, _serverPort, _localProbeTimeout);
  }

  /// Teste si un serveur est joignable.
  ///
  /// Pour la production (port 443), effectue une vraie requête HTTP pour
  /// s'assurer que le serveur Web ne ferme pas brusquement la connexion.
  /// Inclut un retry automatique (2 tentatives) pour les réseaux instables.
  static Future<bool> _isServerReachable(
    String host,
    int port,
    Duration timeout,
  ) async {
    if (port == 443 || host == _productionHost) {
      const maxAttempts = 2;
      for (var attempt = 1; attempt <= maxAttempts; attempt++) {
        try {
          // Validation TLS stricte : on ne contourne jamais les erreurs de
          // certificat, même pour une simple sonde de disponibilité.
          final client = HttpClient()..connectionTimeout = timeout;

          final url = Uri.parse('https://$host/client.html');
          final request = await client.getUrl(url).timeout(timeout);
          final response = await request.close().timeout(timeout);
          client.close();

          // Si le serveur répond avec un statut HTTP valide, la connexion est OK.
          if (response.statusCode < 500) return true;
        } catch (e) {
          _log('Tentative $attempt/$maxAttempts — échec test HTTP production ($host) : $e');
          if (attempt < maxAttempts) {
            await Future.delayed(Duration(seconds: 2 * attempt));
          }
        }
      }
      return false;
    }

    try {
      final socket = await Socket.connect(host, port, timeout: timeout);
      socket.destroy();
      return true;
    } catch (_) {
      return false;
    }
  }

  static void _log(String message) {
    debugPrint('[NetworkDiscovery] $message');
  }
}
