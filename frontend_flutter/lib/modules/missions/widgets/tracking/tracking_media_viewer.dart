import 'dart:async';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';

/// `true` si l'URL pointe vers un fichier vidéo (extensions courantes).
bool isTrackingVideoUrl(String url) {
  final path = url.toLowerCase();
  return path.endsWith('.mp4') ||
      path.endsWith('.mov') ||
      path.endsWith('.avi') ||
      path.endsWith('.mkv') ||
      path.endsWith('.3gp') ||
      path.endsWith('.m4v');
}

/// Ouvre un média : lecteur externe pour la vidéo, visionneuse plein écran
/// zoomable pour l'image. Partagé par l'en-tête de mission et les cartes de
/// jalon.
void openTrackingMedia(BuildContext context, String url, bool isVideo) {
  if (isVideo) {
    unawaited(
      launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication),
    );
    return;
  }
  unawaited(
    Get.dialog(
      Dialog.fullscreen(
        backgroundColor: Colors.black,
        child: Stack(
          children: [
            InteractiveViewer(
              minScale: 0.5,
              maxScale: 4.0,
              child: Center(
                child: CachedNetworkImage(
                  imageUrl: url,
                  fit: BoxFit.contain,
                  placeholder: (context, url) => const CircularProgressIndicator(
                    valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                  ),
                  errorWidget: (context, url, error) => const Icon(
                    Icons.broken_image_outlined,
                    color: Colors.white70,
                    size: 48,
                  ),
                ),
              ),
            ),
            Positioned(
              top: 20,
              right: 20,
              child: SafeArea(
                child: IconButton(
                  icon: const CircleAvatar(
                    backgroundColor: Colors.black45,
                    child: Icon(Icons.close, color: Colors.white),
                  ),
                  onPressed: () => Get.back(),
                ),
              ),
            ),
          ],
        ),
      ),
    ),
  );
}
