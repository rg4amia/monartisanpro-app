import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config/env_config.dart';

/// `true` si [url] désigne un fichier servi par la plateforme : même domaine
/// que l'API, en `http(s)`.
///
/// Une adresse de média vient d'un autre utilisateur (photo d'une demande,
/// preuve d'une étape). L'ouvrir hors de l'application sans ce contrôle
/// envoyait la personne sur le site choisi par l'auteur.
bool isPlatformMediaUrl(String url, {String? apiBaseUrl}) {
  final uri = Uri.tryParse(url.trim());
  final api = Uri.tryParse(apiBaseUrl ?? EnvConfig.baseUrl);
  if (uri == null || api == null || api.host.isEmpty) return false;

  return (uri.scheme == 'https' || uri.scheme == 'http') &&
      uri.userInfo.isEmpty &&
      uri.host.toLowerCase() == api.host.toLowerCase();
}

/// Ouvre une vidéo de la plateforme dans le lecteur du téléphone. Une adresse
/// étrangère n'est pas ouverte, et l'utilisateur en est informé.
Future<void> openPlatformVideo(String url) async {
  if (!isPlatformMediaUrl(url)) {
    Get.snackbar(
      'Fichier non ouvert',
      "Ce fichier n'est pas hébergé par ProsArtisan.",
      snackPosition: SnackPosition.BOTTOM,
      backgroundColor: const Color(0xFFC55E50),
      colorText: Colors.white,
    );
    return;
  }

  await launchUrl(Uri.parse(url), mode: LaunchMode.externalApplication);
}
