import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../core/constants/app_constants.dart';
import '../../../../shared/controllers/search_controller.dart'
    as artisan_search;
import 'search_results_screen.dart';

class SearchFilterScreen extends StatefulWidget {
  const SearchFilterScreen({super.key});

  @override
  State<SearchFilterScreen> createState() => _SearchFilterScreenState();
}

class _SearchFilterScreenState extends State<SearchFilterScreen> {
  final _searchController = Get.find<artisan_search.ArtisanSearchController>();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Rechercher un artisan')),
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(Spacing.screenPadding),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Sector Selection
                    Text(
                      'Secteur d\'activité',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: Spacing.md),

                    Obx(() {
                      if (_searchController.isLoadingSectors.value) {
                        return Container(
                          padding: const EdgeInsets.all(Spacing.lg),
                          decoration: BoxDecoration(
                            color: AppColors.darkCard,
                            borderRadius: BorderRadius.circular(
                              Spacing.radiusMd,
                            ),
                            border: Border.all(color: AppColors.overlayLight),
                          ),
                          child: Row(
                            children: [
                              SizedBox(
                                width: 20,
                                height: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  color: AppColors.darkAccentPrimary,
                                ),
                              ),
                              const SizedBox(width: Spacing.md),
                              const Text(
                                'Chargement des secteurs...',
                                style: TextStyle(
                                  color: AppColors.darkTextSecondary,
                                ),
                              ),
                            ],
                          ),
                        );
                      }

                      return DropdownButtonFormField<int>(
                        value: _searchController.selectedSectorId.value,
                        decoration: InputDecoration(
                          hintText: 'Sélectionner un secteur',
                          prefixIcon: const Icon(Icons.category_outlined),
                          filled: true,
                          fillColor: AppColors.darkCard,
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(
                              Spacing.radiusMd,
                            ),
                            borderSide: const BorderSide(
                              color: AppColors.overlayLight,
                            ),
                          ),
                          enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(
                              Spacing.radiusMd,
                            ),
                            borderSide: const BorderSide(
                              color: AppColors.overlayLight,
                            ),
                          ),
                        ),
                        dropdownColor: AppColors.darkCard,
                        style: const TextStyle(
                          color: AppColors.darkTextPrimary,
                        ),
                        items: [
                          const DropdownMenuItem(
                            value: null,
                            child: Text('Tous les secteurs'),
                          ),
                          ..._searchController.sectors.map((sector) {
                            return DropdownMenuItem(
                              value: sector.id,
                              child: Text(sector.name),
                            );
                          }),
                        ],
                        onChanged: (value) {
                          _searchController.setSectorFilter(value);
                        },
                      );
                    }),
                    const SizedBox(height: Spacing.xl),

                    // Trade Selection
                    Obx(() {
                      if (_searchController.selectedSectorId.value == null) {
                        return const SizedBox.shrink();
                      }

                      return Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Text(
                            'Métier',
                            style: Theme.of(context).textTheme.titleMedium
                                ?.copyWith(
                                  fontWeight: FontWeight.w600,
                                  color: AppColors.darkTextPrimary,
                                ),
                          ),
                          const SizedBox(height: Spacing.sm),
                          Text(
                            'Sélectionnez un métier spécifique (optionnel)',
                            style: Theme.of(context).textTheme.bodySmall
                                ?.copyWith(color: AppColors.darkTextSecondary),
                          ),
                          const SizedBox(height: Spacing.md),

                          // Loading indicator for trades
                          if (_searchController.isLoading.value)
                            Container(
                              padding: const EdgeInsets.all(Spacing.lg),
                              decoration: BoxDecoration(
                                color: AppColors.darkCard,
                                borderRadius: BorderRadius.circular(
                                  Spacing.radiusMd,
                                ),
                                border: Border.all(
                                  color: AppColors.overlayLight,
                                ),
                              ),
                              child: Row(
                                children: [
                                  SizedBox(
                                    width: 20,
                                    height: 20,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                      color: AppColors.darkAccentPrimary,
                                    ),
                                  ),
                                  const SizedBox(width: Spacing.md),
                                  const Text(
                                    'Chargement des métiers...',
                                    style: TextStyle(
                                      color: AppColors.darkTextSecondary,
                                    ),
                                  ),
                                ],
                              ),
                            )
                          else if (_searchController.trades.isEmpty)
                            Container(
                              padding: const EdgeInsets.all(Spacing.lg),
                              decoration: BoxDecoration(
                                color: AppColors.darkCard,
                                borderRadius: BorderRadius.circular(
                                  Spacing.radiusMd,
                                ),
                                border: Border.all(
                                  color: AppColors.overlayLight,
                                ),
                              ),
                              child: Row(
                                children: [
                                  Icon(
                                    Icons.info_outline,
                                    color: AppColors.darkTextTertiary,
                                    size: 20,
                                  ),
                                  const SizedBox(width: Spacing.md),
                                  const Expanded(
                                    child: Text(
                                      'Aucun métier disponible pour ce secteur',
                                      style: TextStyle(
                                        color: AppColors.darkTextSecondary,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            )
                          else
                            DropdownButtonFormField<int>(
                              value: _searchController.selectedTradeId.value,
                              decoration: InputDecoration(
                                hintText: 'Tous les métiers du secteur',
                                hintStyle: const TextStyle(
                                  color: AppColors.darkTextTertiary,
                                ),
                                prefixIcon: const Icon(
                                  Icons.work_outline,
                                  color: AppColors.darkTextSecondary,
                                ),
                                filled: true,
                                fillColor: AppColors.darkCard,
                                border: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(
                                    Spacing.radiusMd,
                                  ),
                                  borderSide: const BorderSide(
                                    color: AppColors.overlayLight,
                                  ),
                                ),
                                enabledBorder: OutlineInputBorder(
                                  borderRadius: BorderRadius.circular(
                                    Spacing.radiusMd,
                                  ),
                                  borderSide: const BorderSide(
                                    color: AppColors.overlayLight,
                                  ),
                                ),
                              ),
                              dropdownColor: AppColors.darkCard,
                              style: const TextStyle(
                                color: AppColors.darkTextPrimary,
                              ),
                              items: [
                                const DropdownMenuItem(
                                  value: null,
                                  child: Text('Tous les métiers du secteur'),
                                ),
                                ..._searchController.trades.map((trade) {
                                  return DropdownMenuItem(
                                    value: trade.id,
                                    child: Text(trade.name),
                                  );
                                }),
                              ],
                              onChanged: (value) {
                                _searchController.setTradeFilter(value);
                              },
                            ),
                          const SizedBox(height: Spacing.xl),
                        ],
                      );
                    }),

                    // Search Radius
                    Text(
                      'Rayon de recherche',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: Spacing.md),

                    Obx(
                      () => Column(
                        children: [
                          Slider(
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
                          Text(
                            '${(_searchController.searchRadius.value / 1000).round()} km',
                            style: Theme.of(context).textTheme.bodyLarge
                                ?.copyWith(
                                  fontWeight: FontWeight.w600,
                                  color: Theme.of(context).colorScheme.primary,
                                ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: Spacing.xl),

                    // Minimum Score Filter
                    Text(
                      'Score N\'Zassa minimum',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: Spacing.sm),
                    Text(
                      'Optionnel - Filtrer par réputation',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.lightTextSecondary,
                      ),
                    ),
                    const SizedBox(height: Spacing.md),

                    Obx(
                      () => DropdownButtonFormField<int>(
                        value: _searchController.minScore.value,
                        decoration: const InputDecoration(
                          hintText: 'Pas de filtre',
                          prefixIcon: Icon(Icons.star_outline),
                        ),
                        items: const [
                          DropdownMenuItem(
                            value: null,
                            child: Text('Pas de filtre'),
                          ),
                          DropdownMenuItem(
                            value: 50,
                            child: Text('50+ points'),
                          ),
                          DropdownMenuItem(
                            value: 70,
                            child: Text('70+ points'),
                          ),
                          DropdownMenuItem(
                            value: 85,
                            child: Text('85+ points'),
                          ),
                        ],
                        onChanged: (value) {
                          _searchController.setMinScore(value);
                        },
                      ),
                    ),
                    const SizedBox(height: Spacing.xl),

                    // Sort By
                    Text(
                      'Trier par',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    const SizedBox(height: Spacing.md),

                    Obx(
                      () => SegmentedButton<String>(
                        segments: const [
                          ButtonSegment(
                            value: 'distance',
                            label: Text('Distance'),
                            icon: Icon(Icons.near_me),
                          ),
                          ButtonSegment(
                            value: 'rating',
                            label: Text('Note'),
                            icon: Icon(Icons.star),
                          ),
                          ButtonSegment(
                            value: 'experience',
                            label: Text('Expérience'),
                            icon: Icon(Icons.work),
                          ),
                        ],
                        selected: {_searchController.sortBy.value},
                        onSelectionChanged: (Set<String> selection) {
                          _searchController.setSortBy(selection.first);
                        },
                      ),
                    ),
                  ],
                ),
              ),
            ),

            // Bottom Actions
            Container(
              padding: const EdgeInsets.all(Spacing.screenPadding),
              decoration: BoxDecoration(
                color: Colors.white,
                boxShadow: [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.05),
                    blurRadius: 10,
                    offset: const Offset(0, -2),
                  ),
                ],
              ),
              child: Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () {
                        _searchController.clearFilters();
                      },
                      child: const Text('Réinitialiser'),
                    ),
                  ),
                  const SizedBox(width: Spacing.md),
                  Expanded(
                    flex: 2,
                    child: Obx(
                      () => ElevatedButton(
                        onPressed: _searchController.isLoading.value
                            ? null
                            : () async {
                                await _searchController.searchArtisans();
                                if (_searchController
                                    .searchResults
                                    .isNotEmpty) {
                                  Get.to(() => const SearchResultsScreen());
                                } else {
                                  Get.snackbar(
                                    'Aucun résultat',
                                    'Aucun artisan trouvé avec ces critères',
                                    backgroundColor: AppColors.warning,
                                    colorText: Colors.white,
                                    snackPosition: SnackPosition.TOP,
                                  );
                                }
                              },
                        child: _searchController.isLoading.value
                            ? const SizedBox(
                                height: 20,
                                width: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2,
                                  valueColor: AlwaysStoppedAnimation<Color>(
                                    Colors.white,
                                  ),
                                ),
                              )
                            : const Text('Rechercher'),
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
