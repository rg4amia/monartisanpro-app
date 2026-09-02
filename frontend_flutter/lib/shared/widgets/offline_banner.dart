import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../core/network/sync_service.dart';

/// Bandeau discret affiché en haut de l'app quand l'appareil est hors-ligne.
///
/// Se branche sur `SyncService.isOffline` (mis à jour par `connectivity_plus`).
/// À placer au-dessus du contenu principal dans un `Column`.
class OfflineBanner extends StatelessWidget {
  const OfflineBanner({super.key});

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<SyncService>()) return const SizedBox.shrink();
    final sync = Get.find<SyncService>();

    return Obx(() {
      final offline = sync.isOffline.value;
      return AnimatedSize(
        duration: const Duration(milliseconds: 200),
        child: offline
            ? Material(
                color: const Color(0xFF37474F),
                child: SafeArea(
                  bottom: false,
                  child: Padding(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 6,
                    ),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: const [
                        Icon(Icons.cloud_off_rounded,
                            size: 15, color: Colors.white),
                        SizedBox(width: 8),
                        Text(
                          'Hors-ligne — vos actions seront synchronisées au retour du réseau',
                          style: TextStyle(color: Colors.white, fontSize: 12),
                        ),
                      ],
                    ),
                  ),
                ),
              )
            : const SizedBox.shrink(),
      );
    });
  }
}
