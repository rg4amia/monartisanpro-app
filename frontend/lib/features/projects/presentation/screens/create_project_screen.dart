import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:geolocator/geolocator.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/controllers/project_controller.dart';
import '../../../../shared/controllers/search_controller.dart' as artisan_search;
import '../../../../shared/models/project_model.dart';

class CreateProjectScreen extends StatefulWidget {
  const CreateProjectScreen({super.key});

  @override
  State<CreateProjectScreen> createState() => _CreateProjectScreenState();
}

class _CreateProjectScreenState extends State<CreateProjectScreen> {
  final _formKey = GlobalKey<FormState>();
  final _titleController = TextEditingController();
  final _descriptionController = TextEditingController();
  final _addressController = TextEditingController();

  final _projectController = Get.put(ProjectController());
  final _searchController = Get.find<artisan_search.ArtisanSearchController>();

  Position? _selectedLocation;
  int? _selectedTradeId;

  @override
  void initState() {
    super.initState();
    _getCurrentLocation();
  }

  @override
  void dispose() {
    _titleController.dispose();
    _descriptionController.dispose();
    _addressController.dispose();
    super.dispose();
  }

  Future<void> _getCurrentLocation() async {
    try {
      final position = _searchController.currentPosition.value;
      if (position != null) {
        setState(() => _selectedLocation = position);
      }
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de récupérer votre position',
        backgroundColor: AppColors.error,
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
      );
    }
  }

  Future<void> _handleCreateProject() async {
    if (!_formKey.currentState!.validate()) return;

    if (_selectedLocation == null) {
      Get.snackbar(
        'Erreur',
        'Veuillez activer la localisation',
        backgroundColor: AppColors.error,
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
      );
      return;
    }

    final request = CreateProjectRequest(
      title: _titleController.text.trim(),
      description: _descriptionController.text.trim(),
      latitude: _selectedLocation!.latitude,
      longitude: _selectedLocation!.longitude,
      address: _addressController.text.trim(),
      tradeId: _selectedTradeId,
    );

    final success = await _projectController.createProject(request);

    if (success) {
      Get.snackbar(
        'Succès',
        'Projet créé avec succès!',
        backgroundColor: AppColors.success,
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
      );
      Get.back(result: true);
    } else {
      Get.snackbar(
        'Erreur',
        _projectController.errorMessage.value,
        backgroundColor: AppColors.error,
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Nouveau projet'),
      ),
      body: SafeArea(
        child: Column(
          children: [
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(Spacing.screenPadding),
                child: Form(
                  key: _formKey,
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      // Info banner
                      Container(
                        padding: const EdgeInsets.all(Spacing.base),
                        decoration: BoxDecoration(
                          color: AppColors.info.withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(Spacing.radiusMd),
                          border: Border.all(
                            color: AppColors.info.withValues(alpha: 0.3),
                          ),
                        ),
                        child: Row(
                          children: [
                            Icon(
                              Icons.info_outline,
                              color: AppColors.info,
                              size: 20,
                            ),
                            const SizedBox(width: Spacing.sm),
                            Expanded(
                              child: Text(
                                'Décrivez votre projet pour recevoir des devis',
                                style: Theme.of(context).textTheme.bodySmall,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: Spacing.xl),

                      // Title
                      TextFormField(
                        controller: _titleController,
                        textInputAction: TextInputAction.next,
                        textCapitalization: TextCapitalization.sentences,
                        decoration: const InputDecoration(
                          labelText: 'Titre du projet',
                          hintText: 'Ex: Rénovation de salle de bain',
                          prefixIcon: Icon(Icons.title_outlined),
                        ),
                        validator: (value) {
                          if (value == null || value.isEmpty) {
                            return 'Veuillez entrer un titre';
                          }
                          if (value.length < 5) {
                            return 'Le titre doit contenir au moins 5 caractères';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: Spacing.lg),

                      // Category/Trade
                      Obx(() => DropdownButtonFormField<int>(
                            value: _selectedTradeId,
                            decoration: const InputDecoration(
                              labelText: 'Type de métier (optionnel)',
                              hintText: 'Sélectionner un métier',
                              prefixIcon: Icon(Icons.work_outline),
                            ),
                            items: [
                              const DropdownMenuItem<int>(
                                value: null,
                                child: Text('Pas de préférence'),
                              ),
                              ..._searchController.trades.map((trade) {
                                return DropdownMenuItem<int>(
                                  value: trade.id,
                                  child: Text(trade.name),
                                );
                              }),
                            ],
                            onChanged: (value) {
                              setState(() => _selectedTradeId = value);
                            },
                          )),
                      const SizedBox(height: Spacing.lg),

                      // Description
                      TextFormField(
                        controller: _descriptionController,
                        maxLines: 5,
                        textCapitalization: TextCapitalization.sentences,
                        decoration: const InputDecoration(
                          labelText: 'Description détaillée',
                          hintText: 'Décrivez les travaux à réaliser...',
                          alignLabelWithHint: true,
                        ),
                        validator: (value) {
                          if (value == null || value.isEmpty) {
                            return 'Veuillez décrire votre projet';
                          }
                          if (value.length < 20) {
                            return 'La description doit contenir au moins 20 caractères';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: Spacing.lg),

                      // Address
                      TextFormField(
                        controller: _addressController,
                        textCapitalization: TextCapitalization.words,
                        decoration: InputDecoration(
                          labelText: 'Adresse du projet',
                          hintText: 'Cocody, Angré...',
                          prefixIcon: const Icon(Icons.location_on_outlined),
                          suffixIcon: IconButton(
                            icon: const Icon(Icons.my_location),
                            onPressed: _getCurrentLocation,
                            tooltip: 'Utiliser ma position',
                          ),
                        ),
                        validator: (value) {
                          if (value == null || value.isEmpty) {
                            return 'Veuillez entrer l\'adresse';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: Spacing.md),

                      // Location status
                      if (_selectedLocation != null)
                        Container(
                          padding: const EdgeInsets.all(Spacing.sm),
                          decoration: BoxDecoration(
                            color: AppColors.success.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(Spacing.radiusSm),
                          ),
                          child: Row(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              Icon(
                                Icons.check_circle,
                                color: AppColors.success,
                                size: 16,
                              ),
                              const SizedBox(width: Spacing.xs),
                              Text(
                                'Position enregistrée',
                                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                      color: AppColors.success,
                                      fontWeight: FontWeight.w600,
                                    ),
                              ),
                            ],
                          ),
                        ),
                      const SizedBox(height: Spacing.xxxl),

                      // Info text
                      Text(
                        'Après la création, les artisans pourront vous envoyer des devis.',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              color: AppColors.lightTextSecondary,
                              fontStyle: FontStyle.italic,
                            ),
                        textAlign: TextAlign.center,
                      ),
                    ],
                  ),
                ),
              ),
            ),

            // Submit button
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
              child: Obx(() => ElevatedButton(
                    onPressed: _projectController.isCreatingProject.value
                        ? null
                        : _handleCreateProject,
                    child: _projectController.isCreatingProject.value
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                            ),
                          )
                        : const Text('Créer le projet'),
                  )),
            ),
          ],
        ),
      ),
    );
  }
}
