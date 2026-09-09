import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/storage/storage_service.dart';
import '../../../core/utils/bypass_validator.dart';
import '../../services/models/sector_model.dart';
import '../../services/models/trade_model.dart';
import '../../services/utils/service_icon_helper.dart';
import '../controllers/missions_controller.dart';

// ─── Design Tokens ────────────────────────────────────────────────────────────
abstract class _C {
  static const bg = Color(0xFFF8F9FA);
  static const surface = Colors.white;
  static const primary = Color(0xFF4F46E5);
  static const primaryLight = Color(0xFFEEF2FF);
  static const ink = Color(0xFF111827);
  static const muted = Color(0xFF6B7280);
  static const subtle = Color(0xFFE5E7EB);
}

class MissionRequestScreen extends StatefulWidget {
  const MissionRequestScreen({super.key});

  @override
  State<MissionRequestScreen> createState() => _MissionRequestScreenState();
}

class _MissionRequestScreenState extends State<MissionRequestScreen> {
  final MissionsController _missionsController = Get.find<MissionsController>();
  final _descCtrl = TextEditingController();
  final _selectedCategory = ''.obs;
  final _selectedCategoryId = 0.obs;
  final _selectedTradeId = 0.obs;
  final _location = 'Abidjan, Côte d\'Ivoire'.obs;
  final _locationDetail = 'Cocody, Riviera 3'.obs;
  final _latitude = 0.0.obs;
  final _longitude = 0.0.obs;
  final _nightIntervention = false.obs;
  final _photos = <XFile>[].obs;
  final _video = Rx<XFile?>(null);

