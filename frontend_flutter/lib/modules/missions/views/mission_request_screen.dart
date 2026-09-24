import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/storage/storage_service.dart';
import '../../../core/utils/bypass_validator.dart';
import '../../../data/models/address_model.dart';
import '../../../data/repositories/mission_repository.dart';
import '../../services/models/intervention_type_model.dart';
import '../../services/models/sector_model.dart';
import '../../services/models/trade_model.dart';
import '../controllers/missions_controller.dart';
import '../widgets/mission_request/gemini_estimate_card.dart';
import '../widgets/mission_request/intervention_options.dart';
import '../widgets/mission_request/mission_media_picker.dart';
import '../widgets/mission_request/mission_request_colors.dart';
import '../widgets/mission_request/mission_request_sections.dart';
import '../widgets/mission_request/quick_category_bottom_sheet.dart';

class MissionRequestScreen extends StatefulWidget {
  const MissionRequestScreen({super.key});

  @override
  State<MissionRequestScreen> createState() => _MissionRequestScreenState();
}

class _MissionRequestScreenState extends State<MissionRequestScreen> {
  // Repli si le GPS est inaccessible (refusé, coupé, ou délai dépassé) —
  // jamais (0.0, 0.0), qui pointe au large du golfe de Guinée et serait
  // envoyé au backend comme si c'était une position réelle (Règle d'or 26/29).
  static const double _kAbidjanFallbackLat = 5.3543;
  static const double _kAbidjanFallbackLng = -4.0083;

  final MissionsController _missionsController = Get.find<MissionsController>();
  final _descCtrl = TextEditingController();
  final _selectedCategory = ''.obs;
  final _selectedCategoryId = 0.obs;
  final _selectedTradeId = 0.obs;
  final _location = 'Localisation en cours...'.obs;
  final _locationDetail = ''.obs;
  final _latitude = 0.0.obs;
  final _longitude = 0.0.obs;
  final _selectedAddressId = Rxn<int>();
  final _selectedAddressLabel = Rxn<String>();
  final _isLocating = true.obs;
  final _nightIntervention = false.obs;
  final _photos = <XFile>[].obs;
  final _video = Rx<XFile?>(null);

  final _interventionTypes = <InterventionTypeModel>[].obs;
  final _selectedInterventionTypeId = Rxn<int>();
  final _isLoadingInterventionTypes = false.obs;
  final MissionRepository _missionRepository = MissionRepository();

