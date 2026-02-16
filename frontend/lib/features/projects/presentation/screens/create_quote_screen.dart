import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/controllers/project_controller.dart';
import '../../../../shared/models/project_model.dart';

class CreateQuoteScreen extends StatefulWidget {
  final int projectId;

  const CreateQuoteScreen({super.key, required this.projectId});

  @override
  State<CreateQuoteScreen> createState() => _CreateQuoteScreenState();
}

class _CreateQuoteScreenState extends State<CreateQuoteScreen> {
  final _projectController = Get.find<ProjectController>();
  final _formKey = GlobalKey<FormState>();
  final _notesController = TextEditingController();

  final List<QuoteItemInput> _materialItems = [];
  final List<QuoteItemInput> _laborItems = [];

  int _validDays = 7;

  @override
  void initState() {
    super.initState();
    _addMaterialItem();
    _addLaborItem();
  }

  @override
  void dispose() {
    _notesController.dispose();
    for (var item in _materialItems) {
      item.dispose();
    }
    for (var item in _laborItems) {
      item.dispose();
    }
    super.dispose();
  }

  void _addMaterialItem() {
    setState(() {
      _materialItems.add(QuoteItemInput());
    });
  }

  void _removeMaterialItem(int index) {
    setState(() {
      _materialItems[index].dispose();
      _materialItems.removeAt(index);
    });
  }

  void _addLaborItem() {
    setState(() {
      _laborItems.add(QuoteItemInput());
    });
  }

  void _removeLaborItem(int index) {
    setState(() {
      _laborItems[index].dispose();
      _laborItems.removeAt(index);
    });
  }

  double get _totalMaterial {
    return _materialItems.fold(0.0, (sum, item) => sum + item.total);
  }

  double get _totalLabor {
    return _laborItems.fold(0.0, (sum, item) => sum + item.total);
  }

  double get _grandTotal {
    return _totalMaterial + _totalLabor;
  }

  double get _materialPercentage {
    if (_grandTotal == 0) return 0;
    return (_totalMaterial / _grandTotal) * 100;
  }

  double get _laborPercentage {
    if (_grandTotal == 0) return 0;
    return (_totalLabor / _grandTotal) * 100;
  }

  Future<void> _submitQuote() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    if (_materialItems.isEmpty && _laborItems.isEmpty) {
      Get.snackbar(
        'Erreur',
        'Veuillez ajouter au moins un élément',
        backgroundColor: AppColors.error,
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
      );
      return;
    }

    final materials = _materialItems
        .where((item) => item.description.text.isNotEmpty)
        .map((item) => QuoteItemRequest(
              description: item.description.text,
              quantity: double.tryParse(item.quantity.text) ?? 0,
              unit: item.unit.text.isNotEmpty ? item.unit.text : null,
              unitPrice: double.tryParse(item.unitPrice.text) ?? 0,
            ))
        .toList();

    final labor = _laborItems
        .where((item) => item.description.text.isNotEmpty)
        .map((item) => QuoteItemRequest(
              description: item.description.text,
              quantity: double.tryParse(item.quantity.text) ?? 0,
              unit: item.unit.text.isNotEmpty ? item.unit.text : null,
              unitPrice: double.tryParse(item.unitPrice.text) ?? 0,
            ))
        .toList();

    final request = CreateQuoteRequest(
      projectId: widget.projectId,
      materials: materials,
      labor: labor,
      validDays: _validDays,
      notes: _notesController.text.isNotEmpty ? _notesController.text : null,
    );

