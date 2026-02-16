import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/controllers/search_controller.dart' as artisan_search;
import '../../../../core/network/search_service.dart';
import '../../../../shared/models/artisan_search_model.dart';

class ArtisanProfileScreen extends StatefulWidget {
  final int artisanId;

  const ArtisanProfileScreen({super.key, required this.artisanId});

  @override
  State<ArtisanProfileScreen> createState() => _ArtisanProfileScreenState();
}

class _ArtisanProfileScreenState extends State<ArtisanProfileScreen> {
  final SearchService _searchService = SearchService();
  ArtisanSearchResult? _artisan;
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadArtisanProfile();
  }

  Future<void> _loadArtisanProfile() async {
    setState(() => _isLoading = true);
    try {
      final response = await _searchService.getArtisanProfile(widget.artisanId);
      if (response.success && response.data != null) {
        setState(() {
          _artisan = response.data;
          _isLoading = false;
        });
      }
    } catch (e) {
      setState(() => _isLoading = false);
      Get.snackbar(
        'Erreur',
        'Impossible de charger le profil',
        backgroundColor: AppColors.error,
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return Scaffold(
        appBar: AppBar(),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    if (_artisan == null) {
      return Scaffold(
        appBar: AppBar(),
        body: const Center(child: Text('Profil non trouvé')),
      );
    }

    return Scaffold(
      body: CustomScrollView(
        slivers: [
          // App Bar with Avatar
          SliverAppBar(
            expandedHeight: 200,
            pinned: true,
            flexibleSpace: FlexibleSpaceBar(
              background: Container(
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    colors: AppColors.primaryGradient,
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                  ),
                ),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    const SizedBox(height: 40),
                    CircleAvatar(
                      radius: 50,
                      backgroundColor: Colors.white,
                      backgroundImage: _artisan!.avatar != null
                          ? NetworkImage(_artisan!.avatar!)
                          : null,
                      child: _artisan!.avatar == null
                          ? Icon(Icons.person, size: 50, color: AppColors.lightAccentPrimary)
                          : null,
                    ),
                    const SizedBox(height: Spacing.md),
                    Text(
                      _artisan!.name,
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                            color: Colors.white,
                            fontWeight: FontWeight.bold,
                          ),
                    ),
                    Text(
                      _artisan!.tradeName,
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                            color: Colors.white.withValues(alpha: 0.9),
                          ),
                    ),
                  ],
                ),
              ),
            ),
          ),

          // Profile Content
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.all(Spacing.screenPadding),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  // Stats Row
                  Row(
                    children: [
                      Expanded(
                        child: _buildStatCard(
                          icon: Icons.star,
                          value: _artisan!.averageRating?.toStringAsFixed(1) ?? 'N/A',
                          label: 'Note',
                          color: AppColors.starRating,
                        ),
                      ),
                      const SizedBox(width: Spacing.md),
                      Expanded(
                        child: _buildStatCard(
                          icon: Icons.work,
                          value: '${_artisan!.projectsCompleted}',
                          label: 'Projets',
                          color: AppColors.success,
                        ),
                      ),
                      const SizedBox(width: Spacing.md),
                      Expanded(
                        child: _buildStatCard(
                          icon: Icons.workspace_premium,
                          value: _artisan!.nzassaScore?.toString() ?? 'N/A',
                          label: 'N\'Zassa',
                          color: AppColors.lightAccentPrimary,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: Spacing.xl),

                  // Experience
                  if (_artisan!.experienceYears > 0) ...[
                    _buildSectionHeader('Expérience'),
                    const SizedBox(height: Spacing.md),
                    Container(
                      padding: const EdgeInsets.all(Spacing.base),
                      decoration: BoxDecoration(
                        color: AppColors.lightBackground,
                        borderRadius: BorderRadius.circular(Spacing.radiusMd),
                      ),
                      child: Row(
                        children: [
                          Icon(
                            Icons.calendar_today,
                            color: AppColors.lightAccentPrimary,
                          ),
                          const SizedBox(width: Spacing.md),
                          Text(
                            '${_artisan!.experienceYears} ans d\'expérience',
                            style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                                  fontWeight: FontWeight.w600,
                                ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: Spacing.xl),
                  ],

                  // Bio
                  if (_artisan!.artisanProfile?.bio != null) ...[
                    _buildSectionHeader('À propos'),
                    const SizedBox(height: Spacing.md),
                    Text(
                      _artisan!.artisanProfile!.bio!,
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                    const SizedBox(height: Spacing.xl),
                  ],

                  // Location
                  if (_artisan!.zoneName != null) ...[
                    _buildSectionHeader('Zone d\'intervention'),
                    const SizedBox(height: Spacing.md),
                    Container(
                      padding: const EdgeInsets.all(Spacing.base),
                      decoration: BoxDecoration(
                        color: AppColors.lightBackground,
                        borderRadius: BorderRadius.circular(Spacing.radiusMd),
                      ),
                      child: Row(
                        children: [
                          Icon(
                            Icons.location_on,
                            color: _artisan!.isNearby
                                ? AppColors.goldenMarker
                                : AppColors.lightAccentPrimary,
                          ),
                          const SizedBox(width: Spacing.md),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                Text(
                                  _artisan!.zoneName!,
                                  style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                                        fontWeight: FontWeight.w600,
                                      ),
                                ),
                                if (_artisan!.distance != null)
                                  Text(
                                    'À ${_artisan!.distanceText} de vous',
                                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                          color: AppColors.lightTextSecondary,
                                        ),
                                  ),
                              ],
                            ),
                          ),
                          if (_artisan!.isNearby)
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: Spacing.sm,
                                vertical: Spacing.xs,
                              ),
                              decoration: BoxDecoration(
                                color: AppColors.goldenMarker.withValues(alpha: 0.2),
                                borderRadius: BorderRadius.circular(Spacing.radiusSm),
                              ),
                              child: Text(
                                'Proche',
                                style: TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.bold,
                                  color: AppColors.goldenMarker,
                                ),
                              ),
                            ),
                        ],
                      ),
                    ),
                    const SizedBox(height: Spacing.xl),
                  ],

                  // Availability Status
                  Container(
                    padding: const EdgeInsets.all(Spacing.base),
                    decoration: BoxDecoration(
                      color: _artisan!.isAvailable
                          ? AppColors.success.withValues(alpha: 0.1)
                          : AppColors.warning.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(Spacing.radiusMd),
                      border: Border.all(
                        color: _artisan!.isAvailable
                            ? AppColors.success
                            : AppColors.warning,
                      ),
                    ),
                    child: Row(
                      children: [
                        Icon(
                          _artisan!.isAvailable
                              ? Icons.check_circle
                              : Icons.access_time,
                          color: _artisan!.isAvailable
                              ? AppColors.success
                              : AppColors.warning,
                        ),
                        const SizedBox(width: Spacing.md),
                        Text(
                          _artisan!.isAvailable
                              ? 'Disponible pour de nouveaux projets'
                              : 'Non disponible actuellement',
                          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                fontWeight: FontWeight.w600,
                                color: _artisan!.isAvailable
                                    ? AppColors.success
                                    : AppColors.warning,
                              ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(height: Spacing.xxxl),

                  // Action Buttons
                  ElevatedButton.icon(
                    onPressed: () {
                      // TODO: Navigate to create project with selected artisan
                      Get.snackbar(
                        'Demander un devis',
                        'Fonctionnalité disponible prochainement',
                        backgroundColor: AppColors.info,
                        colorText: Colors.white,
                        snackPosition: SnackPosition.TOP,
                      );
                    },
                    icon: const Icon(Icons.description_outlined),
                    label: const Text('Demander un devis'),
                  ),
                  const SizedBox(height: Spacing.md),

                  OutlinedButton.icon(
                    onPressed: () {
                      // TODO: Open chat with artisan
                      Get.snackbar(
                        'Contacter',
                        'Messagerie disponible prochainement',
                        backgroundColor: AppColors.info,
                        colorText: Colors.white,
                        snackPosition: SnackPosition.TOP,
                      );
                    },
                    icon: const Icon(Icons.chat_outlined),
                    label: const Text('Envoyer un message'),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildSectionHeader(String title) {
    return Text(
      title,
      style: Theme.of(context).textTheme.titleLarge?.copyWith(
            fontWeight: FontWeight.bold,
          ),
    );
  }

  Widget _buildStatCard({
    required IconData icon,
    required String value,
    required String label,
    required Color color,
  }) {
    return Container(
      padding: const EdgeInsets.all(Spacing.base),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(Spacing.radiusMd),
      ),
      child: Column(
        children: [
          Icon(icon, color: color, size: 28),
          const SizedBox(height: Spacing.sm),
          Text(
            value,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.bold,
                  color: color,
                ),
          ),
          Text(
            label,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: AppColors.lightTextSecondary,
                ),
          ),
        ],
      ),
    );
  }
}
