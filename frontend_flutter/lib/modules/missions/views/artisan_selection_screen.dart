import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../data/models/artisan_model.dart';
import '../../../shared/widgets/score_nzassa.dart';
import '../controllers/artisan_selection_controller.dart';

// ─── Design Tokens ────────────────────────────────────────────────────────────
abstract class _C {
  static const bg = Color(0xFFF8F9FA);
  static const surface = Colors.white;
  static const primary = Color(0xFF4F46E5);
  static const primaryLight = Color(0xFFEEF2FF);
  static const ink = Color(0xFF111827);
  static const muted = Color(0xFF6B7280);
  static const subtle = Color(0xFFE5E7EB);
  static const success = Color(0xFF10B981);
  static const warning = Color(0xFFF59E0B);
}

class ArtisanSelectionScreen extends StatelessWidget {
  const ArtisanSelectionScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.put(ArtisanSelectionController());

    // Récupérer les données de la mission depuis les arguments
    final args = Get.arguments as Map<String, dynamic>?;
    if (args != null) {
      controller.initializeWithMissionData(args);
    }

    return Scaffold(
      backgroundColor: _C.bg,
      body: SafeArea(
        child: Column(
          children: [
            _AppBar(),
            _ViewToggle(controller: controller),
            Expanded(
              child: Obx(() {
                if (controller.isLoading.value) {
                  return const Center(child: CircularProgressIndicator());
                }

                return controller.isMapView.value
                    ? _MapView(controller: controller)
                    : _ListView(controller: controller);
              }),
            ),
          ],
        ),
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
              'Sélectionner un artisan',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: _C.ink,
              ),
            ),
          ),
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: _C.surface,
              borderRadius: BorderRadius.circular(12),
              border: Border.all(color: _C.subtle),
            ),
            child: const Icon(Icons.filter_list, size: 20, color: _C.muted),
          ),
        ],
      ),
    );
  }
}

// ─── View Toggle ──────────────────────────────────────────────────────────────
class _ViewToggle extends StatelessWidget {
  final ArtisanSelectionController controller;