  @override
  void initState() {
    super.initState();
    _loadInterventionTypes();
    _autoDetectLocation();

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

  /// Récupère la position GPS réelle du client dès l'ouverture de l'écran,
  /// pour que la mission soit géolocalisée même si le client ne touche
  /// jamais « Changer ». L'appel est borné (Règle d'or 26) : sans `timeLimit`,
  /// un terminal qui ne fixe aucun point en haute précision laisserait
  /// l'écran indéfiniment sur « Localisation en cours... ».
  Future<void> _autoDetectLocation() async {
    try {
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        throw Exception('Permission de localisation refusée');
      }

      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 5),
        ),
      );

      if (!mounted) return;
      _selectedAddressId.value = null;
      _selectedAddressLabel.value = null;
      _latitude.value = position.latitude;
      _longitude.value = position.longitude;
      _location.value = 'Position actuelle détectée';
      _locationDetail.value =
          'Lat: ${position.latitude.toStringAsFixed(4)}, Lng: ${position.longitude.toStringAsFixed(4)}';
    } catch (_) {
      if (!mounted) return;
      _selectedAddressId.value = null;
      _selectedAddressLabel.value = null;
      _latitude.value = _kAbidjanFallbackLat;
      _longitude.value = _kAbidjanFallbackLng;
      _location.value = 'Abidjan, Côte d\'Ivoire';
      _locationDetail.value = 'Position par défaut — précisez via « Changer »';
    } finally {
      if (mounted) _isLocating.value = false;
    }
  }

  Future<void> _showLocationOptionsSheet() async {
    await showModalBottomSheet<void>(
      context: context,
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      backgroundColor: Colors.white,
      builder: (ctx) {
        return SafeArea(
          child: Padding(
            padding: const EdgeInsets.symmetric(vertical: 20, horizontal: 16),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Center(
                  child: Container(
                    width: 40,
                    height: 4,
                    decoration: BoxDecoration(
                      color: Colors.grey.shade300,
                      borderRadius: BorderRadius.circular(2),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                const Text(
                  'Choisir l\'emplacement du chantier',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: MissionRequestColors.ink,
                  ),
                ),
                const SizedBox(height: 16),
                ListTile(
                  leading: Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: MissionRequestColors.primaryLight,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: const Icon(
                      Icons.my_location,
                      color: MissionRequestColors.primary,
                    ),
                  ),
                  title: const Text(
                    'Ma position GPS actuelle',
                    style: TextStyle(fontWeight: FontWeight.w600),
                  ),
                  subtitle: const Text(
                    'Détecter automatiquement via le capteur GPS',
                  ),
                  onTap: () {
                    Navigator.pop(ctx);
                    _autoDetectLocation();
                  },
                ),
                const Divider(),
                ListTile(
                  leading: Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: const Color(0xFF24734F).withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: const Icon(
                      Icons.bookmark_border_rounded,
                      color: Color(0xFF24734F),
                    ),
                  ),
                  title: const Text(
                    'Mon carnet d\'adresses',
                    style: TextStyle(fontWeight: FontWeight.w600),
                  ),
                  subtitle: const Text(
                    'Sélectionner une adresse enregistrée (maison, bureau, etc.)',
                  ),
                  onTap: () async {
                    Navigator.pop(ctx);
                    final result = await Get.toNamed(Routes.addressList);
                    if (result is AddressModel) {
                      _selectedAddressId.value = result.id;
                      _selectedAddressLabel.value = result.shortLabel;
                      _location.value = result.addressLine;
                      final cityPart =
                          result.city.isNotEmpty ? '${result.city} • ' : '';
                      final latPart = result.lat != null && result.lat != 0.0
                          ? 'Lat: ${result.lat!.toStringAsFixed(4)}, Lng: ${result.lng?.toStringAsFixed(4)}'
                          : 'Adresse enregistrée';
                      _locationDetail.value = '$cityPart$latPart';
                      if (result.lat != null && result.lat != 0.0) {
                        _latitude.value = result.lat!;
                        _longitude.value = result.lng ?? _kAbidjanFallbackLng;
                      }
                    }
                  },
                ),
                const Divider(),
                ListTile(
                  leading: Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: Colors.blue.shade50,
                      borderRadius: BorderRadius.circular(10),
                    ),
                    child: Icon(Icons.map_outlined, color: Colors.blue.shade700),
                  ),
                  title: const Text(
                    'Sélectionner sur la carte',
                    style: TextStyle(fontWeight: FontWeight.w600),
                  ),
                  subtitle: const Text(
                    'Pointer manuellement l\'emplacement précis',
                  ),
                  onTap: () async {
                    Navigator.pop(ctx);
                    final result = await Get.toNamed(Routes.locationPicker);
                    if (result != null && result is Map) {
                      _selectedAddressId.value = null;
                      _selectedAddressLabel.value = null;
                      _latitude.value = result['latitude'] ?? 0.0;
                      _longitude.value = result['longitude'] ?? 0.0;
                      _location.value =
                          result['address'] ?? 'Emplacement sélectionné';
                      _locationDetail.value =
                          'Lat: ${_latitude.value.toStringAsFixed(4)}, Lng: ${_longitude.value.toStringAsFixed(4)}';
                    }
                  },
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Future<void> _loadInterventionTypes() async {
    _isLoadingInterventionTypes.value = true;
    try {
      final types = await _missionRepository.getInterventionTypes();
      _interventionTypes.value = types;
    } catch (_) {
      // Silencieux : le backend applique un type par défaut si absent.
    } finally {
      _isLoadingInterventionTypes.value = false;
    }
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
    final video = await picker.pickVideo(
      source: ImageSource.gallery,
      maxDuration: const Duration(seconds: 30),
    );
    if (video != null) {
      final size = await video.length();
      if (size > 25 * 1024 * 1024) {
        Get.snackbar(
          'Vidéo trop volumineuse',
          'La vidéo (${(size / (1024 * 1024)).toStringAsFixed(1)} Mo) dépasse la limite autorisée de 25 Mo. Veuillez choisir une vidéo plus courte (≤ 30s).',
          snackPosition: SnackPosition.TOP,
          backgroundColor: const Color(0xFFC55E50),
          colorText: Colors.white,
        );
        return;
      }
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
      builder: (ctx) => QuickCategoryBottomSheet(
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
      backgroundColor: MissionRequestColors.bg,
      body: SafeArea(
        child: Column(
          children: [
            MissionRequestAppBar(),
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    MissionRequestSectionTitle(title: 'Catégorie de service'),
                    const SizedBox(height: 12),
                    Obx(
                      () => _selectedCategory.value.isEmpty
                          ? SelectServiceButton(
                              onTap: _openCategorySelector,
                            )
                          : SelectedServiceCard(
                              category: _selectedCategory.value,
                              onChangeTap: _openCategorySelector,
                            ),
                    ),
                    const SizedBox(height: 24),
                    MissionRequestSectionTitle(title: 'Détails de la mission'),
                    const SizedBox(height: 8),
                    const Text(
                      'Décrivez votre problème',
                      style: TextStyle(
                        fontSize: 13,
                        color: MissionRequestColors.muted,
                      ),
                    ),
                    const SizedBox(height: 12),
                    MissionDescriptionField(controller: _descCtrl),
                    const SizedBox(height: 16),
                    Obx(
                      () => GeminiEstimateCard(
                        isLoading: _missionsController.isEstimating.value,
                        estimate: _missionsController.estimateResult.value,
                        onAnalyze: _runGeminiEstimate,
                      ),
                    ),
                    const SizedBox(height: 24),
                    MissionRequestSectionTitle(
                      title: 'Options d\'intervention',
                    ),
                    const SizedBox(height: 12),
                    Obx(
                      () => InterventionTypeSelector(
                        types: _interventionTypes,
                        isLoading: _isLoadingInterventionTypes.value,
                        selectedId: _selectedInterventionTypeId.value,
                        onSelected: (id) =>
                            _selectedInterventionTypeId.value = id,
                      ),
                    ),
                    const SizedBox(height: 12),
                    Obx(
                      () => NightInterventionCard(
                        enabled: _nightIntervention.value,
                        onChanged: (value) => _nightIntervention.value = value,
                      ),
                    ),
                    const SizedBox(height: 24),
                    MissionRequestSectionTitle(
                      title: 'Visuels (Photos ou Vidéos)',
                    ),
                    const SizedBox(height: 12),
                    MissionMediaPicker(
                      photos: _photos,
                      video: _video,
                      onPickImage: _pickImage,
                      onPickVideo: _pickVideo,
                    ),
                    const SizedBox(height: 24),
                    MissionRequestSectionTitle(title: 'Votre emplacement'),
                    const SizedBox(height: 12),
                    Obx(
                      () => MissionLocationCard(
                        location: _location.value,
                        detail: _locationDetail.value,
                        addressLabel: _selectedAddressLabel.value,
                        onChangeTap: _showLocationOptionsSheet,
                      ),
                    ),
                    const SizedBox(height: 32),
                    MissionRequestSearchButton(
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
                        if (_selectedInterventionTypeId.value == null) {
                          Get.snackbar(
                            'Erreur',
                            'Veuillez sélectionner le type d\'intervention souhaité',
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
                            'interventionTypeId':
                                _selectedInterventionTypeId.value,
                            'description': _descCtrl.text,
                            'locationAddress': _location.value,
                            'locationDetail': _locationDetail.value,
                            'latitude': _latitude.value,
                            'longitude': _longitude.value,
                            'addressId': _selectedAddressId.value,
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
