import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../shared/models/trade.dart';
import '../../../../shared/widgets/trade_selector_widget.dart';
import '../../../../shared/controllers/reference_data_controller.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_typography.dart';

class TradeSelectionDemoPage extends StatefulWidget {
  const TradeSelectionDemoPage({Key? key}) : super(key: key);

  @override
  State<TradeSelectionDemoPage> createState() => _TradeSelectionDemoPageState();
}

class _TradeSelectionDemoPageState extends State<TradeSelectionDemoPage> {
  final ReferenceDataController _controller = Get.put(
    ReferenceDataController(),
  );
  Trade? _selectedTrade;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Sélection de Métier'),
        backgroundColor: AppColors.accentPrimary,
        foregroundColor: Colors.white,
      ),
      body: Padding(
        padding: const EdgeInsets.all(16.0),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Métier sélectionné
            if (_selectedTrade != null) ...[
              Card(
                color: AppColors.accentPrimary.withOpacity(0.1),
                child: Padding(
                  padding: const EdgeInsets.all(16.0),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Métier sélectionné',
                        style: AppTypography.body.copyWith(
                          color: AppColors.textPrimary,
                        ),
                      ),
                      const SizedBox(height: AppSpacing.sm),
                      Text(
                        _selectedTrade!.name,
                        style: AppTypography.h4.copyWith(
                          color: AppColors.textPrimary,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      Text(
                        'Code: ${_selectedTrade!.code}',
                        style: AppTypography.bodySmall.copyWith(
                          color: AppColors.textSecondary,
                        ),
                      ),
                      Obx(() {
                        final sector = _controller.getSectorById(
                          _selectedTrade!.sectorId,
                        );
                        return Text(
                          'Secteur: ${sector?.name ?? 'Inconnu'}',
                          style: AppTypography.bodySmall.copyWith(
                            color: AppColors.textSecondary,
                          ),
                        );
                      }),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: AppSpacing.base),
            ],

            // Sélecteur de métier
            Expanded(
              child: TradeSelectorWidget(
                onTradeSelected: (trade) {
                  setState(() {
                    _selectedTrade = trade;
                  });
                  Get.snackbar(
                    'Métier sélectionné',
                    trade.name,
                    snackPosition: SnackPosition.BOTTOM,
                    backgroundColor: AppColors.accentPrimary,
                    colorText: Colors.white,
                  );
                },
                selectedTrade: _selectedTrade,
                hintText: 'Rechercher votre métier...',
              ),
            ),

            // Boutons d'action
            if (_selectedTrade != null) ...[
              const SizedBox(height: AppSpacing.base),
              Row(
                children: [
                  Expanded(
                    child: ElevatedButton(
                      onPressed: () {
                        // Action de validation
                        Get.dialog(
                          AlertDialog(
                            title: const Text('Confirmation'),
                            content: Text(
                              'Vous avez sélectionné le métier "${_selectedTrade!.name}". '
                              'Voulez-vous continuer ?',
                            ),
                            actions: [
                              TextButton(
                                onPressed: () => Get.back(),
                                child: const Text('Annuler'),
                              ),
                              ElevatedButton(
                                onPressed: () {
                                  Get.back();
                                  Get.snackbar(
                                    'Succès',
                                    'Métier "${_selectedTrade!.name}" confirmé !',
                                    snackPosition: SnackPosition.BOTTOM,
                                    backgroundColor: AppColors.accentSuccess,
                                    colorText: Colors.white,
                                  );
                                },
                                child: const Text('Confirmer'),
                              ),
                            ],
                          ),
                        );
                      },
                      child: const Text('Confirmer la sélection'),
                    ),
                  ),
                  const SizedBox(width: AppSpacing.sm),
                  OutlinedButton(
                    onPressed: () {
                      setState(() {
                        _selectedTrade = null;
                      });
                    },
                    child: const Text('Effacer'),
                  ),
                ],
              ),
            ],
          ],
        ),
      ),
    );
  }
}