  const _ViewToggle({required this.controller});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 20, vertical: 8),
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _C.subtle),
      ),
      child: Row(
        children: [
          Expanded(
            child: Obx(
              () => _ToggleButton(
                icon: Icons.map_outlined,
                label: 'Carte',
                isSelected: controller.isMapView.value,
                onTap: () => controller.setMapView(true),
              ),
            ),
          ),
          Expanded(
            child: Obx(
              () => _ToggleButton(
                icon: Icons.list_outlined,
                label: 'Liste',
                isSelected: !controller.isMapView.value,
                onTap: () => controller.setMapView(false),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _ToggleButton extends StatelessWidget {
  final IconData icon;
  final String label;
  final bool isSelected;
  final VoidCallback onTap;

  const _ToggleButton({
    required this.icon,
    required this.label,
    required this.isSelected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 12),
        decoration: BoxDecoration(
          color: isSelected ? _C.primary : Colors.transparent,
          borderRadius: BorderRadius.circular(8),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 18, color: isSelected ? Colors.white : _C.muted),
            const SizedBox(width: 8),
            Text(
              label,
              style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w600,
                color: isSelected ? Colors.white : _C.muted,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ─── Map View ─────────────────────────────────────────────────────────────────
class _MapView extends StatelessWidget {
  final ArtisanSelectionController controller;

  const _MapView({required this.controller});

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _C.subtle),
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(16),
        child: Stack(
          children: [
            // Placeholder pour la carte - remplacer par YandexMap
            Container(
              height: double.infinity,
              color: _C.subtle,
              child: const Center(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.map, size: 64, color: _C.muted),
                    SizedBox(height: 16),
                    Text(
                      'Vue carte des artisans',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                        color: _C.muted,
                      ),
                    ),
                    SizedBox(height: 8),
                    Text(
                      'Carte Yandex Maps intégrée ici',
                      style: TextStyle(fontSize: 14, color: _C.muted),
                    ),
                  ],
                ),
              ),
            ),

            // Bouton pour ouvrir la carte complète
            Positioned(
              bottom: 16,
              right: 16,
              child: ElevatedButton.icon(
                onPressed: () => Get.toNamed(Routes.artisanMap),
                icon: const Icon(Icons.fullscreen, size: 18),
                label: const Text('Carte complète'),
                style: ElevatedButton.styleFrom(
                  backgroundColor: _C.primary,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 12,
                  ),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ─── List View ────────────────────────────────────────────────────────────────
class _ListView extends StatelessWidget {
  final ArtisanSelectionController controller;

  const _ListView({required this.controller});

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      final artisans = controller.artisans;

      if (artisans.isEmpty) {
        return _EmptyState();
      }

      return RefreshIndicator(
        onRefresh: controller.refreshArtisans,
        child: ListView.separated(
          padding: const EdgeInsets.all(20),
          itemCount: artisans.length,
          separatorBuilder: (_, __) => const SizedBox(height: 16),
          itemBuilder: (_, index) => _ArtisanCard(
            artisan: artisans[index],
            onTap: () => controller.selectArtisan(artisans[index]),
          ),
        ),
      );
    });
  }
}

// ─── Artisan Card ─────────────────────────────────────────────────────────────
class _ArtisanCard extends StatelessWidget {
  final ArtisanModel artisan;
  final VoidCallback onTap;

  const _ArtisanCard({required this.artisan, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: _C.surface,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: artisan.isGoldenMarker ? _C.warning : _C.subtle,
            width: artisan.isGoldenMarker ? 2 : 1,
          ),
          boxShadow: [
            BoxShadow(
              color: _C.ink.withValues(alpha: 0.04),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header avec badge élite si applicable
            if (artisan.isGoldenMarker)
              Container(
                margin: const EdgeInsets.only(bottom: 12),
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: _C.warning.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(Icons.stars, size: 14, color: _C.warning),
                    const SizedBox(width: 4),
                    Text(
                      'ARTISAN D\'ÉLITE',
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                        color: _C.warning,
                        letterSpacing: 0.5,
                      ),
                    ),
                  ],
                ),
              ),

            // Contenu principal
            Row(
              children: [
                // Avatar
                Container(
                  width: 60,
                  height: 60,
                  decoration: BoxDecoration(
                    color: _C.primaryLight,
                    borderRadius: BorderRadius.circular(12),
                    image: artisan.photo != null
                        ? DecorationImage(
                            image: NetworkImage(artisan.photo!),
                            fit: BoxFit.cover,
                          )
                        : null,
                  ),
                  child: artisan.photo == null
                      ? const Icon(Icons.person, color: _C.primary, size: 30)
                      : null,
                ),
                const SizedBox(width: 12),

                // Infos
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        artisan.name ?? 'Artisan',
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w700,
                          color: _C.ink,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Text(
                        artisan.trade ?? 'Métier',
                        style: const TextStyle(fontSize: 14, color: _C.muted),
                      ),
                      const SizedBox(height: 8),
                      Row(
                        children: [
                          Icon(
                            Icons.location_on_outlined,
                            size: 14,
                            color: _C.muted,
                          ),
                          const SizedBox(width: 4),
                          Text(
                            artisan.distance ??
                                artisan.locationLabel ??
                                artisan.commune ??
                                'Distance inconnue',
                            style: const TextStyle(
                              fontSize: 13,
                              color: _C.muted,
                            ),
                          ),
                          const SizedBox(width: 12),
                          Icon(Icons.star, size: 14, color: _C.warning),
                          const SizedBox(width: 4),
                          Text(
                            '${artisan.rating}',
                            style: const TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w600,
                              color: _C.ink,
                            ),
                          ),
                        ],
                      ),
                    ],
                  ),
                ),

                // Score N'Zassa
                Column(
                  children: [
                    ScoreNzassaBadge(score: artisan.scoreNzassa),
                    const SizedBox(height: 8),
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 8,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: _C.success.withValues(alpha: 0.1),
                        borderRadius: BorderRadius.circular(6),
                      ),
                      child: Text(
                        'Disponible',
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w600,
                          color: _C.success,
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),

            const SizedBox(height: 16),

            // Actions
            Row(
              children: [
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: () =>
                        Get.toNamed(Routes.artisanProfile, arguments: artisan),
                    icon: const Icon(Icons.person_outline, size: 16),
                    label: const Text('Profil'),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: _C.primary,
                      side: BorderSide(
                        color: _C.primary.withValues(alpha: 0.3),
                      ),
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(8),
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: ElevatedButton.icon(
                    onPressed: onTap,
                    icon: const Icon(Icons.handshake, size: 16),
                    label: const Text('Choisir'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: _C.primary,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 12),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(8),
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

// ─── Empty State ──────────────────────────────────────────────────────────────
class _EmptyState extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 80,
            height: 80,
            decoration: BoxDecoration(
              color: _C.primaryLight,
              borderRadius: BorderRadius.circular(20),
            ),
            child: const Icon(Icons.search_off, size: 40, color: _C.primary),
          ),
          const SizedBox(height: 16),
          const Text(
            'Aucun artisan trouvé',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w700,
              color: _C.ink,
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Essayez d\'élargir votre zone de recherche\nou changez de catégorie',
            textAlign: TextAlign.center,
            style: TextStyle(fontSize: 14, color: _C.muted),
          ),
          const SizedBox(height: 24),
          ElevatedButton.icon(
            onPressed: () =>
                Get.find<ArtisanSelectionController>().refreshArtisans(),
            icon: const Icon(Icons.refresh, size: 18),
            label: const Text('Actualiser'),
            style: ElevatedButton.styleFrom(
              backgroundColor: _C.primary,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
