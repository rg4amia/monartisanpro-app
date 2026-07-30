import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

import 'package:image_picker/image_picker.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:frontend_flutter/core/config/env_config.dart';

class IaAssistantScreen extends StatefulWidget {
  const IaAssistantScreen({super.key});

  @override
  State<IaAssistantScreen> createState() => _IaAssistantScreenState();
}

class _IaAssistantScreenState extends State<IaAssistantScreen> {
  late final WebViewController _controller;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _requestPermissions();
    _initWebView();
  }

  Future<void> _requestPermissions() async {
    await [
      Permission.camera,
      Permission.storage,
      Permission.photos,
    ].request();
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
                title: const Text("Prendre une photo (Appareil photo)"),
                onTap: () => Navigator.pop(context, ImageSource.camera),
              ),
              ListTile(
                leading: const Icon(Icons.photo_library, color: Color(0xFF8B5CF6)),
                title: const Text("Choisir depuis la galerie"),
                onTap: () => Navigator.pop(context, ImageSource.gallery),
              ),
            ],
          ),
        );
      },
    );

    if (source == null) return;

    final XFile? image = await picker.pickImage(
      source: source,
      maxWidth: 1024,
      maxHeight: 1024,
      imageQuality: 80,
    );

    if (image == null) return;

    try {
      final bytes = await image.readAsBytes();
      final base64Image = base64Encode(bytes);
      final filename = image.name;
      final fileSize = bytes.length;

      await _controller.runJavaScript(
        "window.handleFlutterImage('$base64Image', '$filename', $fileSize);"
      );
    } catch (e) {
      debugPrint("Error reading/sending image: $e");
    }
  }

  void _initWebView() {
    final uri = Uri.parse(EnvConfig.baseUrl);
    final host = uri.host;
    final assistantUrl = uri.hasPort
        ? '${uri.scheme}://$host:${uri.port}/client.html'
        : '${uri.scheme}://$host/client.html';

    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(const Color(0xFFF8FAFC))
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (String url) {
            setState(() {
              _isLoading = true;
            });
          },
          onPageFinished: (String url) {
            setState(() {
              _isLoading = false;
            });
          },
          onWebResourceError: (WebResourceError error) {
            debugPrint("WebView error: ${error.description}");
          },
        ),
      )
      ..addJavaScriptChannel(
        'FlutterNotificationChannel',
        onMessageReceived: (JavaScriptMessage message) {
          try {
            final data = jsonDecode(message.message);
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
            debugPrint("Error parsing channel message: $e");
          }
        },
      )
      ..loadRequest(Uri.parse(assistantUrl));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      appBar: AppBar(
        title: const Text(
          "Assistant IA Chantier",
          style: TextStyle(color: Color(0xFF0F172A), fontWeight: FontWeight.bold),
        ),
        backgroundColor: const Color(0xFFFFFFFF),
        elevation: 0,
        iconTheme: const IconThemeData(color: Color(0xFF0F172A)),
      ),
      body: SafeArea(
        child: Stack(
          children: [
            WebViewWidget(controller: _controller),
            if (_isLoading)
              const Center(
                child: CircularProgressIndicator(
                  color: Color(0xFF06B6D4), // Cyan neon color
                ),
              ),
          ],
        ),
      ),
    );
  }
}
