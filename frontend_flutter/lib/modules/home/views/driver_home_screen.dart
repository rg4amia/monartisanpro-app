import 'dart:async';

import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../shared/widgets/broadcast_media_section.dart';
import '../../../shared/widgets/communication_banner.dart';
import '../controllers/home_controller.dart';
import '../widgets/driver_home/driver_delivery_cards.dart';
import '../widgets/driver_home/driver_header.dart';
import '../widgets/driver_home/driver_overview_cards.dart';

class DriverHomeScreen extends StatefulWidget {
  const DriverHomeScreen({super.key});

  @override
  State<DriverHomeScreen> createState() => _DriverHomeScreenState();
}

class _DriverHomeScreenState extends State<DriverHomeScreen> {
  final controller = Get.find<HomeController>();
  String _activeTab = 'overview'; // 'overview', 'requests', 'vehicle'

  // Controllers for vehicle configuration form
  final _plateCtrl = TextEditingController();
  final _basePriceCtrl = TextEditingController();
  final _priceKmCtrl = TextEditingController();
  final _addressCtrl = TextEditingController();
  final _gpsCtrl = TextEditingController();
  String _selectedVehicle = 'Moto';
  bool _loadingGeo = false;

  @override
  void initState() {
    super.initState();
    _plateCtrl.text = controller.driverPlate.value;
    _addressCtrl.text = controller.driverAddress.value;
    _selectedVehicle = controller.driverVehicle.value;
    _gpsCtrl.text = controller.driverGpsCoords.value;
    _updatePricingForVehicle(_selectedVehicle);
  }

  void _updatePricingForVehicle(String vehicle) {
    if (vehicle == 'Moto') {
      _basePriceCtrl.text = '1000';
      _priceKmCtrl.text = '200';
    } else if (vehicle == 'Tricycle') {
      _basePriceCtrl.text = '1500';
      _priceKmCtrl.text = '300';
    } else if (vehicle == 'Camionnette') {
      _basePriceCtrl.text = '2500';
      _priceKmCtrl.text = '500';
    }
  }

  @override
  void dispose() {
    _plateCtrl.dispose();
    _basePriceCtrl.dispose();
    _priceKmCtrl.dispose();
    _addressCtrl.dispose();
    _gpsCtrl.dispose();
    super.dispose();
  }

