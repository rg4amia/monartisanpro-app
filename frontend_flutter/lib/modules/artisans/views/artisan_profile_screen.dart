import 'package:cached_network_image/cached_network_image.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/artisan_model.dart';
import '../../../shared/widgets/score_prosartisan.dart';
import '../../missions/controllers/artisan_selection_controller.dart';
import '../../missions/controllers/missions_controller.dart';
import '../controllers/artisan_controller.dart';

class ArtisanProfileScreen extends StatelessWidget {
  const ArtisanProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final c = Get.find<ArtisanController>();

    return Scaffold(
      backgroundColor: AppColors.background,
      body: Obx(() {
        if (c.isLoading.value || c.artisan.value == null) {
          return const Center(child: CircularProgressIndicator());
        }
        final a = c.artisan.value!;
        final scoreData = c.score.value;
        final rootData = (scoreData?['data'] as Map<String, dynamic>?) ?? scoreData;
        final dynamicScore = (rootData?['score_prosartisan'] as num?)?.toInt() ?? a.scoreProsArtisan;

        return CustomScrollView(
          slivers: [
            // Photo header
            SliverAppBar(
              expandedHeight: 220,
              pinned: true,
              backgroundColor: AppColors.primary,
              flexibleSpace: FlexibleSpaceBar(
                background: a.photo != null
                    ? CachedNetworkImage(
                        imageUrl: a.photo!,
                        fit: BoxFit.cover,
                        placeholder: (_, __) => Container(
                            color: AppColors.primary.withValues(alpha: 0.3)),
                        errorWidget: (_, __, ___) =>
                            Container(color: AppColors.primary),
                      )
                    : Container(
                        color: AppColors.accent.withValues(alpha: 0.15),
                        child: Center(
                          child: Text(
                            Formatters.initial(a.name ?? 'A'),
                            style: const TextStyle(
                                fontSize: 72,
                                fontWeight: FontWeight.w800,
                                color: AppColors.accent),
                          ),
                        ),
                      ),
              ),
            ),

            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Name + score
                    Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Wrap(
                                crossAxisAlignment: WrapCrossAlignment.center,
                                spacing: 8,
                                runSpacing: 6,
                                children: [
                                  Text(a.name ?? 'Artisan',
                                      style: const TextStyle(
                                          fontSize: 22,
                                          fontWeight: FontWeight.w800,
                                          color: AppColors.textPrimary)),
                                  if (a.isGoldenMarker)
                                    Container(
                                      padding: const EdgeInsets.symmetric(
                                          horizontal: 8, vertical: 3),
                                      decoration: BoxDecoration(
                                        color: const Color(0xFFFFF3CD),
                                        borderRadius: BorderRadius.circular(8),
                                      ),
                                      child: const Row(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          Icon(Icons.star,
                                              size: 12,
                                              color: Color(0xFFD4A017)),
                                          SizedBox(width: 3),
                                          Text('Artisan d\'élite',
                                              style: TextStyle(
                                                  fontSize: 11,
                                                  color: Color(0xFFD4A017),
                                                  fontWeight: FontWeight.w600)),
                                        ],
                                      ),
                                    ),
                                  if (a.isCnmciVerified)
                                    Container(
                                      padding: const EdgeInsets.symmetric(
                                          horizontal: 8, vertical: 3),
                                      decoration: BoxDecoration(
                                        color: const Color(0xFFD1FAE5),
                                        borderRadius: BorderRadius.circular(8),
                                      ),
                                      child: const Row(
                                        mainAxisSize: MainAxisSize.min,
                                        children: [
                                          Icon(Icons.verified,
                                              size: 12,
                                              color: Color(0xFF059669)),
                                          SizedBox(width: 3),
                                          Text('Certifié CNMCI',
                                              style: TextStyle(
                                                  fontSize: 11,
                                                  color: Color(0xFF059669),
                                                  fontWeight: FontWeight.w600)),
                                        ],
                                      ),
                                    ),
                                ],
                              ),
                              if (a.trade != null)
                                Text(a.trade!,
                                    style: const TextStyle(
                                        fontSize: 15,
                                        color: AppColors.textSecondary)),
                              if (a.nightInterventionAvailable) ...[
                                const SizedBox(height: 10),
                                Container(
                                  padding: const EdgeInsets.symmetric(
                                    horizontal: 10,
                                    vertical: 6,
                                  ),
                                  decoration: BoxDecoration(
                                    color: AppColors.primary.withValues(alpha: 0.08),
                                    borderRadius: BorderRadius.circular(10),
                                  ),
                                  child: const Row(
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      Icon(
                                        Icons.nightlight_round,
                                        size: 14,
                                        color: AppColors.primary,
                                      ),
                                      SizedBox(width: 6),
                                      Text(
                                        'Intervention de nuit',
                                        style: TextStyle(
                                          fontSize: 12,
                                          fontWeight: FontWeight.w700,
                                          color: AppColors.primary,
                                        ),
                                      ),
                                    ],
                                  ),
                                ),
                              ],
                              if (a.distance != null)
                                Row(
                                  children: [
                                    const Icon(Icons.location_on_outlined,
                                        size: 14, color: AppColors.textMuted),
                                    Text(a.distance!,
                                        style: const TextStyle(
                                            fontSize: 13,
                                            color: AppColors.textMuted)),
                                  ],
                                ),
                            ],
                          ),
                        ),
                        ScoreProsArtisan(
                            score: dynamicScore,
                            size: ScoreSize.large,
                            showLabel: true),
                      ],
                    ),
                    const SizedBox(height: 20),

                    // Actions - Bouton Demander devis (Sans option Appeler)
                    // Actions - Bouton Demander devis / Choisir l'artisan
                    SizedBox(
                      width: double.infinity,
                      child: c.fromSelection.value
                          ? ElevatedButton.icon(
                              onPressed: () {
                                if (Get.isRegistered<ArtisanSelectionController>()) {
                                  Get.find<ArtisanSelectionController>().selectArtisan(a);
                                } else {
                                  Get.snackbar(
                                    'Erreur',
                                    'Impossible de sélectionner cet artisan',
                                    snackPosition: SnackPosition.TOP,
                                  );
                                }
                              },
                              style: ElevatedButton.styleFrom(
                                padding: const EdgeInsets.symmetric(vertical: 14),
                                backgroundColor: AppColors.primary,
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(12),
                                ),
                              ),
                              icon: const Icon(Icons.check_circle_outline, color: Colors.white),
                              label: const Text(
                                'Choisir cet artisan',
                                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                              ),
                            )
                          : ElevatedButton.icon(
                              onPressed: () => _showQuoteRequestModal(context, a),
                              style: ElevatedButton.styleFrom(
                                padding: const EdgeInsets.symmetric(vertical: 14),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(12),
                                ),
                              ),
                              icon: const Icon(Icons.send_outlined, color: Colors.white),
                              label: const Text(
                                'Demander un devis',
                                style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                              ),
                            ),
                    ),
                    const SizedBox(height: 20),

                    // Score breakdown
                    _buildScoreBreakdown(c),
                  ],
                ),
              ),
            ),
          ],
        );
      }),
    );
  }

  Widget _buildScoreBreakdown(ArtisanController c) {
    final scoreData = c.score.value;
    if (scoreData == null) return const SizedBox.shrink();
    final rootData =
        (scoreData['data'] as Map<String, dynamic>?) ?? scoreData;
    final breakdown = rootData['breakdown'] is Map
        ? Map<String, dynamic>.from(
            rootData['breakdown'] as Map,
          )
        : const <String, dynamic>{};
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text('Score ProsArtisan',
            style: TextStyle(
                fontSize: 17,
                fontWeight: FontWeight.w700,
                color: AppColors.textPrimary)),
        const SizedBox(height: 12),
        _ScoreDimension(
            label: 'Fiabilité',
            pct: 40,
            value: _weightedBreakdownValue(
              breakdown['fiabilite'],
              40,
            ),
            color: AppColors.primary),
        _ScoreDimension(
            label: 'Intégrité',
            pct: 30,
            value: _weightedBreakdownValue(
              breakdown['integrite'],
              30,
            ),
            color: AppColors.accent),
        _ScoreDimension(
            label: 'Qualité',
            pct: 20,
            value: _weightedBreakdownValue(
              breakdown['qualite'],
              20,
            ),
            color: AppColors.success),
        _ScoreDimension(
            label: 'Réactivité',
            pct: 10,
            value: _weightedBreakdownValue(
              breakdown['reactivite'],
              10,
            ),
            color: AppColors.warning),
      ],
    );
  }

  int _weightedBreakdownValue(dynamic value, int maxPoints) {
    double parsed;

    if (value is num) {
      parsed = value.toDouble();
    } else {
      parsed = double.tryParse(value?.toString() ?? '') ?? 0;
    }

    return (parsed / 5 * maxPoints).round().clamp(0, maxPoints);
  }
}