  @override
  void initState() {
    super.initState();

    // RÈGLE CRITIQUE : Vérifier KYC avant création mission
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final kycStatus = StorageService.getKycStatus();
      if (kycStatus != 'actif') {
        Get.snackbar(
          'KYC requis',
          'Veuillez compléter votre vérification d\'identité avant de créer une mission',
          backgroundColor: Colors.red,
          colorText: Colors.white,
          duration: const Duration(seconds: 5),
          snackPosition: SnackPosition.BOTTOM,
        );
        // On redirige vers le profil ou l'onboarding KYC si nécessaire
        // Pour l'instant, on retourne en arrière pour bloquer l'accès
        Get.back();
      }
    });

    final args = Get.arguments as Map<String, dynamic>?;
    if (args != null && args['category'] != null) {
      _selectedCategory.value = args['category'] as String;
    }
  }

  @override
  void dispose() {
    _descCtrl.dispose();
    super.dispose();
  }

  Future<void> _pickImage() async {
    if (_photos.length >= 5) {
      Get.snackbar('Limite atteinte', 'Maximum 5 photos autorisées');
      return;
    }
    final picker = ImagePicker();
    final image = await picker.pickImage(source: ImageSource.gallery);
    if (image != null) {
      _photos.add(image);
    }
  }

  Future<void> _pickVideo() async {
    final picker = ImagePicker();
    final video = await picker.pickVideo(source: ImageSource.gallery);
    if (video != null) {
      _video.value = video;
    }
  }

  Future<void> _runGeminiEstimate() async {
    final desc = _descCtrl.text.trim();
    if (desc.length < 10) {
      Get.snackbar(
        'Description requise',
        'Veuillez d\'abord décrire votre problème (au moins 10 caractères) pour lancer l\'analyse IA.',
        snackPosition: SnackPosition.TOP,
        backgroundColor: Colors.amber.shade800,
        colorText: Colors.white,
      );
      return;
    }

    final result = await _missionsController.estimate(
      desc,
      _selectedCategory.value.isNotEmpty ? _selectedCategory.value : null,
    );

    if (result != null) {
      final detectedCategory = result['category']?.toString();
      if (detectedCategory != null &&
          detectedCategory.isNotEmpty &&
          _selectedCategory.value.isEmpty) {
        _selectedCategory.value = detectedCategory;
      }
    }
  }

  Future<void> _openCategorySelector() async {
    final result = await showModalBottomSheet<dynamic>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => _QuickCategoryBottomSheet(
        onSelected: (cat) => Navigator.pop(ctx, {'category': cat}),
        onOpenFullServices: () async {
          Navigator.pop(ctx);
          final res = await Get.toNamed(Routes.services);
          if (res != null) {
            _applyCategorySelection(res);
          }
        },
      ),
    );

    if (result != null) {
      _applyCategorySelection(result);
    }
  }

  void _applyCategorySelection(dynamic result) {
    if (result != null && result is Map) {
      final trade = result['trade'] as TradeModel?;
      final sector = result['sector'] as SectorModel?;
      final category =
          result['category'] as String? ?? trade?.name ?? sector?.name ?? '';
      if (category.isNotEmpty) {
        _selectedCategory.value = category;
      }
      if (sector != null) {
        _selectedCategoryId.value = sector.id;
      }
      if (trade != null) {
        _selectedTradeId.value = trade.id;
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: _C.bg,
      body: SafeArea(
        child: Column(
          children: [
            _AppBar(),
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _SectionTitle(title: 'Catégorie de service'),
                    const SizedBox(height: 12),
                    Obx(
                      () => _selectedCategory.value.isEmpty
                          ? _SelectServiceButton(
                              onTap: _openCategorySelector,
                            )
                          : _SelectedServiceCard(
                              category: _selectedCategory.value,
                              onChangeTap: _openCategorySelector,
                            ),
                    ),
                    const SizedBox(height: 24),
                    _SectionTitle(title: 'Détails de la mission'),
                    const SizedBox(height: 8),
                    const Text(
                      'Décrivez votre problème',
                      style: TextStyle(fontSize: 13, color: _C.muted),
                    ),
                    const SizedBox(height: 12),
                    _DescriptionField(controller: _descCtrl),
                    const SizedBox(height: 16),
                    Obx(
                      () => _GeminiEstimateCard(
                        isLoading: _missionsController.isEstimating.value,
                        estimate: _missionsController.estimateResult.value,
                        onAnalyze: _runGeminiEstimate,
                      ),
                    ),
                    const SizedBox(height: 24),
                    _SectionTitle(title: 'Options d\'intervention'),
                    const SizedBox(height: 12),
                    Obx(
                      () => _NightInterventionCard(
                        enabled: _nightIntervention.value,
                        onChanged: (value) => _nightIntervention.value = value,
                      ),
                    ),
                    const SizedBox(height: 24),
                    _SectionTitle(title: 'Visuels (Photos ou Vidéos)'),
                    const SizedBox(height: 12),
                    _MediaPicker(
                      photos: _photos,
                      video: _video,
                      onPickImage: _pickImage,
                      onPickVideo: _pickVideo,
                    ),
                    const SizedBox(height: 24),
                    _SectionTitle(title: 'Votre emplacement'),
                    const SizedBox(height: 12),
                    Obx(
                      () => _LocationCard(
                        location: _location.value,
                        detail: _locationDetail.value,
                        onChangeTap: () async {
                          final result = await Get.toNamed(
                            Routes.locationPicker,
                          );
                          if (result != null && result is Map) {
                            _latitude.value = result['latitude'] ?? 0.0;
                            _longitude.value = result['longitude'] ?? 0.0;
                            _location.value =
                                result['address'] ?? 'Location selected';
                            _locationDetail.value =
                                'Lat: ${_latitude.value.toStringAsFixed(4)}, Lng: ${_longitude.value.toStringAsFixed(4)}';
                          }
                        },
                      ),
                    ),
                    const SizedBox(height: 32),
                    _SearchButton(
                      onPressed: () {
                        if (_selectedCategory.value.isEmpty) {
                          Get.snackbar(
                            'Erreur',
                            'Veuillez sélectionner une catégorie',
                          );
                          return;
                        }
                        if (_descCtrl.text.length < 10) {
                          Get.snackbar(
                            'Erreur',
                            'Veuillez fournir plus de détails',
                          );
                          return;
                        }

                        final bypassError =
                            BypassValidator.validate(_descCtrl.text);
                        if (bypassError != null) {
                          Get.snackbar(
                            'Sécurité',
                            bypassError,
                            backgroundColor: Colors.red[100],
                            colorText: Colors.red[900],
                          );
                          return;
                        }
                        // Navigate to artisan selection with mission data
                        Get.toNamed(
                          Routes.artisanSelection,
                          arguments: {
                            'category': _selectedCategory.value,
                            'categoryId': _selectedCategoryId.value,
                            'tradeId': _selectedTradeId.value,
                            'description': _descCtrl.text,
                            'locationAddress': _location.value,
                            'locationDetail': _locationDetail.value,
                            'latitude': _latitude.value,
                            'longitude': _longitude.value,
                            'nightIntervention': _nightIntervention.value,
                            'photos': _photos.toList(),
                            'video': _video.value,
                          },
                        );
                      },
                    ),
                    const SizedBox(height: 24),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _GeminiEstimateCard extends StatelessWidget {
  final bool isLoading;
  final Map<String, dynamic>? estimate;
  final VoidCallback onAnalyze;

  const _GeminiEstimateCard({
    required this.isLoading,
    required this.estimate,
    required this.onAnalyze,
  });

  @override
  Widget build(BuildContext context) {
    final category = estimate?['category']?.toString();
    final urgency = estimate?['urgency']?.toString();
    final explanation = estimate?['explanation']?.toString();
    final priceMin = estimate?['price_min'];
    final priceMax = estimate?['price_max'];

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _C.primaryLight,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFC7D2FE)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: _C.primary,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(
                  Icons.auto_awesome_rounded,
                  color: Colors.white,
                ),
              ),
              const SizedBox(width: 12),
              const Expanded(
                child: Text(
                  'Pré-analyse Gemini',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: _C.ink,
                  ),
                ),
              ),
              FilledButton(
                onPressed: isLoading ? null : onAnalyze,
                style: FilledButton.styleFrom(
                  backgroundColor: _C.primary,
                  foregroundColor: Colors.white,
                ),
                child: Text(isLoading ? 'Analyse...' : 'Analyser'),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            estimate == null
                ? 'Lance une estimation IA pour obtenir une catégorie, un niveau d’urgence et une fourchette de prix avant de choisir un artisan.'
                : 'Analyse disponible. Tu peux maintenant comparer les artisans avec une meilleure vision du besoin.',
            style: const TextStyle(fontSize: 13, color: _C.muted, height: 1.4),
          ),
          if (estimate != null) ...[
            const SizedBox(height: 16),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                _EstimateBadge(
                  label: 'Catégorie',
                  value: category ?? 'Non définie',
                ),
                _EstimateBadge(
                  label: 'Urgence',
                  value: urgency ?? 'Non définie',
                ),
                _EstimateBadge(
                  label: 'Budget',
                  value: '${_formatFcfa(priceMin)} - ${_formatFcfa(priceMax)}',
                ),
              ],
            ),
            if (explanation != null && explanation.trim().isNotEmpty) ...[
              const SizedBox(height: 12),
              Text(
                explanation,
                style: const TextStyle(
                  fontSize: 13,
                  color: _C.ink,
                  height: 1.4,
                ),
              ),
            ],
          ],
        ],
      ),
    );
  }
}

