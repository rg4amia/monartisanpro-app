import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../core/network/sync_service.dart';

/// Bandeau discret affiché en haut de l'app quand l'appareil est hors-ligne.
///
/// Se branche sur `SyncService.isOffline` (mis à jour par `connectivity_plus`).
/// À placer au-dessus du contenu principal dans un `Column`.
///
/// Il annonce aussi, jusqu'à ce que l'utilisateur l'ait lue, toute action
/// enregistrée hors connexion qui n'a finalement pas abouti
/// (`SyncService.failures`).
class OfflineBanner extends StatelessWidget {
  const OfflineBanner({super.key});

  @override
  Widget build(BuildContext context) {
    if (!Get.isRegistered<SyncService>()) return const SizedBox.shrink();
    final sync = Get.find<SyncService>();

    return Obx(() {
      final offline = sync.isOffline.value;
      final failures = sync.failures.toList();

      return AnimatedSize(
        duration: const Duration(milliseconds: 200),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            if (offline) const _OfflineStrip(),
            if (failures.isNotEmpty)
              _FailureStrip(
                failure: failures.first,
                others: failures.length - 1,
                onDismiss: () => sync.dismissFailure(failures.first.id),
              ),
          ],
        ),
      );
    });
  }
}

class _OfflineStrip extends StatelessWidget {
  const _OfflineStrip();

  @override
  Widget build(BuildContext context) {
    return Material(
      color: const Color(0xFF37474F),
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: const [
              Icon(Icons.cloud_off_rounded, size: 15, color: Colors.white),
              SizedBox(width: 8),
              Text(
                'Hors-ligne — vos actions seront synchronisées au retour du réseau',
                style: TextStyle(color: Colors.white, fontSize: 12),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _FailureStrip extends StatelessWidget {
  const _FailureStrip({
    required this.failure,
    required this.others,
    required this.onDismiss,
  });

  final SyncFailure failure;
  final int others;
  final VoidCallback onDismiss;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: const Color(0xFFB71C1C),
      child: SafeArea(
        bottom: false,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 8, 4, 8),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Padding(
                padding: EdgeInsets.only(top: 2),
                child: Icon(
                  Icons.error_outline_rounded,
                  size: 18,
                  color: Colors.white,
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      '${failure.label} : non aboutie',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 13,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '${failure.reason} Refaites cette action.',
                      style: const TextStyle(color: Colors.white, fontSize: 12),
                    ),
                    if (others > 0)
                      Padding(
                        padding: const EdgeInsets.only(top: 2),
                        child: Text(
                          others == 1
                              ? '1 autre action non aboutie.'
                              : '$others autres actions non abouties.',
                          style: const TextStyle(
                            color: Colors.white70,
                            fontSize: 12,
                          ),
                        ),
                      ),
                  ],
                ),
              ),
              TextButton(
                onPressed: onDismiss,
                style: TextButton.styleFrom(foregroundColor: Colors.white),
                child: const Text('Compris'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
