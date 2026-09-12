import 'dart:async';

import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../../shared/widgets/image_viewer.dart';

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
  openImageViewer(context, urls: [url]);
}