    final success = await _projectController.createQuote(request);
    if (success) {
      Get.back(result: true);
      Get.snackbar(
        'Succès',
        'Votre devis a été envoyé au client',
        backgroundColor: AppColors.success,
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Créer un devis'),
        actions: [
          TextButton(
            onPressed: _submitQuote,
            child: Text(
              'ENVOYER',
              style: TextStyle(
                color: Theme.of(context).colorScheme.onPrimary,
                fontWeight: FontWeight.bold,
              ),
            ),
          ),
        ],
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(Spacing.screenPadding),
          children: [
            // Material Section
            _buildSectionHeader(
              context,
              'Matériaux',
              Icons.inventory_2_outlined,
              onAdd: _addMaterialItem,
            ),
            const SizedBox(height: Spacing.md),
            ..._materialItems.asMap().entries.map((entry) {
              return Padding(
                padding: const EdgeInsets.only(bottom: Spacing.md),
                child: _buildItemCard(
                  context,
                  entry.value,
                  entry.key,
                  onRemove: _materialItems.length > 1
                      ? () => _removeMaterialItem(entry.key)
                      : null,
                ),
              );
            }),

            const SizedBox(height: Spacing.xl),

            // Labor Section
            _buildSectionHeader(
              context,
              'Main d\'œuvre',
              Icons.construction_outlined,
              onAdd: _addLaborItem,
            ),
            const SizedBox(height: Spacing.md),
            ..._laborItems.asMap().entries.map((entry) {
              return Padding(
                padding: const EdgeInsets.only(bottom: Spacing.md),
                child: _buildItemCard(
                  context,
                  entry.value,
                  entry.key,
                  onRemove: _laborItems.length > 1
                      ? () => _removeLaborItem(entry.key)
                      : null,
                ),
              );
            }),

            const SizedBox(height: Spacing.xl),

            // Summary Card
            Container(
              padding: const EdgeInsets.all(Spacing.base),
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  colors: AppColors.primaryGradient,
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                ),
                borderRadius: BorderRadius.circular(Spacing.radiusMd),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Récapitulatif',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          color: Colors.white,
                          fontWeight: FontWeight.bold,
                        ),
                  ),
                  const SizedBox(height: Spacing.md),
                  _buildSummaryRow('Matériaux', _totalMaterial, _materialPercentage),
                  const SizedBox(height: Spacing.sm),
                  _buildSummaryRow('Main d\'œuvre', _totalLabor, _laborPercentage),
                  const Divider(color: Colors.white54, height: Spacing.lg),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      Text(
                        'TOTAL',
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                              color: Colors.white,
                              fontWeight: FontWeight.bold,
                            ),
                      ),
                      Text(
                        '${_grandTotal.toStringAsFixed(0)} FCFA',
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                              color: Colors.white,
                              fontWeight: FontWeight.bold,
                            ),
                      ),
                    ],
                  ),
                ],
              ),
            ),

            const SizedBox(height: Spacing.xl),

            // Validity Period
            Text(
              'Validité du devis',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
            ),
            const SizedBox(height: Spacing.md),
            Container(
              padding: const EdgeInsets.all(Spacing.base),
              decoration: BoxDecoration(
                color: AppColors.lightBackground,
                borderRadius: BorderRadius.circular(Spacing.radiusMd),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Text(
                      'Le devis sera valide pendant:',
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                  ),
                  DropdownButton<int>(
                    value: _validDays,
                    items: [3, 7, 14, 30].map((days) {
                      return DropdownMenuItem(
                        value: days,
                        child: Text('$days jours'),
                      );
                    }).toList(),
                    onChanged: (value) {
                      setState(() => _validDays = value!);
                    },
                  ),
                ],
              ),
            ),

            const SizedBox(height: Spacing.xl),

            // Notes
            Text(
              'Notes (optionnel)',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.bold,
                  ),
            ),
            const SizedBox(height: Spacing.md),
            TextFormField(
              controller: _notesController,
              maxLines: 4,
              decoration: InputDecoration(
                hintText: 'Informations complémentaires, conditions particulières...',
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(Spacing.radiusMd),
                ),
              ),
            ),

            const SizedBox(height: Spacing.xxxl),
          ],
        ),
      ),
    );
  }

  Widget _buildSectionHeader(
    BuildContext context,
    String title,
    IconData icon, {
    required VoidCallback onAdd,
  }) {
    return Row(
      children: [
        Icon(icon, size: 20, color: Theme.of(context).colorScheme.primary),
        const SizedBox(width: Spacing.sm),
        Text(
          title,
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.bold,
              ),
        ),
        const Spacer(),
        IconButton(
          icon: const Icon(Icons.add_circle),
          color: Theme.of(context).colorScheme.primary,
          onPressed: onAdd,
        ),
      ],
    );
  }

  Widget _buildItemCard(
    BuildContext context,
    QuoteItemInput item,
    int index, {
    VoidCallback? onRemove,
  }) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(Spacing.base),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Text(
                  'Article ${index + 1}',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.lightTextSecondary,
                      ),
                ),
                const Spacer(),
                if (onRemove != null)
                  IconButton(
                    icon: const Icon(Icons.delete_outline),
                    color: AppColors.error,
                    onPressed: onRemove,
                    constraints: const BoxConstraints(),
                    padding: EdgeInsets.zero,
                  ),
              ],
            ),
            const SizedBox(height: Spacing.md),
            TextFormField(
              controller: item.description,
              decoration: InputDecoration(
                labelText: 'Description *',
                hintText: 'Ex: Ciment, Peinture, Pose carrelage...',
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(Spacing.radiusMd),
                ),
              ),
              validator: (value) {
                if (value == null || value.isEmpty) {
                  return 'Veuillez entrer une description';
                }
                return null;
              },
            ),
            const SizedBox(height: Spacing.md),
            Row(
              children: [
                Expanded(
                  flex: 2,
                  child: TextFormField(
                    controller: item.quantity,
                    keyboardType: TextInputType.number,
                    decoration: InputDecoration(
                      labelText: 'Quantité *',
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(Spacing.radiusMd),
                      ),
                    ),
                    validator: (value) {
                      if (value == null || value.isEmpty) {
                        return 'Requis';
                      }
                      if (double.tryParse(value) == null) {
                        return 'Nombre invalide';
                      }
                      return null;
                    },
                    onChanged: (_) => setState(() {}),
                  ),
                ),
                const SizedBox(width: Spacing.md),
                Expanded(
                  child: TextFormField(
                    controller: item.unit,
                    decoration: InputDecoration(
                      labelText: 'Unité',
                      hintText: 'kg, m²...',
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(Spacing.radiusMd),
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: Spacing.md),
                Expanded(
                  flex: 2,
                  child: TextFormField(
                    controller: item.unitPrice,
                    keyboardType: TextInputType.number,
                    decoration: InputDecoration(
                      labelText: 'Prix unitaire *',
                      suffix: const Text('FCFA'),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(Spacing.radiusMd),
                      ),
                    ),
                    validator: (value) {
                      if (value == null || value.isEmpty) {
                        return 'Requis';
                      }
                      if (double.tryParse(value) == null) {
                        return 'Nombre invalide';
                      }
                      return null;
                    },
                    onChanged: (_) => setState(() {}),
                  ),
                ),
              ],
            ),
            if (item.total > 0) ...[
              const SizedBox(height: Spacing.md),
              Container(
                padding: const EdgeInsets.all(Spacing.sm),
                decoration: BoxDecoration(
                  color: AppColors.lightBackground,
                  borderRadius: BorderRadius.circular(Spacing.radiusSm),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    Text(
                      'Total: ',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                    Text(
                      '${item.total.toStringAsFixed(0)} FCFA',
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                            fontWeight: FontWeight.bold,
                            color: Theme.of(context).colorScheme.primary,
                          ),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildSummaryRow(String label, double amount, double percentage) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                color: Colors.white,
              ),
        ),
        Row(
          children: [
            Text(
              '${amount.toStringAsFixed(0)} FCFA',
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.w600,
                  ),
            ),
            const SizedBox(width: Spacing.sm),
            Container(
              padding: const EdgeInsets.symmetric(
                horizontal: Spacing.sm,
                vertical: Spacing.xs,
              ),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.2),
                borderRadius: BorderRadius.circular(Spacing.radiusSm),
              ),
              child: Text(
                '${percentage.toStringAsFixed(0)}%',
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.bold,
                  color: Colors.white,
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }
}

class QuoteItemInput {
  final TextEditingController description = TextEditingController();
  final TextEditingController quantity = TextEditingController();
  final TextEditingController unit = TextEditingController();
  final TextEditingController unitPrice = TextEditingController();

  double get total {
    final qty = double.tryParse(quantity.text) ?? 0;
    final price = double.tryParse(unitPrice.text) ?? 0;
    return qty * price;
  }

  void dispose() {
    description.dispose();
    quantity.dispose();
    unit.dispose();
    unitPrice.dispose();
  }
}
