import 'dart:async';

import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../data/models/mission_model.dart';
import 'section_container.dart';

/// Carte de la contrepartie : côté artisan elle montre le client (avec appel
/// et itinéraire quand la mission est payée), côté client elle montre
/// l'artisan en charge.
class CounterpartyCard extends StatelessWidget {
  const CounterpartyCard({
    required this.role,
    required this.mission,
    super.key,
  });

  final String role;
  final MissionModel mission;

  @override
  Widget build(BuildContext context) {
    final isArtisan = role == 'artisan';
    final title = isArtisan ? 'Client de la mission' : 'Artisan en charge';
    final name = isArtisan
        ? (mission.clientName ?? 'Client')
        : (mission.artisanName ?? 'Artisan');
    final subtitle = isArtisan
        ? 'Ce client validera vos jalons via OTP.'
        : 'Le suivi terrain et les jalons sont portes par cet artisan.';

    final showActions = isArtisan && mission.isPaid;
    final displaySubtitle = showActions && mission.location != null
        ? 'Adresse : ${mission.location}'
        : subtitle;

    return SectionContainer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 52,
                height: 52,
                decoration: BoxDecoration(
                  color: AppColors.secondary,
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Icon(
                  isArtisan ? Icons.person_outline : Icons.handyman_outlined,
                  color: AppColors.primary,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        color: AppColors.textSecondary,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      name,
                      style: const TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w700,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      displaySubtitle,
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.textSecondary,
                        height: 1.3,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (showActions &&
              (mission.clientPhone != null ||
                  (mission.clientLatitude != null &&
                      mission.clientLongitude != null))) ...[
            const SizedBox(height: 12),
            const Divider(color: AppColors.border, height: 1),
            const SizedBox(height: 12),
            Row(
              children: [
                if (mission.clientPhone != null)
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: () {
                        unawaited(
                          launchUrl(
                            Uri.parse('tel:${mission.clientPhone}'),
                            mode: LaunchMode.externalApplication,
                          ),
                        );
                      },
                      style: OutlinedButton.styleFrom(
                        foregroundColor: AppColors.primary,
                        side: const BorderSide(color: AppColors.primary),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                        padding: const EdgeInsets.symmetric(vertical: 10),
                      ),
                      icon: const Icon(Icons.phone, size: 16),
                      label: Text(
                        mission.clientPhone!,
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ),
                if (mission.clientPhone != null &&
                    mission.clientLatitude != null &&
                    mission.clientLongitude != null)
                  const SizedBox(width: 8),
                if (mission.clientLatitude != null &&
                    mission.clientLongitude != null)
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: () {
                        final lat = mission.clientLatitude;
                        final lng = mission.clientLongitude;
                        unawaited(
                          launchUrl(
                            Uri.parse(
                              'https://www.google.com/maps/search/?api=1&query=$lat,$lng',
                            ),
                            mode: LaunchMode.externalApplication,
                          ),
                        );
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        foregroundColor: Colors.white,
                        elevation: 0,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                        padding: const EdgeInsets.symmetric(vertical: 10),
                      ),
                      icon: const Icon(Icons.map_outlined, size: 16),
                      label: const Text(
                        'Itinéraire Maps',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}
