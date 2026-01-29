import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../models/trade.dart';
import '../models/sector.dart';
import '../controllers/reference_data_controller.dart';
import '../../core/theme/app_theme.dart';
import '../../core/theme/app_spacing.dart';

class TradeSelectorWidget extends StatefulWidget {
  final Function(Trade) onTradeSelected;
  final Trade? selectedTrade;
  final bool showSectorFilter;
  final String? hintText;

  const TradeSelectorWidget({
    Key? key,
    required this.onTradeSelected,
    this.selectedTrade,
    this.showSectorFilter = true,
    this.hintText,
  }) : super(key: key);

  @override
  State<TradeSelectorWidget> createState() => _TradeSelectorWidgetState();
}

class _TradeSelectorWidgetState extends State<TradeSelectorWidget> {
  final ReferenceDataController _controller =
      Get.find<ReferenceDataController>();
  final TextEditingController _searchController = TextEditingController();
  Sector? _selectedSector;

  @override
  void initState() {
    super.initState();
    _searchController.addListener(_onSearchChanged);
  }

  @override
  void dispose() {
    _searchController.removeListener(_onSearchChanged);
    _searchController.dispose();
    super.dispose();
  }

  void _onSearchChanged() {
    _controller.searchTrades(_searchController.text);
  }

  void _onSectorChanged(Sector? sector) {
    setState(() {
      _selectedSector = sector;
    });
    _controller.filterTradesBySector(sector?.id);
    _searchController.clear();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // Filtre par secteur
        if (widget.showSectorFilter) ...[
          Text('Secteur d\'activité', style: AppTheme.textTheme.titleMedium),
          AppSpacing.verticalSpaceSmall,
          Obx(
            () => DropdownButtonFormField<Sector>(
              value: _selectedSector,
              decoration: InputDecoration(
                hintText: 'Sélectionner un secteur',
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(8),
                ),
              ),
              items: [
                const DropdownMenuItem<Sector>(
                  value: null,
                  child: Text('Tous les secteurs'),
                ),
                ..._controller.sectors.map(
                  (sector) => DropdownMenuItem<Sector>(
                    value: sector,
                    child: Text(sector.name),
                  ),
                ),
              ],
              onChanged: _onSectorChanged,
            ),
          ),
          AppSpacing.verticalSpaceMedium,
        ],

        // Barre de recherche
        Text('Rechercher un métier', style: AppTheme.textTheme.titleMedium),
        AppSpacing.verticalSpaceSmall,
        TextField(
          controller: _searchController,
          decoration: InputDecoration(
            hintText: widget.hintText ?? 'Tapez le nom du métier...',
            prefixIcon: const Icon(Icons.search),
            suffixIcon: _searchController.text.isNotEmpty
                ? IconButton(
                    icon: const Icon(Icons.clear),
                    onPressed: () {
                      _searchController.clear();
                    },
                  )
                : null,
            border: OutlineInputBorder(borderRadius: BorderRadius.circular(8)),
          ),
        ),
        AppSpacing.verticalSpaceMedium,

        // Liste des métiers
        Text('Métiers disponibles', style: AppTheme.textTheme.titleMedium),
        AppSpacing.verticalSpaceSmall,
        Expanded(
          child: Obx(() {
            if (_controller.isLoadingSectors || _controller.isLoadingTrades) {
              return const Center(child: CircularProgressIndicator());
            }

            if (_controller.hasError) {
              return Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(
                      Icons.error_outline,
                      size: 64,
                      color: AppTheme.colorScheme.error,
                    ),
                    AppSpacing.verticalSpaceMedium,
                    Text(
                      'Erreur de chargement',
                      style: AppTheme.textTheme.titleLarge,
                    ),
                    AppSpacing.verticalSpaceSmall,
                    Text(
                      _controller.errorMessage,
                      textAlign: TextAlign.center,
                      style: AppTheme.textTheme.bodyMedium,
                    ),
                    AppSpacing.verticalSpaceMedium,
                    ElevatedButton(
                      onPressed: _controller.refresh,
                      child: const Text('Réessayer'),
                    ),
                  ],
                ),
              );
            }

            if (_controller.filteredTrades.isEmpty) {
              return Center(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(
                      Icons.search_off,
                      size: 64,
                      color: AppTheme.colorScheme.onSurfaceVariant,
                    ),
                    AppSpacing.verticalSpaceMedium,
                    Text(
                      'Aucun métier trouvé',
                      style: AppTheme.textTheme.titleLarge,
                    ),
                    AppSpacing.verticalSpaceSmall,
                    Text(
                      'Essayez de modifier votre recherche ou votre filtre',
                      textAlign: TextAlign.center,
                      style: AppTheme.textTheme.bodyMedium,
                    ),
                  ],
                ),
              );
            }

            return ListView.builder(
              itemCount: _controller.filteredTrades.length,
              itemBuilder: (context, index) {
                final trade = _controller.filteredTrades[index];
                final sector = _controller.getSectorById(trade.sectorId);
                final isSelected = widget.selectedTrade?.id == trade.id;

                return Card(
                  margin: const EdgeInsets.only(bottom: 8),
                  child: ListTile(
                    title: Text(
                      trade.name,
                      style: AppTheme.textTheme.titleMedium?.copyWith(
                        fontWeight: isSelected
                            ? FontWeight.bold
                            : FontWeight.normal,
                      ),
                    ),
                    subtitle: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text('Code: ${trade.code}'),
                        if (sector != null) Text('Secteur: ${sector.name}'),
                      ],
                    ),
                    trailing: isSelected
                        ? Icon(
                            Icons.check_circle,
                            color: AppTheme.colorScheme.primary,
                          )
                        : const Icon(Icons.arrow_forward_ios),
                    selected: isSelected,
                    onTap: () => widget.onTradeSelected(trade),
                  ),
                );
              },
            );
          }),
        ),
      ],
    );
  }
}