  Future<void> _handleGeoLocation() async {
    setState(() => _loadingGeo = true);
    try {
      final perm = await Geolocator.checkPermission();
      if (perm == LocationPermission.denied) {
        await Geolocator.requestPermission();
      }
      final pos = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.high,
          timeLimit: Duration(seconds: 5),
        ),
      );
      setState(() {
        _gpsCtrl.text =
            '${pos.latitude.toStringAsFixed(6)}, ${pos.longitude.toStringAsFixed(6)}';
      });
      Get.snackbar(
        'GPS synchronisé',
        'Coordonnées GPS mises à jour.',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: AppColors.success,
        colorText: Colors.white,
      );
    } catch (_) {
      Get.snackbar(
        'Erreur GPS',
        'Impossible de récupérer votre position.',
        snackPosition: SnackPosition.BOTTOM,
        backgroundColor: AppColors.danger,
        colorText: Colors.white,
      );
    } finally {
      setState(() => _loadingGeo = false);
    }
  }

  void _handleSaveVehicle() {
    final basePrice = int.tryParse(_basePriceCtrl.text) ?? 1000;
    final priceKm = int.tryParse(_priceKmCtrl.text) ?? 200;
    controller.handleSaveVehicle(
      _selectedVehicle,
      _plateCtrl.text,
      basePrice,
      priceKm,
      _addressCtrl.text,
      _gpsCtrl.text,
    );
    Get.snackbar(
      'Véhicule mis à jour',
      'Les spécifications de votre véhicule ont été sauvegardées.',
      snackPosition: SnackPosition.BOTTOM,
      backgroundColor: AppColors.success,
      colorText: Colors.white,
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: controller.refresh,
          color: AppColors.driver,
          child: Obx(() {
            return CustomScrollView(
              physics: const AlwaysScrollableScrollPhysics(),
              slivers: [
                SliverToBoxAdapter(
                  child: buildDriverHeader(controller),
                ),
                SliverToBoxAdapter(child: _buildSubTabBar()),
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 20),
                    child: Column(
                      children: [
                        CommunicationBanner(
                          announcements: controller.announcements,
                        ),
                        LeSaviezVousCarousel(tips: controller.tips),
                        BroadcastMediaSection(
                          voice: controller.voiceBroadcasts,
                          video: controller.videoBroadcasts,
                        ),
                      ],
                    ),
                  ),
                ),
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.fromLTRB(20, 16, 20, 100),
                    child: AnimatedSwitcher(
                      duration: const Duration(milliseconds: 250),
                      child: _buildTabContent(),
                    ),
                  ),
                ),
              ],
            );
          }),
        ),
      ),
    );
  }

  // ── Navigation Tab Bar ─────────────────────────────────────────────────────
  Widget _buildSubTabBar() {
    return Container(
      margin: const EdgeInsets.fromLTRB(20, 16, 20, 0),
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: AppColors.border.withValues(alpha: 0.5),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        children: [
          Expanded(child: _buildSubTabButton('overview', 'VUE D\'ENSEMBLE')),
          Expanded(child: _buildSubTabButton('requests', 'COURSES')),
          Expanded(child: _buildSubTabButton('vehicle', 'VÉHICULE')),
        ],
      ),
    );
  }

  Widget _buildSubTabButton(String tabKey, String label) {
    final isActive = _activeTab == tabKey;
    return GestureDetector(
      onTap: () => setState(() => _activeTab = tabKey),
      child: Container(
        padding: const EdgeInsets.symmetric(vertical: 10),
        decoration: BoxDecoration(
          color: isActive ? AppColors.surface : Colors.transparent,
          borderRadius: BorderRadius.circular(12),
          boxShadow: isActive
              ? [
                  BoxShadow(
                    color: Colors.black.withValues(alpha: 0.05),
                    blurRadius: 6,
                    offset: const Offset(0, 2),
                  ),
                ]
              : null,
        ),
        child: Center(
          child: Text(
            label,
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w800,
              color: isActive ? AppColors.primary : AppColors.textSecondary,
              letterSpacing: 0.3,
            ),
          ),
        ),
      ),
    );
  }

  // ── Tab Content Switcher ───────────────────────────────────────────────────
  Widget _buildTabContent() {
    switch (_activeTab) {
      case 'requests':
        return _buildRequestsTab();
      case 'vehicle':
        return _buildVehicleTab();
      case 'overview':
      default:
        return _buildOverviewTab();
    }
  }

  // ── Tab 1: Overview ────────────────────────────────────────────────────────
  Widget _buildOverviewTab() {
    return KeyedSubtree(
      key: const ValueKey('overview'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Row(
            children: [
              Expanded(
                child: buildDriverStatCard(
                  title: 'Mon Portefeuille',
                  value: Formatters.fcfa(controller.walletMo.value),
                  subtitle: 'Solde disponible ›',
                  color: AppColors.primary,
                  background: AppColors.secondary,
                  icon: Icons.account_balance_wallet_outlined,
                  onTap: () => Get.toNamed(Routes.wallet),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: buildDriverStatCard(
                  title: 'Score Fluidité',
                  value: '${controller.fluidityScore.value} pts',
                  subtitle: 'Statut : ${controller.fluidityStatus}',
                  color: controller.fluidityScore.value > 150
                      ? Colors.amber.shade700
                      : AppColors.driver,
                  background: controller.fluidityScore.value > 150
                      ? Colors.amber.shade50
                      : AppColors.driverSoft,
                  icon: controller.fluidityScore.value > 150
                      ? Icons.workspace_premium_rounded
                      : Icons.military_tech,
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),
          buildDriverRatingEvolutionCard(),
          const SizedBox(height: 20),
          buildDriverTipCard(),
        ],
      ),
    );
  }

  // ── Tab 2: Requests / Deliveries ───────────────────────────────────────────
  Widget _buildRequestsTab() {
    final active = controller.driverActiveMissions;
    final available = controller.driverAvailableMissions;

    return KeyedSubtree(
      key: const ValueKey('requests'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          // Section 1: Active Deliveries
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Courses actives (${active.length})',
                style: const TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textPrimary,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          if (active.isEmpty)
            buildEmptyDeliveriesCard('Aucune course active en cours.')
          else
            Column(
              children: active
                  .map((m) => buildActiveDeliveryCard(controller, m))
                  .toList(),
            ),

          const SizedBox(height: 24),

          // Section 2: Available Deliveries
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Courses disponibles (${available.length})',
                style: const TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textPrimary,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          if (available.isEmpty)
            buildEmptyDeliveriesCard('Aucune course de livraison disponible.')
          else
            Column(
              children: available
                  .map((m) => buildAvailableDeliveryCard(controller, m))
                  .toList(),
            ),
        ],
      ),
    );
  }

  // ── Tab 3: Vehicle Configuration ───────────────────────────────────────────
  Widget _buildVehicleTab() {
    return KeyedSubtree(
      key: const ValueKey('vehicle'),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Text(
            'Spécifications Véhicule',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w800,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 16),
          DropdownButtonFormField<String>(
            initialValue: _selectedVehicle,
            decoration: InputDecoration(
              labelText: 'Catégorie de Véhicule',
              border:
                  OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
            ),
            items: const [
              DropdownMenuItem(value: 'Moto', child: Text('Moto')),
              DropdownMenuItem(value: 'Tricycle', child: Text('Tricycle')),
              DropdownMenuItem(
                value: 'Camionnette',
                child: Text('Camionnette'),
              ),
            ],
            onChanged: (val) {
              if (val != null) {
                setState(() {
                  _selectedVehicle = val;
                  _updatePricingForVehicle(val);
                });
              }
            },
          ),
          const SizedBox(height: 16),
          TextField(
            controller: _plateCtrl,
            decoration: InputDecoration(
              labelText: 'Plaque d\'immatriculation',
              border:
                  OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
            ),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _basePriceCtrl,
                  readOnly: true,
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(
                    labelText: 'Frais fixes (FCFA)',
                    helperText: 'Géré par la plateforme',
                    fillColor: AppColors.background,
                    filled: true,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: TextField(
                  controller: _priceKmCtrl,
                  readOnly: true,
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(
                    labelText: 'Frais par Km (FCFA)',
                    helperText: 'Géré par la plateforme',
                    fillColor: AppColors.background,
                    filled: true,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),

          // GPS and Location Section
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: AppColors.secondary.withValues(alpha: 0.5),
              borderRadius: BorderRadius.circular(18),
              border: Border.all(color: AppColors.border),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                TextField(
                  controller: _gpsCtrl,
                  decoration: InputDecoration(
                    labelText: 'Coordonnées GPS (Ex: 5.3482, -4.0169)',
                    fillColor: AppColors.surface,
                    filled: true,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                    hintText: 'lat, lng',
                    suffixIcon: IconButton(
                      icon: _loadingGeo
                          ? const SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                color: AppColors.primary,
                              ),
                            )
                          : const Icon(
                              Icons.my_location,
                              color: AppColors.primary,
                            ),
                      onPressed: _loadingGeo ? null : _handleGeoLocation,
                      tooltip: 'Obtenir ma position GPS',
                    ),
                  ),
                  style: const TextStyle(fontFamily: 'monospace', fontSize: 13),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _addressCtrl,
                  decoration: InputDecoration(
                    labelText: 'Adresse manuelle',
                    fillColor: AppColors.surface,
                    filled: true,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 24),
          ElevatedButton(
            onPressed: _handleSaveVehicle,
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.driver,
              foregroundColor: Colors.white,
              minimumSize: const Size(double.infinity, 50),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
              shadowColor: AppColors.driver.withValues(alpha: 0.3),
              elevation: 4,
            ),
            child: const Text(
              'Sauvegarder les modifications',
              style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800),
            ),
          ),
        ],
      ),
    );
  }
}