class _ScoreDimension extends StatelessWidget {
  final String label;
  final int pct;
  final int value;
  final Color color;

  const _ScoreDimension({
    required this.label,
    required this.pct,
    required this.value,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(label,
                  style: const TextStyle(
                      fontSize: 13, color: AppColors.textSecondary)),
              Text('$value / $pct pts',
                  style: TextStyle(
                      fontSize: 13, fontWeight: FontWeight.w600, color: color)),
            ],
          ),
          const SizedBox(height: 4),
          ClipRRect(
            borderRadius: BorderRadius.circular(4),
            child: LinearProgressIndicator(
              value: pct > 0 ? value / pct : 0,
              backgroundColor: color.withValues(alpha: 0.12),
              valueColor: AlwaysStoppedAnimation(color),
              minHeight: 6,
            ),
          ),
        ],
      ),
    );
  }
}

void _showQuoteRequestModal(BuildContext context, ArtisanModel a) {
  final descCtrl = TextEditingController();
  final urgency = 'moyen'.obs;
  final photos = <XFile>[].obs;
  final video = Rx<XFile?>(null);

  Future<void> pickImage() async {
    if (photos.length >= 5) {
      Get.snackbar('Limite atteinte', 'Maximum 5 photos autorisées');
      return;
    }
    final picker = ImagePicker();
    final image = await picker.pickImage(source: ImageSource.gallery);
    if (image != null) {
      photos.add(image);
    }
  }

  Future<void> pickVideo() async {
    final picker = ImagePicker();
    final vid = await picker.pickVideo(source: ImageSource.gallery);
    if (vid != null) {
      video.value = vid;
    }
  }

  showModalBottomSheet(
    context: context,
    isScrollControlled: true,
    backgroundColor: Colors.transparent,
    builder: (context) {
      return Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.of(context).viewInsets.bottom,
        ),
        child: Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
          ),
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Expanded(
                    child: Text(
                      'Demande de devis - ${a.name}',
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                        color: AppColors.textPrimary,
                      ),
                    ),
                  ),
                  IconButton(
                    onPressed: () => Get.back(),
                    icon: const Icon(Icons.close_rounded),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              Text(
                'Décrivez vos travaux pour que ${a.name} puisse vous établir un devis personnalisé.',
                style: const TextStyle(fontSize: 13, color: AppColors.textSecondary),
              ),
              const SizedBox(height: 16),

              // Description Textfield
              TextField(
                controller: descCtrl,
                maxLines: 4,
                decoration: InputDecoration(
                  hintText: 'Décrivez précisément votre besoin (min. 20 caractères)...\nEx: Remplacement fuite sous évier cuisine et pose nouveau siphon.',
                  hintStyle: const TextStyle(fontSize: 13, color: AppColors.textMuted),
                  filled: true,
                  fillColor: AppColors.background,
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(12),
                    borderSide: const BorderSide(color: AppColors.border),
                  ),
                ),
              ),
              const SizedBox(height: 16),

              // Urgency Selection
              const Text(
                'Niveau d\'urgence',
                style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.textPrimary),
              ),
              const SizedBox(height: 8),
              Obx(() => SegmentedButton<String>(
                segments: const [
                  ButtonSegment(value: 'faible', label: Text('Normal')),
                  ButtonSegment(value: 'moyen', label: Text('Moyen')),
                  ButtonSegment(value: 'urgent', label: Text('Urgent')),
                ],
                selected: {urgency.value},
                onSelectionChanged: (set) => urgency.value = set.first,
              )),
              const SizedBox(height: 16),

              const Text(
                'Visuels (Photos ou Vidéos)',
                style: TextStyle(fontSize: 13, fontWeight: FontWeight.w600, color: AppColors.textPrimary),
              ),
              const SizedBox(height: 8),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: pickImage,
                      icon: const Icon(Icons.add_photo_alternate_outlined),
                      label: const Text('Photo'),
                      style: OutlinedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: OutlinedButton.icon(
                      onPressed: pickVideo,
                      icon: const Icon(Icons.videocam_outlined),
                      label: const Text('Vidéo'),
                      style: OutlinedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 12),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 8),
              const Text(
                'Max 5 photos et 1 vidéo (max 30s)',
                style: TextStyle(fontSize: 11, color: AppColors.textSecondary),
              ),
              Obx(() {
                if (photos.isEmpty && video.value == null) {
                  return const SizedBox.shrink();
                }
                return Padding(
                  padding: const EdgeInsets.only(top: 12, bottom: 8),
                  child: Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      ...photos.map(
                        (photo) => _MediaThumbnailLocal(
                          file: photo,
                          onRemove: () => photos.remove(photo),
                        ),
                      ),
                      if (video.value != null)
                        _MediaThumbnailLocal(
                          file: video.value!,
                          isVideo: true,
                          onRemove: () => video.value = null,
                        ),
                    ],
                  ),
                );
              }),
              const SizedBox(height: 24),

              // Submit Button
              ElevatedButton.icon(
                onPressed: () async {
                  final text = descCtrl.text.trim();
                  if (text.length < 20) {
                    Get.snackbar(
                      'Description insuffisante',
                      'Veuillez saisir au moins 20 caractères pour décrire vos travaux.',
                      snackPosition: SnackPosition.TOP,
                      backgroundColor: AppColors.warning,
                      colorText: Colors.white,
                    );
                    return;
                  }

                  // 1. Show loading progress indicator
                  Get.dialog(
                    const Center(
                      child: CircularProgressIndicator(),
                    ),
                    barrierDismissible: false,
                  );

                  try {
                    final missionsController = Get.isRegistered<MissionsController>()
                        ? Get.find<MissionsController>()
                        : Get.put(MissionsController());

                    // 2. Upload photos and videos first
                    final List<String> uploadedUrls = [];
                    for (final photo in photos) {
                      final url = await missionsController.uploadFile(photo.path);
                      uploadedUrls.add(url);
                    }
                    if (video.value != null) {
                      final url = await missionsController.uploadFile(video.value!.path);
                      uploadedUrls.add(url);
                    }

                    // 3. Create the mission request
                    final mission = await missionsController.createMission(
                      artisanId: a.id,
                      description: text,
                      category: a.trade ?? 'Travaux généraux',
                      urgency: urgency.value,
                      photos: uploadedUrls.isNotEmpty ? uploadedUrls : null,
                    );

                    Get.back(); // Close loading dialog
                    Get.back(); // Close bottom sheet modal

                    if (mission != null) {
                      Get.toNamed(
                        Routes.missionTracking,
                        arguments: mission,
                      );
                    }
                  } catch (e) {
                    Get.back(); // Close loading dialog
                    
                    String errorMsg = 'Une erreur est survenue lors du téléversement ou de la création';
                    if (e is DioException) {
                      errorMsg = e.response?.data['message'] ?? e.message ?? errorMsg;
                    } else if (e.toString().contains('Fichier rejeté')) {
                      errorMsg = e.toString();
                    }
                    
                    Get.snackbar(
                      'Erreur',
                      errorMsg,
                      snackPosition: SnackPosition.TOP,
                      backgroundColor: Colors.red,
                      colorText: Colors.white,
                      duration: const Duration(seconds: 6),
                    );
                  }
                },
                style: ElevatedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  backgroundColor: AppColors.client,
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                ),
                icon: const Icon(Icons.send_outlined, color: Colors.white),
                label: const Text('ENVOYER LA DEMANDE DE DEVIS', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 14)),
              ),
            ],
          ),
        ),
      );
    },
  );
}

class _MediaThumbnailLocal extends StatelessWidget {
  final XFile file;
  final bool isVideo;
  final VoidCallback onRemove;

  const _MediaThumbnailLocal({
    required this.file,
    this.isVideo = false,
    required this.onRemove,
  });

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        Container(
          width: 60,
          height: 60,
          decoration: BoxDecoration(
            color: Colors.grey[200],
            borderRadius: BorderRadius.circular(8),
            border: Border.all(color: AppColors.border),
          ),
          child: Center(
            child: Icon(
              isVideo ? Icons.videocam : Icons.image,
              color: AppColors.textSecondary,
            ),
          ),
        ),
        Positioned(
          top: 2,
          right: 2,
          child: GestureDetector(
            onTap: onRemove,
            child: Container(
              width: 18,
              height: 18,
              decoration: const BoxDecoration(
                color: Colors.red,
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.close, size: 12, color: Colors.white),
            ),
          ),
        ),
      ],
    );
  }
}
