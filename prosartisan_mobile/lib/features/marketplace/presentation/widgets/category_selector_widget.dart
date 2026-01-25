import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:prosartisan_mobile/features/marketplace/data/repositories/reference_data_repository_impl.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/sector.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/trade.dart';

class CategorySelectorWidget extends StatefulWidget {
  final Trade? selectedTrade;
  final Function(Trade) onTradeSelected;

  const CategorySelectorWidget({
    super.key,
    required this.selectedTrade,
    required this.onTradeSelected,
  });

  @override
  State<CategorySelectorWidget> createState() => _CategorySelectorWidgetState();
}

class _CategorySelectorWidgetState extends State<CategorySelectorWidget> {
  List<Sector> _sectors = [];
  bool _isLoading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _fetchSectors();
  }

  Future<void> _fetchSectors() async {
    // TODO: Use DI/GetIt for repository
    final repository = ReferenceDataRepositoryImpl(client: Dio());

    try {
      final sectors = await repository.getSectors();
      if (mounted) {
        setState(() {
          _sectors = sectors;
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _error = e.toString();
          _isLoading = false;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_isLoading) {
      return const Center(child: CircularProgressIndicator());
    }

    if (_error != null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(8.0),
          child: Text(
            'Erreur de chargement: $_error',
            style: const TextStyle(color: Colors.red),
          ),
        ),
      );
    }

    return ListView.builder(
      shrinkWrap: true,
      physics: const NeverScrollableScrollPhysics(),
      itemCount: _sectors.length,
      itemBuilder: (context, index) {
        final sector = _sectors[index];
        // Check if a trade in this sector is selected to expand initially?
        // For now, just standard ExpansionTile.
        return ExpansionTile(
          title: Text(
            sector.name,
            style: const TextStyle(fontWeight: FontWeight.bold),
          ),
          children: sector.trades.map((trade) {
            final isSelected = widget.selectedTrade?.id == trade.id;
            return ListTile(
              title: Text(trade.name),
              leading: Icon(_getIconForSector(sector.name), size: 20),
              selected: isSelected,
              selectedTileColor: Colors.blue.withOpacity(0.1),
              trailing: isSelected
                  ? const Icon(Icons.check_circle, color: Colors.blue)
                  : null,
              onTap: () => widget.onTradeSelected(trade),
            );
          }).toList(),
        );
      },
    );
  }

  IconData _getIconForSector(String sectorName) {
    // Simple mapping for visuals
    if (sectorName.contains('MÉCANIQUE')) return Icons.car_repair;
    if (sectorName.contains('ÉLECTRICITÉ')) return Icons.electrical_services;
    if (sectorName.contains('PLOMBERIE')) return Icons.plumbing;
    if (sectorName.contains('BÂTIMENT')) return Icons.construction;
    if (sectorName.contains('MENUISERIE')) return Icons.carpenter;
    if (sectorName.contains('SOUDURE')) return Icons.local_fire_department;
    if (sectorName.contains('NUMÉRIQUE')) return Icons.computer;
    return Icons.work;
  }
}
