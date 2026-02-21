import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../core/constants/app_constants.dart';
import '../../../../shared/controllers/search_controller.dart'
    as artisan_search;
import '../../../../shared/widgets/custom_select2.dart';
import 'search_results_screen.dart';

class SearchFilterScreen extends StatefulWidget {
  const SearchFilterScreen({super.key});

  @override
  State<SearchFilterScreen> createState() => _SearchFilterScreenState();
}

class _SearchFilterScreenState extends State<SearchFilterScreen>
    with SingleTickerProviderStateMixin {
  final _searchController = Get.find<artisan_search.ArtisanSearchController>();
  late AnimationController _animationController;
  late Animation<double> _fadeAnimation;

  @override
  void initState() {
    super.initState();
    _animationController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 600),
    );
    _fadeAnimation = CurvedAnimation(
      parent: _animationController,
      curve: Curves.easeInOut,
    );
    _animationController.forward();
  }

  @override
  void dispose() {
    _animationController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.lightBackground,
      appBar: AppBar(
        elevation: 0,
        backgroundColor: Colors.transparent,
        title: const Text(
          'Filtres de recherche',
          style: TextStyle(fontWeight: FontWeight.w600),
        ),
        actions: [
          TextButton.icon(
            onPressed: () {
              _searchController.clearFilters();
            },
            icon: const Icon(Icons.refresh, size: 18),
            label: const Text('Réinitialiser'),
            style: TextButton.styleFrom(
              foregroundColor: AppColors.lightTextSecondary,
            ),
          ),
          const SizedBox(width: Spacing.sm),
        ],
      ),
      body: FadeTransition(
        opacity: _fadeAnimation,
        child: Column(
          children: [
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(Spacing.screenPadding),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Sector Selection Card
                    _buildSectionCard(
                      context,
                      icon: Icons.category_rounded,
                      title: 'Secteur d\'activité',
                      subtitle: 'Choisissez votre domaine',
                      child: Obx(() {
                        if (_searchController.isLoadingSectors.value) {
                          return _buildLoadingIndicator('Chargement...');
                        }

                        // Create a list with null option for "All sectors"
                        final sectorItems = [
                          null,
                          ..._searchController.sectors,
                        ];

                        return CustomSelect2<dynamic>(
                          selectedItem:
                              _searchController.selectedSectorId.value == null
                              ? null
                              : _searchController.sectors.firstWhereOrNull(
                                  (s) =>
                                      s.id ==
                                      _searchController.selectedSectorId.value,
                                ),
                          items: sectorItems,
                          itemAsString: (item) =>
                              item == null ? 'Tous les secteurs' : item.name,
                          hint: 'Sélectionner un secteur',
                          prefixIcon: Icons.category_outlined,
                          showSearchBox: true,
                          searchHint: 'Rechercher un secteur...',
                          onChanged: (value) {
                            _searchController.setSectorFilter(value?.id);
                          },
                        );
                      }),
                    ),
                    const SizedBox(height: Spacing.lg),

                    // Trade Selection Card
                    Obx(() {
                      if (_searchController.selectedSectorId.value == null) {
                        return const SizedBox.shrink();
                      }

                      return AnimatedContainer(
                        duration: const Duration(milliseconds: 300),
                        child: Column(
                          children: [
                            _buildSectionCard(
                              context,
                              icon: Icons.work_rounded,
                              title: 'Métier spécifique',
                              subtitle: 'Affinez votre recherche (optionnel)',
                              child: _searchController.isLoading.value
                                  ? _buildLoadingIndicator(
                                      'Chargement des métiers...',
                                    )
                                  : _searchController.trades.isEmpty
                                  ? _buildEmptyState('Aucun métier disponible')
                                  : CustomSelect2<dynamic>(
                                      selectedItem:
                                          _searchController
                                                  .selectedTradeId
                                                  .value ==
                                              null
                                          ? null
                                          : _searchController.trades
                                                .firstWhereOrNull(
                                                  (t) =>
                                                      t.id ==
                                                      _searchController
                                                          .selectedTradeId
                                                          .value,
                                                ),
                                      items: [
                                        null,
                                        ..._searchController.trades,
                                      ],
                                      itemAsString: (item) => item == null
                                          ? 'Tous les métiers du secteur'
                                          : item.name,
                                      hint: 'Tous les métiers',
                                      prefixIcon: Icons.work_outline,
                                      showSearchBox: true,
                                      searchHint: 'Rechercher un métier...',
                                      onChanged: (value) {
                                        _searchController.setTradeFilter(
                                          value?.id,
                                        );
                                      },
                                    ),
                            ),
                            const SizedBox(height: Spacing.lg),
                          ],
                        ),
                      );
                    }),

                    // Search Radius Card
                    _buildSectionCard(
                      context,
                      icon: Icons.radar_rounded,
                      title: 'Rayon de recherche',
                      subtitle: 'Distance maximale',
                      child: Obx(
                        () => Column(
                          children: [
                            const SizedBox(height: Spacing.sm),
                            Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: Spacing.lg,
                                vertical: Spacing.md,
                              ),
                              decoration: BoxDecoration(
                                gradient: LinearGradient(
                                  colors: [
                                    Theme.of(context).colorScheme.primary
                                        .withValues(alpha: 0.1),
                                    Theme.of(context).colorScheme.primary
                                        .withValues(alpha: 0.05),
                                  ],
                                ),
                                borderRadius: BorderRadius.circular(
                                  Spacing.radiusLg,
                                ),
                              ),
                              child: Row(
                                mainAxisAlignment:
                                    MainAxisAlignment.spaceBetween,
                                children: [
                                  const Icon(
                                    Icons.location_on,
                                    color: AppColors.goldenMarker,
                                  ),
                                  Text(
                                    '${(_searchController.searchRadius.value / 1000).round()} km',
                                    style: Theme.of(context)
                                        .textTheme
                                        .headlineSmall
                                        ?.copyWith(
                                          fontWeight: FontWeight.bold,
                                          color: Theme.of(
                                            context,
                                          ).colorScheme.primary,
                                        ),
                                  ),
                                  const Icon(
                                    Icons.my_location,
                                    color: AppColors.goldenMarker,
                                  ),
                                ],
                              ),
                            ),
                            SliderTheme(
                              data: SliderTheme.of(context).copyWith(
                                activeTrackColor: Theme.of(
                                  context,
                                ).colorScheme.primary,
                                inactiveTrackColor: AppColors.lightTextTertiary
                                    .withValues(alpha: 0.2),
                                thumbColor: Theme.of(
                                  context,
                                ).colorScheme.primary,
                                overlayColor: Theme.of(
                                  context,
                                ).colorScheme.primary.withValues(alpha: 0.2),
                                thumbShape: const RoundSliderThumbShape(
                                  enabledThumbRadius: 12,
                                ),
                                overlayShape: const RoundSliderOverlayShape(
                                  overlayRadius: 24,
                                ),
                              ),
                              child: Slider(
                                value: _searchController.searchRadius.value,
                                min: 1000,
                                max: AppConstants.maxSearchRadius,
                                divisions: 49,
                                label:
                                    '${(_searchController.searchRadius.value / 1000).round()} km',
                                onChanged: (value) {
                                  _searchController.setSearchRadius(value);
                                },
                              ),
                            ),
                            Padding(
                              padding: const EdgeInsets.symmetric(
                                horizontal: Spacing.md,
                              ),
                              child: Row(
                                mainAxisAlignment:
                                    MainAxisAlignment.spaceBetween,
                                children: [
                                  Text(
                                    '1 km',
                                    style: Theme.of(context).textTheme.bodySmall
                                        ?.copyWith(
                                          color: AppColors.lightTextTertiary,
                                        ),
                                  ),
                                  Text(
                                    '${(AppConstants.maxSearchRadius / 1000).round()} km',
                                    style: Theme.of(context).textTheme.bodySmall
                                        ?.copyWith(
                                          color: AppColors.lightTextTertiary,
                                        ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: Spacing.lg),

                    // Score Filter Card
                    _buildSectionCard(
                      context,
                      icon: Icons.star_rounded,
                      title: 'Score N\'Zassa minimum',
                      subtitle: 'Filtrer par réputation (optionnel)',
                      child: Obx(() {
                        final scoreOptions = [
                          {'value': null, 'label': 'Pas de filtre'},
                          {'value': 50, 'label': '50+ points'},
                          {'value': 70, 'label': '70+ points'},
                          {'value': 85, 'label': '85+ points'},
                        ];

                        return CustomSelect2<Map<String, dynamic>>(
                          selectedItem: scoreOptions.firstWhere(
                            (opt) =>
                                opt['value'] ==
                                _searchController.minScore.value,
                            orElse: () => scoreOptions[0],
                          ),
                          items: scoreOptions,
                          itemAsString: (item) => item['label'] as String,
                          hint: 'Pas de filtre',
                          prefixIcon: Icons.star_outline,
                          showSearchBox: false,
                          itemBuilder: (context, item, isSelected) {
                            final value = item['value'] as int?;
                            final label = item['label'] as String;

                            return Container(
                              padding: const EdgeInsets.symmetric(
                                horizontal: Spacing.md,
                                vertical: Spacing.sm,
                              ),
                              decoration: BoxDecoration(
                                color: isSelected
                                    ? AppColors.lightAccentPrimary.withValues(
                                        alpha: 0.1,
                                      )
                                    : Colors.transparent,
                              ),
                              child: Row(
                                children: [
                                  if (value != null) ...[
                                    const Icon(
                                      Icons.star,
                                      size: 18,
                                      color: Colors.amber,
                                    ),
                                    const SizedBox(width: Spacing.sm),
                                  ],
                                  Expanded(
                                    child: Text(
                                      label,
                                      style: TextStyle(
                                        color: isSelected
                                            ? AppColors.lightAccentPrimary
                                            : AppColors.lightTextPrimary,
                                        fontWeight: isSelected
                                            ? FontWeight.w600
                                            : FontWeight.w400,
                                        fontSize: 14,
                                      ),
                                    ),
                                  ),
                                  if (isSelected)
                                    Icon(
                                      Icons.check_circle,
                                      color: AppColors.lightAccentPrimary,
                                      size: 20,
                                    ),
                                ],
                              ),
                            );
                          },
                          onChanged: (value) {
                            _searchController.setMinScore(
                              value?['value'] as int?,
                            );
                          },
                        );
                      }),
                    ),
                    const SizedBox(height: Spacing.lg),

                    // Sort By Card
                    _buildSectionCard(
                      context,
                      icon: Icons.sort_rounded,
                      title: 'Trier par',
                      subtitle: 'Ordre d\'affichage des résultats',
                      child: Obx(
                        () => Column(
                          children: [
                            const SizedBox(height: Spacing.sm),
                            _buildSortOption(
                              context,
                              value: 'distance',
                              icon: Icons.near_me_rounded,
                              title: 'Distance',
                              subtitle: 'Les plus proches',
                              isSelected:
                                  _searchController.sortBy.value == 'distance',
                              onTap: () =>
                                  _searchController.setSortBy('distance'),
                            ),
                            const SizedBox(height: Spacing.sm),
                            _buildSortOption(
                              context,
                              value: 'rating',
                              icon: Icons.star_rounded,
                              title: 'Note',
                              subtitle: 'Les mieux notés',
                              isSelected:
                                  _searchController.sortBy.value == 'rating',
                              onTap: () =>
                                  _searchController.setSortBy('rating'),
                            ),
                            const SizedBox(height: Spacing.sm),
                            _buildSortOption(
                              context,
                              value: 'experience',
                              icon: Icons.work_rounded,
                              title: 'Expérience',
                              subtitle: 'Les plus expérimentés',
                              isSelected:
                                  _searchController.sortBy.value ==
                                  'experience',
                              onTap: () =>
                                  _searchController.setSortBy('experience'),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 100),
                  ],
                ),
              ),
            ),

            // Bottom Search Button
            _buildBottomSearchButton(context),
          ],
        ),
      ),
    );
  }

  Widget _buildSectionCard(
    BuildContext context, {
    required IconData icon,
    required String title,
    required String subtitle,
    required Widget child,
  }) {
    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(Spacing.radiusLg),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.05),
            blurRadius: 10,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Padding(
        padding: const EdgeInsets.all(Spacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(Spacing.sm),
                  decoration: BoxDecoration(
                    color: Theme.of(
                      context,
                    ).colorScheme.primary.withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(Spacing.radiusMd),
                  ),
                  child: Icon(
                    icon,
                    color: Theme.of(context).colorScheme.primary,
                    size: 24,
                  ),
                ),
                const SizedBox(width: Spacing.md),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w600),
                      ),
                      Text(
                        subtitle,
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: AppColors.lightTextSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: Spacing.md),
            child,
          ],
        ),
      ),
    );
  }

  Widget _buildModernDropdown<T>({
    required T? value,
    required String hint,
    required IconData icon,
    required List<DropdownMenuItem<T>> items,
    required void Function(T?) onChanged,
  }) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.lightBackground,
        borderRadius: BorderRadius.circular(Spacing.radiusMd),
        border: Border.all(
          color: AppColors.lightTextTertiary.withValues(alpha: 0.2),
        ),
      ),
      child: DropdownButtonFormField<T>(
        value: value,
        isExpanded: true,
        decoration: InputDecoration(
          hintText: hint,
          prefixIcon: Icon(icon, color: AppColors.lightTextSecondary),
          border: InputBorder.none,
          contentPadding: const EdgeInsets.symmetric(
            horizontal: Spacing.md,
            vertical: Spacing.sm,
          ),
        ),
        dropdownColor: Colors.white,
        items: items,
        onChanged: onChanged,
      ),
    );
  }

  Widget _buildSortOption(
    BuildContext context, {
    required String value,
    required IconData icon,
    required String title,
    required String subtitle,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(Spacing.radiusMd),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.all(Spacing.md),
        decoration: BoxDecoration(
          color: isSelected
              ? Theme.of(context).colorScheme.primary.withValues(alpha: 0.1)
              : AppColors.lightBackground,
          borderRadius: BorderRadius.circular(Spacing.radiusMd),
          border: Border.all(
            color: isSelected
                ? Theme.of(context).colorScheme.primary
                : AppColors.lightTextTertiary.withValues(alpha: 0.2),
            width: isSelected ? 2 : 1,
          ),
        ),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(Spacing.sm),
              decoration: BoxDecoration(
                color: isSelected
                    ? Theme.of(context).colorScheme.primary
                    : AppColors.lightTextTertiary.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(Spacing.radiusSm),
              ),
              child: Icon(
                icon,
                color: isSelected ? Colors.white : AppColors.lightTextSecondary,
                size: 20,
              ),
            ),
            const SizedBox(width: Spacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      fontWeight: isSelected
                          ? FontWeight.w600
                          : FontWeight.normal,
                      color: isSelected
                          ? Theme.of(context).colorScheme.primary
                          : AppColors.lightTextPrimary,
                    ),
                  ),
                  Text(
                    subtitle,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.lightTextSecondary,
                    ),
                  ),
                ],
              ),
            ),
            if (isSelected)
              Icon(
                Icons.check_circle,
                color: Theme.of(context).colorScheme.primary,
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildLoadingIndicator(String message) {
    return Container(
      padding: const EdgeInsets.all(Spacing.md),
      decoration: BoxDecoration(
        color: AppColors.lightBackground,
        borderRadius: BorderRadius.circular(Spacing.radiusMd),
      ),
      child: Row(
        children: [
          SizedBox(
            width: 20,
            height: 20,
            child: CircularProgressIndicator(
              strokeWidth: 2,
              color: Theme.of(context).colorScheme.primary,
            ),
          ),
          const SizedBox(width: Spacing.md),
          Expanded(
            child: Text(
              message,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: AppColors.lightTextSecondary,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildEmptyState(String message) {
    return Container(
      padding: const EdgeInsets.all(Spacing.md),
      decoration: BoxDecoration(
        color: AppColors.lightBackground,
        borderRadius: BorderRadius.circular(Spacing.radiusMd),
      ),
      child: Row(
        children: [
          Icon(
            Icons.info_outline,
            color: AppColors.lightTextTertiary,
            size: 20,
          ),
          const SizedBox(width: Spacing.md),
          Expanded(
            child: Text(
              message,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: AppColors.lightTextSecondary,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBottomSearchButton(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(Spacing.screenPadding),
      decoration: BoxDecoration(
        color: Colors.white,
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.1),
            blurRadius: 20,
            offset: const Offset(0, -5),
          ),
        ],
      ),
      child: SafeArea(
        top: false,
        child: Obx(
          () => ElevatedButton(
            onPressed: _searchController.isLoading.value
                ? null
                : () async {
                    await _searchController.searchArtisans();
                    if (_searchController.searchResults.isNotEmpty) {
                      Get.to(() => const SearchResultsScreen());
                    } else {
                      Get.snackbar(
                        'Aucun résultat',
                        'Aucun artisan trouvé avec ces critères',
                        backgroundColor: AppColors.warning,
                        colorText: Colors.white,
                        snackPosition: SnackPosition.TOP,
                        icon: const Icon(Icons.search_off, color: Colors.white),
                      );
                    }
                  },
            style: ElevatedButton.styleFrom(
              padding: const EdgeInsets.symmetric(vertical: Spacing.md),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(Spacing.radiusLg),
              ),
            ),
            child: _searchController.isLoading.value
                ? const SizedBox(
                    height: 20,
                    width: 20,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                    ),
                  )
                : Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(Icons.search_rounded),
                      const SizedBox(width: Spacing.sm),
                      Text(
                        'Rechercher des artisans',
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(
                              color: Colors.white,
                              fontWeight: FontWeight.w600,
                            ),
                      ),
                    ],
                  ),
          ),
        ),
      ),
    );
  }
}