class _EstimateBadge extends StatelessWidget {
  final String label;
  final String value;

  const _EstimateBadge({
    required this.label,
    required this.value,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(12),
      ),
      child: RichText(
        text: TextSpan(
          style: const TextStyle(fontSize: 12, color: _C.muted),
          children: [
            TextSpan(text: '$label\n'),
            TextSpan(
              text: value,
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w700,
                color: _C.ink,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

String _formatFcfa(dynamic amount) {
  final value =
      amount is int ? amount : int.tryParse(amount?.toString() ?? '') ?? 0;

  final formatted = value.toString().replaceAllMapped(
        RegExp(r'\B(?=(\d{3})+(?!\d))'),
        (_) => ' ',
      );

  return '$formatted FCFA';
}

class _NightInterventionCard extends StatelessWidget {
  final bool enabled;
  final ValueChanged<bool> onChanged;

  const _NightInterventionCard({
    required this.enabled,
    required this.onChanged,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _C.subtle),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: _C.primaryLight,
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(
              Icons.nightlight_round,
              color: _C.primary,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: const [
                Text(
                  'Intervention de nuit',
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: _C.ink,
                  ),
                ),
                SizedBox(height: 4),
                Text(
                  'Affiche seulement les artisans qui acceptent les demandes entre 18h et 7h.',
                  style: TextStyle(
                    fontSize: 13,
                    color: _C.muted,
                    height: 1.35,
                  ),
                ),
              ],
            ),
          ),
          Switch(
            value: enabled,
            activeThumbColor: _C.primary,
            onChanged: onChanged,
          ),
        ],
      ),
    );
  }
}

// ─── App Bar ──────────────────────────────────────────────────────────────────
class _AppBar extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 16),
      child: Row(
        children: [
          GestureDetector(
            onTap: () => Get.back(),
            child: Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: _C.surface,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: _C.subtle),
              ),
              child: const Icon(Icons.arrow_back, size: 20),
            ),
          ),
          const Expanded(
            child: Text(
              'Créer une mission',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: _C.ink,
              ),
            ),
          ),
          const SizedBox(width: 40), // Balance the back button
        ],
      ),
    );
  }
}

