import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:frontend_flutter/core/config/env_config.dart';
import 'package:image_picker/image_picker.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:webview_flutter/webview_flutter.dart';

class IaAssistantScreen extends StatefulWidget {
  const IaAssistantScreen({super.key});

  @override
  State<IaAssistantScreen> createState() => _IaAssistantScreenState();
}

class _IaAssistantScreenState extends State<IaAssistantScreen> {
  late final WebViewController _controller;
  bool _isLoading = true;
  bool _hasError = false;
  String _errorMessage = '';
  int _retryCount = 0;
  static const int _maxRetries = 3;

  @override
  void initState() {
    super.initState();
    _requestPermissions();
    _initWebView();
  }

  Future<void> _requestPermissions() async {
    try {
      await Permission.camera.request();
      // On Android 13+ photos is used, on Android 12- storage is used.
      if (await Permission.photos.request().isDenied) {
        await Permission.storage.request();
      }
    } catch (e) {
      debugPrint('Error requesting permissions: $e');
    }
  }

  Future<void> _selectAndSendImage() async {
    final ImagePicker picker = ImagePicker();

    final ImageSource? source = await showModalBottomSheet<ImageSource>(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (BuildContext context) {
        return SafeArea(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              ListTile(
                leading: const Icon(Icons.camera_alt, color: Color(0xFF06B6D4)),
                title: const Text('Prendre une photo (Appareil photo)'),
                onTap: () => Navigator.pop(context, ImageSource.camera),
              ),
              ListTile(
                leading:
                    const Icon(Icons.photo_library, color: Color(0xFF8B5CF6)),
                title: const Text('Choisir depuis la galerie'),
                onTap: () => Navigator.pop(context, ImageSource.gallery),
              ),
            ],
          ),
        );
      },
    );

    if (source == null) return;

    try {
      final XFile? image = await picker.pickImage(
        source: source,
        maxWidth: 1024,
        maxHeight: 1024,
        imageQuality: 80,
      );

      if (image == null) return;

      final bytes = await image.readAsBytes();
      final base64Image = base64Encode(bytes);
      final filename = image.name;
      final fileSize = bytes.length;

      final String jsData = jsonEncode({
        'base64': base64Image,
        'filename': filename,
        'fileSize': fileSize,
      });

      await _controller.runJavaScript(
        '(() => { const data = $jsData; window.handleFlutterImage(data.base64, data.filename, data.fileSize); })()',
      );
    } catch (e) {
      debugPrint('Error picking/reading/sending image: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text("Erreur d'accès à l'appareil photo/galerie : $e"),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  /// Construit l'URL de client.html à partir de l'URL API résolue.
  String _buildAssistantUrl() {
    final uri = Uri.parse(EnvConfig.baseUrl);
    final host = uri.host;
    final timestamp = DateTime.now().millisecondsSinceEpoch;
    return uri.hasPort
        ? '${uri.scheme}://$host:${uri.port}/client.html?t=$timestamp'
        : '${uri.scheme}://$host/client.html?t=$timestamp';
  }

  void _initWebView() {
    final assistantUrl = _buildAssistantUrl();
    debugPrint(
      '[IaAssistant] Chargement WebView → $assistantUrl (mode: ${EnvConfig.currentMode})',
    );

    setState(() {
      _isLoading = true;
      _hasError = false;
      _errorMessage = '';
    });

    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(const Color(0xFFF8FAFC))
      ..setUserAgent(
        'Mozilla/5.0 (Linux; Android 10; K) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Mobile Safari/537.36',
      )
      ..clearCache()
      ..clearLocalStorage()
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (String url) {
            setState(() {
              _isLoading = true;
              _hasError = false;
            });
          },
          onPageFinished: (String url) {
            setState(() {
              _isLoading = false;
            });
          },
          onWebResourceError: (WebResourceError error) {
            debugPrint(
              'WebView error (isForMainFrame: ${error.isForMainFrame}, url: ${error.url}): ${error.description}',
            );

            // Ne bloquer la page entière QUE si l'erreur concerne le cadre principal (main frame)
            final bool isMainFrameError = error.isForMainFrame ?? true;
            if (!isMainFrameError) {
              // Ignorer les erreurs de sous-ressources secondaires (WS, favicons, etc.)
              return;
            }

            // Retry automatique avant d'afficher l'erreur
            if (_retryCount < _maxRetries - 1) {
              _retryCount++;
              setState(() {
                _isLoading = true;
                _hasError = false;
              });
              Future.delayed(const Duration(seconds: 2), () {
                if (mounted) {
                  final assistantUrl = _buildAssistantUrl();
                  debugPrint(
                    '[IaAssistant] Retry automatique $_retryCount/$_maxRetries → $assistantUrl',
                  );
                  _controller.loadRequest(Uri.parse(assistantUrl));
                }
              });
            } else {
              setState(() {
                _isLoading = false;
                _hasError = true;
                _errorMessage = error.description;
              });
            }
          },
        ),
      )
      ..addJavaScriptChannel(
        'FlutterNotificationChannel',
        onMessageReceived: (JavaScriptMessage message) {
          try {
            final data = jsonDecode(message.message) as Map<String, dynamic>;
            final event = data['event'];
            final msg = data['message'] ?? '';

            if (event == 'select_image') {
              _selectAndSendImage();
              return;
            }

            final color = event == 'auth_success'
                ? const Color(0xFF10B981) // Emerald Green
                : const Color(0xFFEF4444); // Red

            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text(
                  msg,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.bold,
                    fontSize: 13.0,
                  ),
                ),
                backgroundColor: color,
                behavior: SnackBarBehavior.floating,
                margin: const EdgeInsets.all(16.0),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12.0),
                ),
                duration: const Duration(seconds: 4),
              ),
            );
          } catch (e) {
            debugPrint('Error parsing channel message: $e');
          }
        },
      )
      ..loadRequest(Uri.parse(assistantUrl));
  }

  /// Re-découvre le réseau et recharge la WebView.
  Future<void> _retryConnection() async {
    setState(() {
      _isLoading = true;
      _hasError = false;
      _errorMessage = '';
      _retryCount =
          0; // Reset le compteur pour une nouvelle série de tentatives
    });

    // Relancer la découverte réseau
    await EnvConfig.rediscover();

    // Recharger avec la nouvelle URL
    final assistantUrl = _buildAssistantUrl();
    debugPrint(
      '[IaAssistant] Retry → $assistantUrl (mode: ${EnvConfig.currentMode})',
    );
    unawaited(_controller.loadRequest(Uri.parse(assistantUrl)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text(
          'Assistant IA Chantier',
          style:
              TextStyle(color: Color(0xFF0F172A), fontWeight: FontWeight.bold),
        ),
        backgroundColor: const Color(0xFFFFFFFF),
        elevation: 0,
        iconTheme: const IconThemeData(color: Color(0xFF0F172A)),
        actions: [
          // Badge indiquant le mode de connexion
          Center(
            child: Container(
              margin: const EdgeInsets.only(right: 12),
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: _modeColor.withValues(alpha: 0.15),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: _modeColor.withValues(alpha: 0.3)),
              ),
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(_modeIcon, size: 12, color: _modeColor),
                  const SizedBox(width: 4),
                  Text(
                    _modeLabel,
                    style: TextStyle(
                      fontSize: 10,
                      fontWeight: FontWeight.bold,
                      color: _modeColor,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
      body: SafeArea(
        child: Stack(
          children: [
            // WebView
            WebViewWidget(controller: _controller),

            // Loading spinner avec indicateur de retry
            if (_isLoading && !_hasError)
              Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const CircularProgressIndicator(
                      color: Color(0xFF06B6D4),
                    ),
                    if (_retryCount > 0) ...[
                      const SizedBox(height: 12),
                      Text(
                        'Tentative ${_retryCount + 1}/$_maxRetries...',
                        style: const TextStyle(
                          fontSize: 12,
                          color: Color(0xFF64748B),
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
                  ],
                ),
              ),

            // Panneau d'erreur avec retry (fond opaque propre)
            if (_hasError)
              Container(
                color: const Color(0xFFF8FAFC),
                width: double.infinity,
                height: double.infinity,
                child: Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24.0),
                    child: Card(
                      elevation: 4,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Padding(
                        padding: const EdgeInsets.all(24.0),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            const Icon(
                              Icons.wifi_off_rounded,
                              size: 56,
                              color: Color(0xFFEF4444),
                            ),
                            const SizedBox(height: 16),
                            const Text(
                              'Erreur de connexion',
                              style: TextStyle(
                                fontSize: 18,
                                fontWeight: FontWeight.bold,
                                color: Color(0xFF0F172A),
                              ),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              'Impossible de joindre le serveur.\n'
                              'Mode actuel : ${EnvConfig.currentMode}\n'
                              'URL : ${_buildAssistantUrl()}',
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                fontSize: 12,
                                color: Color(0xFF64748B),
                              ),
                            ),
                            if (_errorMessage.isNotEmpty) ...[
                              const SizedBox(height: 6),
                              Text(
                                _errorMessage,
                                textAlign: TextAlign.center,
                                style: const TextStyle(
                                  fontSize: 10,
                                  color: Color(0xFF94A3B8),
                                ),
                              ),
                            ],
                            const SizedBox(height: 20),
                            ElevatedButton.icon(
                              onPressed: _retryConnection,
                              icon: const Icon(Icons.refresh_rounded, size: 18),
                              label: const Text('Re-scanner le réseau'),
                              style: ElevatedButton.styleFrom(
                                backgroundColor: const Color(0xFF06B6D4),
                                foregroundColor: Colors.white,
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 20,
                                  vertical: 12,
                                ),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(12),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }

  // ── Helpers pour le badge de mode ────────────────────────────────────────

  String get _modeLabel {
    switch (EnvConfig.currentMode) {
      case 'production':
        return 'PROD';
      case 'local':
        return 'LOCAL';
      case 'emulator':
        return 'EMU';
      default:
        return '...';
    }
  }

  Color get _modeColor {
    switch (EnvConfig.currentMode) {
      case 'production':
        return const Color(0xFF10B981);
      case 'local':
        return const Color(0xFF06B6D4);
      case 'emulator':
        return const Color(0xFF8B5CF6);
      default:
        return const Color(0xFF94A3B8);
    }
  }

  IconData get _modeIcon {
    switch (EnvConfig.currentMode) {
      case 'production':
        return Icons.cloud_done_rounded;
      case 'local':
        return Icons.wifi_rounded;
      case 'emulator':
        return Icons.phone_android_rounded;
      default:
        return Icons.help_outline_rounded;
    }
  }
}
