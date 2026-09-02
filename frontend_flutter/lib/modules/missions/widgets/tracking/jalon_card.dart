import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/formatters.dart';
import '../../../../data/models/jalon_model.dart';
import '../../../../data/models/mission_model.dart';
import '../../controllers/missions_controller.dart';
import '../../views/jalon_submit_screen.dart';
import 'jalon_payment_dialog.dart';
import 'otp_validation_dialog.dart';
import 'status_pill.dart';
import 'tracking_media_viewer.dart';

/// Carte d'un jalon dans le suivi de mission : statut coloré, montant,
/// galerie de preuves, et actions contextuelles selon le rôle et l'état
/// (soumettre / renvoyer OTP côté artisan ; accepter preuves / financer /
/// valider OTP côté client).
class JalonCard extends StatelessWidget {
  const JalonCard({
    required this.role,
    required this.mission,
    required this.jalon,
    super.key,
  });

  final String role;
  final MissionModel mission;
  final JalonModel jalon;

  @override
  Widget build(BuildContext context) {
    final controller = Get.find<MissionsController>();
    final config = _statusConfig();

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: config.bg,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: config.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 16,
                backgroundColor: config.color.withValues(alpha: 0.12),
                child: Text(
                  '${jalon.ordre}',
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w800,
                    color: config.color,
                  ),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      jalon.description,
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w700,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      Formatters.fcfa(jalon.montant),
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.textSecondary,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              StatusPill(
                label: Formatters.jalonStatus(jalon.statut),
                color: config.color,
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            config.message,
            style: const TextStyle(
              fontSize: 12,
              color: AppColors.textSecondary,
              height: 1.35,
            ),
          ),
          if (jalon.photosJson != null && jalon.photosJson!.isNotEmpty) ...[
            const SizedBox(height: 14),
            _JalonProofGallery(photos: jalon.photosJson!),
          ],
          if (role == 'artisan') _artisanActions(controller),
          if (role == 'client' && (jalon.isSubmitted || jalon.isPending))
            _clientActions(context, controller),
        ],
      ),
    );
  }

  Widget _artisanActions(MissionsController controller) {
    if (jalon.isPending) {
      return Padding(
        padding: const EdgeInsets.only(top: 12),
        child: SizedBox(
          width: double.infinity,
          child: ElevatedButton.icon(
            onPressed: () => Get.to(
              () => const JalonSubmitScreen(),
              arguments: jalon,
            ),
            icon: const Icon(Icons.camera_alt_outlined, size: 18),
            label: const Text('Soumettre le jalon'),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.primary,
              foregroundColor: Colors.white,
            ),
          ),
        ),
      );
    }

    if (jalon.isSubmitted) {
      return Padding(
        padding: const EdgeInsets.only(top: 12),
        child: Row(
          children: [
            Expanded(
              child: OutlinedButton.icon(
                onPressed: () => Get.to(
                  () => const JalonSubmitScreen(),
                  arguments: jalon,
                ),
                icon: const Icon(Icons.add_a_photo_outlined, size: 16),
                label: const Text(
                  'Compléter preuves',
                  style: TextStyle(fontSize: 12),
                ),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: ElevatedButton.icon(
                onPressed: () async => controller.requestOtp(jalon.id),
                icon: const Icon(Icons.sms_outlined, size: 16),
                label: const Text(
                  'Renvoyer OTP',
                  style: TextStyle(fontSize: 12),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  foregroundColor: Colors.white,
                ),
              ),
            ),
          ],
        ),
      );
    }

    return const SizedBox.shrink();
  }

  Widget _clientActions(BuildContext context, MissionsController controller) {
    return Column(
      children: [
        const SizedBox(height: 12),
        Column(
          children: [
            if (jalon.isSubmitted) ...[
              SizedBox(
                width: double.infinity,
                child: ElevatedButton.icon(
                  onPressed: () => _confirmAcceptProofs(controller),
                  icon: const Icon(Icons.verified_outlined, size: 18),
                  label: const Text('Accepter les preuves'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.success,
                    foregroundColor: Colors.white,
                  ),
                ),
              ),
              const SizedBox(height: 8),
            ],
            Row(
              children: [
                if (mission.paymentType == 'hybrid') ...[
                  Expanded(
                    child: ElevatedButton.icon(
                      onPressed: () => showJalonPaymentDialog(context, jalon),
                      icon: const Icon(Icons.payment_rounded, size: 18),
                      label: const Text('Financer'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        foregroundColor: Colors.white,
                      ),
                    ),
                  ),
                  const SizedBox(width: 8),
                ],
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () => showOtpValidationDialog(context, jalon),
                    icon: const Icon(Icons.sms_outlined, size: 18),
                    label: const Text('Valider via OTP'),
                    style: OutlinedButton.styleFrom(
                      side: const BorderSide(color: AppColors.success),
                      foregroundColor: AppColors.success,
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
        if (jalon.isSubmitted) ...[
          const SizedBox(height: 8),
          SizedBox(
            width: double.infinity,
            child: TextButton.icon(
              onPressed: () async => controller.requestOtp(jalon.id),
              icon: const Icon(
                Icons.sms_outlined,
                size: 16,
                color: AppColors.primary,
              ),
              label: const Text(
                'Renvoyer le code de validation (SMS)',
                style: TextStyle(
                  color: AppColors.primary,
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ),
        ],
      ],
    );
  }

  Future<void> _confirmAcceptProofs(MissionsController controller) async {
    final confirm = await Get.dialog<bool>(
      AlertDialog(
        title: const Text('Accepter les preuves ?'),
        content: const Text(
          'En acceptant ces preuves, vous validez la réalisation de ce jalon et débloquez le paiement pour l\'artisan.',
        ),
        actions: [
          TextButton(
            onPressed: () => Get.back(result: false),
            child: const Text('Annuler'),
          ),
          ElevatedButton(
            onPressed: () => Get.back(result: true),
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.success,
            ),
            child: const Text('Accepter'),
          ),
        ],
      ),
    );
    if (confirm == true) {
      await controller.acceptJalonProofs(jalon.id);
    }
  }

  _JalonStatusConfig _statusConfig() {
    if (jalon.isPaid) {
      return const _JalonStatusConfig(
        color: AppColors.success,
        bg: AppColors.supplierSoft,
        border: Color(0xFFB8E7CC),
        message: 'Paiement libere sur le wallet main d\'oeuvre.',
      );
    }

    if (jalon.isValidated) {
      if (mission.needsReferent) {
        return const _JalonStatusConfig(
          color: AppColors.accent,
          bg: AppColors.artisanSoft,
          border: Color(0xFFF6D68A),
          message:
              'OTP valide. La liberation attend la validation physique du referent.',
        );
      }

      return const _JalonStatusConfig(
        color: AppColors.success,
        bg: AppColors.supplierSoft,
        border: Color(0xFFB8E7CC),
        message: 'OTP valide. Le paiement est en cours de liberation.',
      );
    }

    if (jalon.isSubmitted) {
      return const _JalonStatusConfig(
        color: AppColors.primary,
        bg: AppColors.secondary,
        border: Color(0xFFC7D2FE),
        message:
            'Preuves envoyees. Le client doit maintenant confirmer l\'OTP recu par SMS.',
      );
    }

    return const _JalonStatusConfig(
      color: AppColors.accent,
      bg: Color(0xFFFFFBEB),
      border: Color(0xFFFDE68A),
      message:
          'Ce jalon doit etre documente avec des photos geolocalisees avant soumission.',
    );
  }
}

class _JalonProofGallery extends StatelessWidget {
  const _JalonProofGallery({required this.photos});

  final List<Map<String, dynamic>> photos;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Preuves de réalisation :',
          style: TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w700,
            color: AppColors.textPrimary,
          ),
        ),
        const SizedBox(height: 8),
        SizedBox(
          height: 70,
          child: ListView.separated(
            scrollDirection: Axis.horizontal,
            itemCount: photos.length,
            separatorBuilder: (context, index) => const SizedBox(width: 8),
            itemBuilder: (context, index) {
              final url = photos[index]['url'] as String? ?? '';
              final isVideo = isTrackingVideoUrl(url);
              return GestureDetector(
                onTap: () => openTrackingMedia(context, url, isVideo),
                child: Container(
                  width: 70,
                  height: 70,
                  decoration: BoxDecoration(
                    color: Colors.black12,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: AppColors.border),
                  ),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(10),
                    child: Stack(
                      fit: StackFit.expand,
                      children: [
                        if (isVideo)
                          Container(
                            color: Colors.grey[800],
                            child: const Center(
                              child: Icon(
                                Icons.play_arrow_rounded,
                                color: Colors.white,
                                size: 24,
                              ),
                            ),
                          )
                        else
                          CachedNetworkImage(
                            imageUrl: url,
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
                            errorWidget: (context, url, error) => const Center(
                              child: Icon(
                                Icons.broken_image_outlined,
                                size: 20,
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
    );
  }
}

class _JalonStatusConfig {
  const _JalonStatusConfig({
    required this.color,
    required this.bg,
    required this.border,
    required this.message,
  });

  final Color color;
  final Color bg;
  final Color border;
  final String message;
}
