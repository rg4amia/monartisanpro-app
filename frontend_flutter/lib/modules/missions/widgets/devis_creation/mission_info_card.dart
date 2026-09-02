import 'dart:async';

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../data/models/mission_model.dart';

/// En-tête rappelant la mission à chiffrer : description, client, lieu,
/// méta-badges et galerie des visuels transmis par le client.
class MissionInfoCard extends StatelessWidget {
  const MissionInfoCard({
    this.missionId,
    this.mission,
    super.key,
  });

  final int? missionId;
  final MissionModel? mission;

  @override
  Widget build(BuildContext context) {
    final missionDescription = mission?.description?.trim();
    final location = mission?.location?.trim();
    final clientName = mission?.clientName?.trim();

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.secondary,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.primary.withValues(alpha: 0.2)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              const Icon(
                Icons.assignment_outlined,
                color: AppColors.primary,
                size: 24,
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Mission #${missionId ?? 'N/A'}',
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                        color: AppColors.primary,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      missionDescription?.isNotEmpty == true
                          ? missionDescription!
                          : 'Détaillez les lignes de main d\'œuvre et matériaux, puis définissez les jalons de paiement.',
                      style: const TextStyle(
                        fontSize: 13,
                        color: AppColors.textSecondary,
                        height: 1.35,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (clientName != null && clientName.isNotEmpty) ...[
            const SizedBox(height: 14),
            _InfoLine(
              icon: Icons.person_outline,
              text: 'Client: $clientName',
            ),
          ],
          if (location != null && location.isNotEmpty) ...[
            const SizedBox(height: 8),
            _InfoLine(
              icon: Icons.place_outlined,
              text: location,
            ),
          ],
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              if (mission?.category?.trim().isNotEmpty == true)
                _MetaPill(
                  icon: Icons.category_outlined,
                  label: mission!.category!.trim(),
                ),
              if (mission?.urgency?.trim().isNotEmpty == true)
                _MetaPill(
                  icon: Icons.flash_on_outlined,
                  label: 'Urgence ${mission!.urgencyLabel.toLowerCase()}',
                ),
              if (mission?.needsReferent == true)
                const _MetaPill(
                  icon: Icons.verified_user_outlined,
                  label: 'Référent requis',
                  color: AppColors.artisanSoft,
                  textColor: AppColors.accent,
                ),
            ],
          ),
          if (mission?.photos != null && mission!.photos.isNotEmpty) ...[
            const SizedBox(height: 16),
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
                itemCount: mission!.photos.length,
                separatorBuilder: (context, index) => const SizedBox(width: 10),
                itemBuilder: (context, index) {
                  final mediaUrl = mission!.photos[index];
                  final isVideo = _isVideoUrl(mediaUrl);

                  return GestureDetector(
                    onTap: () => _openMedia(context, mediaUrl, isVideo),
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

  bool _isVideoUrl(String url) {
    final path = url.toLowerCase();
    return path.endsWith('.mp4') ||
        path.endsWith('.mov') ||
        path.endsWith('.avi') ||
        path.endsWith('.mkv') ||
        path.endsWith('.3gp') ||
        path.endsWith('.m4v');
  }

  void _openMedia(BuildContext context, String url, bool isVideo) {
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
                    placeholder: (context, url) =>
                        const CircularProgressIndicator(
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
}

class _InfoLine extends StatelessWidget {
  const _InfoLine({
    required this.icon,
    required this.text,
  });

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Icon(icon, size: 16, color: AppColors.primary),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(
              fontSize: 12,
              color: AppColors.textSecondary,
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ],
    );
  }
}

class _MetaPill extends StatelessWidget {
  const _MetaPill({
    required this.icon,
    required this.label,
    this.color = Colors.white,
    this.textColor = AppColors.textPrimary,
  });

  final IconData icon;
  final String label;
  final Color color;
  final Color textColor;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: color,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: textColor),
          const SizedBox(width: 6),
          Text(
            label,
            style: TextStyle(
              fontSize: 12,
              color: textColor,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}
