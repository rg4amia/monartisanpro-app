import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../core/network/sync_service.dart';

/// Bandeau non-bloquant affiché au-dessus d'une carte Yandex quand l'appareil
/// est hors-ligne.
///
/// Yandex MapKit a besoin du réseau pour télécharger les tuiles : hors-ligne,
/// seules les zones déjà consultées (en cache) s'affichent, et la recherche
/// d'adresse / le calcul d'itinéraire échouent. Ce widget prévient l'utilisateur
/// sans masquer la carte (les tuiles en cache restent utiles).
///
/// À poser dans le `Stack` d'un écran carte, typiquement :
/// `Positioned(top: safeTop, left: 12, right: 12, child: MapOfflineNotice())`.
class MapOfflineNotice extends StatelessWidget {
  const MapOfflineNotice({super.key, this.margin});

  final EdgeInsetsGeometry? margin;

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<SyncService>()) return const SizedBox.shrink();
    final sync = Get.find<SyncService>();

    return Obx(() {
      if (!sync.isOffline.value) return const SizedBox.shrink();

      return Container(
        margin: margin ?? const EdgeInsets.all(12),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
        decoration: BoxDecoration(
          color: const Color(0xFF37474F),
          borderRadius: BorderRadius.circular(12),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withValues(alpha: 0.18),
              blurRadius: 12,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: const Row(
          children: [
            Icon(Icons.cloud_off_rounded, size: 16, color: Colors.white),
            SizedBox(width: 10),
            Expanded(
              child: Text(
                'Carte indisponible hors-ligne. Seules les zones déjà '
                'consultées s\'affichent ; la recherche d\'adresse est désactivée.',
                style: TextStyle(color: Colors.white, fontSize: 11.5, height: 1.3),
              ),
            ),
          ],
        ),
      );
    });
  }
}
