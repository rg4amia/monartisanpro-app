import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/formatters.dart';
import '../../../../data/models/mission_model.dart';
import 'status_pill.dart';
import 'tracking_media_viewer.dart';

/// En-tête de l'écran de suivi : statut, référence, description, métadonnées
/// (lieu / urgence / date) et galerie des visuels transmis par le client.
class MissionHeaderCard extends StatelessWidget {
  const MissionHeaderCard({required this.mission, super.key});

  final MissionModel mission;

  @override
  Widget build(BuildContext context) {
    final statusColor = _statusColor(mission.status);

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(22),
        boxShadow: [
          BoxShadow(
            color: AppColors.textPrimary.withValues(alpha: 0.04),
            blurRadius: 12,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              StatusPill(
                label: Formatters.missionStatus(mission.status),
                color: statusColor,
              ),
              const SizedBox(width: 8),
              if (mission.needsReferent)
                const StatusPill(
                  label: 'Referent obligatoire',
                  color: AppColors.accent,
                ),
              const Spacer(),
              Text(
                '#MS-${mission.id.toString().padLeft(5, '0')}',
                style: const TextStyle(
                  fontSize: 12,
                  color: AppColors.textSecondary,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            mission.description ?? 'Mission',
            style: const TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.w800,
              color: AppColors.textPrimary,
              height: 1.2,
            ),
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 12,
            runSpacing: 10,
            children: [
              _HeaderMeta(
                icon: Icons.place_outlined,
                text: mission.location ?? 'Adresse non renseignee',
              ),
              _HeaderMeta(
                icon: Icons.timer_outlined,
                text: 'Urgence ${mission.urgencyLabel.toLowerCase()}',
              ),
              _HeaderMeta(
                icon: Icons.calendar_today_outlined,
                text: Formatters.date(mission.createdAt),
              ),
            ],
          ),
          if (mission.photos.isNotEmpty) ...[
            const SizedBox(height: 18),
            const Divider(color: AppColors.border),
            const SizedBox(height: 12),
            const Text(
              'Visuels transmis par le client :',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                color: AppColors.textPrimary,
              ),
            ),
            const SizedBox(height: 10),
            SizedBox(
              height: 90,
              child: ListView.separated(
                scrollDirection: Axis.horizontal,
                itemCount: mission.photos.length,
                separatorBuilder: (context, index) => const SizedBox(width: 10),
                itemBuilder: (context, index) {
                  final mediaUrl = mission.photos[index];
                  final isVideo = isTrackingVideoUrl(mediaUrl);

                  return GestureDetector(
                    onTap: () => openTrackingMedia(context, mediaUrl, isVideo),
                    child: Container(
                      width: 90,
                      height: 90,
                      decoration: BoxDecoration(
                        color: Colors.black12,
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: AppColors.border),
                        boxShadow: [
                          BoxShadow(
                            color: Colors.black.withValues(alpha: 0.04),
                            blurRadius: 6,
                            offset: const Offset(0, 2),
                          ),
                        ],
                      ),
                      child: ClipRRect(
                        borderRadius: BorderRadius.circular(12),
                        child: Stack(
                          fit: StackFit.expand,
                          children: [
                            if (isVideo)
                              Container(
                                color: Colors.grey[800],
                                child: const Center(
                                  child: CircleAvatar(
                                    backgroundColor: Colors.white24,
                                    radius: 20,
                                    child: Icon(
                                      Icons.play_arrow_rounded,
                                      color: Colors.white,
                                      size: 24,
                                    ),
                                  ),
                                ),
                              )
                            else
                              CachedNetworkImage(
                                imageUrl: mediaUrl,
                                fit: BoxFit.cover,
                                placeholder: (context, url) => const Center(
                                  child: SizedBox(
                                    width: 20,
                                    height: 20,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  ),
                                ),
                                errorWidget: (context, url, error) =>
                                    const Center(
                                  child: Icon(
                                    Icons.broken_image_outlined,
                                    size: 24,
                                  ),
                                ),
                              ),
                          ],
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
          ],
        ],
      ),
    );
  }

  Color _statusColor(String status) {
    switch (status) {
      case 'en_attente':
        return AppColors.accent;
      case 'financee':
        return AppColors.primary;
      case 'en_cours':
        return AppColors.success;
      case 'litige':
        return AppColors.danger;
      default:
        return AppColors.textSecondary;
    }
  }
}

class _HeaderMeta extends StatelessWidget {
  const _HeaderMeta({
    required this.icon,
    required this.text,
  });

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 15, color: AppColors.textSecondary),
        const SizedBox(width: 6),
        Text(
          text,
          style: const TextStyle(
            fontSize: 12,
            color: AppColors.textSecondary,
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    );
  }
}