// ─── Section Title ────────────────────────────────────────────────────────────
class _SectionTitle extends StatelessWidget {
  final String title;
  const _SectionTitle({required this.title});

  @override
  Widget build(BuildContext context) {
    return Text(
      title,
      style: const TextStyle(
        fontSize: 16,
        fontWeight: FontWeight.w700,
        color: _C.ink,
      ),
    );
  }
}

// ─── Category Chips ───────────────────────────────────────────────────────────
class _SelectServiceButton extends StatelessWidget {
  final VoidCallback onTap;

  const _SelectServiceButton({required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: _C.surface,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: _C.subtle, width: 2),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: const [
            Icon(Icons.add_circle_outline, color: _C.primary, size: 24),
            SizedBox(width: 12),
            Text(
              'Sélectionner la catégorie de service',
              style: TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w600,
                color: _C.primary,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _SelectedServiceCard extends StatelessWidget {
  final String category;
  final VoidCallback onChangeTap;

  const _SelectedServiceCard({
    required this.category,
    required this.onChangeTap,
  });

  @override
  Widget build(BuildContext context) {
    final color = ServiceIconHelper.getSectorColor(category);
    final icon = ServiceIconHelper.getSectorIcon(category);

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.withValues(alpha: 0.3), width: 1.5),
      ),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: color,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: Colors.white, size: 24),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Catégorie sélectionnée',
                  style: TextStyle(
                    fontSize: 12,
                    color: _C.muted,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  category,
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: _C.ink,
                  ),
                ),
              ],
            ),
          ),
          TextButton(
            onPressed: onChangeTap,
            child: const Text(
              'Modifier',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: _C.primary,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _QuickCategoryBottomSheet extends StatelessWidget {
  final ValueChanged<String> onSelected;
  final VoidCallback onOpenFullServices;

  const _QuickCategoryBottomSheet({
    required this.onSelected,
    required this.onOpenFullServices,
  });

  static const List<String> popularCategories = [
    'Plomberie',
    'Électricité',
    'Maçonnerie',
    'Menuiserie',
    'Peinture & Revêtements',
    'Climatisation & Froid',
    'Serrurerie',
    'Mécanique Auto & Moto',
    'Soudure & Métallerie',
    'Sécurité & Domotique',
    'Nettoyage & Entretien',
    'Jardinage & Espaces verts',
  ];

  @override
  Widget build(BuildContext context) {
    return Container(
      height: MediaQuery.of(context).size.height * 0.75,
      decoration: const BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      child: Column(
        children: [
          const SizedBox(height: 12),
          Container(
            width: 40,
            height: 4,
            decoration: BoxDecoration(
              color: const Color(0xFFE5E7EB),
              borderRadius: BorderRadius.circular(2),
            ),
          ),
          const SizedBox(height: 16),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: const [
                      Text(
                        'Sélectionner une catégorie',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w700,
                          color: _C.ink,
                        ),
                      ),
                      SizedBox(height: 4),
                      Text(
                        'Choisissez le domaine correspondant à vos travaux',
                        style: TextStyle(fontSize: 13, color: _C.muted),
                      ),
                    ],
                  ),
                ),
                GestureDetector(
                  onTap: () => Navigator.pop(context),
                  child: Container(
                    width: 32,
                    height: 32,
                    decoration: BoxDecoration(
                      color: const Color(0xFFF1F5F9),
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: const Icon(Icons.close, size: 18, color: _C.muted),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
          Expanded(
            child: GridView.builder(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 4),
              gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                crossAxisCount: 2,
                crossAxisSpacing: 12,
                mainAxisSpacing: 12,
                childAspectRatio: 2.2,
              ),
              itemCount: popularCategories.length,
              itemBuilder: (context, index) {
                final category = popularCategories[index];
                final color = ServiceIconHelper.getSectorColor(category);
                final icon = ServiceIconHelper.getSectorIcon(category);

                return InkWell(
                  onTap: () => onSelected(category),
                  borderRadius: BorderRadius.circular(14),
                  child: Container(
                    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    decoration: BoxDecoration(
                      color: color.withValues(alpha: 0.08),
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: color.withValues(alpha: 0.25)),
                    ),
                    child: Row(
                      children: [
                        Container(
                          width: 38,
                          height: 38,
                          decoration: BoxDecoration(
                            color: color,
                            borderRadius: BorderRadius.circular(10),
                          ),
                          child: Icon(icon, color: Colors.white, size: 20),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            category,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w600,
                              color: color,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                );
              },
            ),
          ),
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
            child: SizedBox(
              width: double.infinity,
              child: OutlinedButton.icon(
                onPressed: onOpenFullServices,
                style: OutlinedButton.styleFrom(
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  side: const BorderSide(color: _C.primary),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                icon: const Icon(Icons.explore_outlined, color: _C.primary, size: 20),
                label: const Text(
                  'Explorer tous les métiers & spécialités',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: _C.primary,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Description Field ────────────────────────────────────────────────────────
class _DescriptionField extends StatelessWidget {
  final TextEditingController controller;
  const _DescriptionField({required this.controller});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _C.subtle),
      ),
      child: TextField(
        controller: controller,
        maxLines: 6,
        maxLength: 500,
        decoration: const InputDecoration(
          hintText:
              'Veuillez fournir autant de détails que possible sur le problème...',
          hintStyle: TextStyle(color: _C.muted, fontSize: 14),
          border: InputBorder.none,
          contentPadding: EdgeInsets.all(16),
          counterStyle: TextStyle(fontSize: 12, color: _C.muted),
        ),
      ),
    );
  }
}

// ─── Media Picker ─────────────────────────────────────────────────────────────
class _MediaPicker extends StatelessWidget {
  final RxList<XFile> photos;
  final Rx<XFile?> video;
  final VoidCallback onPickImage;
  final VoidCallback onPickVideo;

  const _MediaPicker({
    required this.photos,
    required this.video,
    required this.onPickImage,
    required this.onPickVideo,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Expanded(
              child: _MediaButton(
                icon: Icons.add_photo_alternate_outlined,
                label: 'Ajouter une Photo',
                onTap: onPickImage,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: _MediaButton(
                icon: Icons.videocam_outlined,
                label: 'Ajouter une Vidéo',
                onTap: onPickVideo,
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        const Text(
          'Max 5 photos et 1 vidéo (max 30s)',
          style: TextStyle(fontSize: 12, color: _C.muted),
        ),
        Obx(() {
          if (photos.isEmpty && video.value == null) {
            return const SizedBox.shrink();
          }
          return Padding(
            padding: const EdgeInsets.only(top: 12),
            child: Wrap(
              spacing: 8,
              runSpacing: 8,
              children: [
                ...photos.map(
                  (photo) => _MediaThumbnail(
                    file: photo,
                    onRemove: () => photos.remove(photo),
                  ),
                ),
                if (video.value != null)
                  _MediaThumbnail(
                    file: video.value!,
                    isVideo: true,
                    onRemove: () => video.value = null,
                  ),
              ],
            ),
          );
        }),
      ],
    );
  }
}

class _MediaButton extends StatelessWidget {
  final IconData icon;
  final String label;
  final VoidCallback onTap;

  const _MediaButton({
    required this.icon,
    required this.label,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        height: 100,
        decoration: BoxDecoration(
          color: _C.surface,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: _C.subtle, style: BorderStyle.solid),
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 32, color: _C.muted),
            const SizedBox(height: 8),
            Text(
              label,
              style: const TextStyle(
                fontSize: 13,
                color: _C.muted,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _MediaThumbnail extends StatelessWidget {
  final XFile file;
  final bool isVideo;
  final VoidCallback onRemove;

  const _MediaThumbnail({
    required this.file,
    this.isVideo = false,
    required this.onRemove,
  });

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        Container(
          width: 80,
          height: 80,
          decoration: BoxDecoration(
            color: _C.subtle,
            borderRadius: BorderRadius.circular(8),
          ),
          child: Center(
            child: Icon(
              isVideo ? Icons.videocam : Icons.image,
              color: _C.muted,
            ),
          ),
        ),
        Positioned(
          top: 4,
          right: 4,
          child: GestureDetector(
            onTap: onRemove,
            child: Container(
              width: 24,
              height: 24,
              decoration: const BoxDecoration(
                color: Colors.red,
                shape: BoxShape.circle,
              ),
              child: const Icon(Icons.close, size: 16, color: Colors.white),
            ),
          ),
        ),
      ],
    );
  }
}

// ─── Location Card ────────────────────────────────────────────────────────────
class _LocationCard extends StatelessWidget {
  final String location;
  final String detail;
  final VoidCallback onChangeTap;

  const _LocationCard({
    required this.location,
    required this.detail,
    required this.onChangeTap,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _C.subtle),
      ),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: _C.primaryLight,
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Icon(Icons.location_on, color: _C.primary, size: 20),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  location,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: _C.ink,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  detail,
                  style: const TextStyle(fontSize: 12, color: _C.muted),
                ),
              ],
            ),
          ),
          TextButton(
            onPressed: onChangeTap,
            child: const Text(
              'Modifier',
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: _C.primary,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Search Button ────────────────────────────────────────────────────────────
class _SearchButton extends StatelessWidget {
  final VoidCallback onPressed;
  const _SearchButton({required this.onPressed});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      child: ElevatedButton(
        onPressed: onPressed,
        style: ElevatedButton.styleFrom(
          backgroundColor: _C.primary,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(vertical: 16),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: const [
            Icon(Icons.search, size: 20),
            SizedBox(width: 8),
            Text(
              'Rechercher des artisans',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
            ),
          ],
        ),
      ),
    );
  }
}
