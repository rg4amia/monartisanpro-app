import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:geolocator/geolocator.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/mission_model.dart';
import '../controllers/home_controller.dart';

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
  String _selectedVehicle = 'Moto';
  String _gpsCoords = '';
  bool _loadingGeo = false;

  @override
  void initState() {
    super.initState();
    _plateCtrl.text = controller.driverPlate.value;
    _basePriceCtrl.text = controller.driverBasePrice.value.toString();
    _priceKmCtrl.text = controller.driverPriceKm.value.toString();
    _addressCtrl.text = controller.driverAddress.value;
    _selectedVehicle = controller.driverVehicle.value;
    _gpsCoords = controller.driverGpsCoords.value;
  }

  @override
  void dispose() {
    _plateCtrl.dispose();
    _basePriceCtrl.dispose();
    _priceKmCtrl.dispose();
    _addressCtrl.dispose();
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
        _gpsCoords = '${pos.latitude.toStringAsFixed(6)}, ${pos.longitude.toStringAsFixed(6)}';
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
      _gpsCoords,
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
          child: CustomScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              SliverToBoxAdapter(child: _buildHeader()),
              SliverToBoxAdapter(child: _buildSubTabBar()),
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
          ),
        ),
      ),
    );
  }

  // ── Header Section ─────────────────────────────────────────────────────────
  Widget _buildHeader() {
    final firstName = controller.userName.value.isNotEmpty
        ? controller.userName.value.split(' ').first
        : 'Partenaire';

    return Container(
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 24),
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            AppColors.primary,
            AppColors.driver,
          ],
        ),
        borderRadius: BorderRadius.vertical(bottom: Radius.circular(28)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 52,
                height: 52,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.14),
                  borderRadius: BorderRadius.circular(16),
                ),
                child: const Icon(Icons.local_shipping, color: Colors.white),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Espace Livreur',
                      style: TextStyle(
                        color: Colors.white70,
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 0.5,
                      ),
                    ),
                    Text(
                      'Bonjour, $firstName',
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 24,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ],
                ),
              ),
              GestureDetector(
                onTap: () => Get.toNamed('/notifications'),
                child: Container(
                  width: 44,
                  height: 44,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: const Icon(Icons.notifications_outlined, color: Colors.white),
                ),
              ),
            ],
          ),
        ],
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
                child: Obx(() => _buildStatCard(
                      title: 'Mon Portefeuille',
                      value: Formatters.fcfa(controller.walletMo.value),
                      subtitle: 'Solde disponible',
                      color: AppColors.primary,
                      background: AppColors.secondary,
                      icon: Icons.account_balance_wallet_outlined,
                    )),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _buildStatCard(
                  title: 'Note Moyenne',
                  value: '4.9 ★',
                  subtitle: '85 évaluations',
                  color: AppColors.driver,
                  background: AppColors.driverSoft,
                  icon: Icons.star_outline_rounded,
                ),
              ),
            ],
          ),
          const SizedBox(height: 20),
          _buildTipCard(),
        ],
      ),
    );
  }

  Widget _buildStatCard({
    required String title,
    required String value,
    required String subtitle,
    required Color color,
    required Color background,
    required IconData icon,
  }) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: background,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: color, size: 18),
          ),
          const SizedBox(height: 14),
          Text(
            value,
            style: TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.w800,
              color: color,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            title,
            style: const TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w700,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            subtitle,
            style: const TextStyle(
              fontSize: 11,
              color: AppColors.textSecondary,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTipCard() {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: AppColors.driverSoft,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.driver.withValues(alpha: 0.14)),
      ),
      child: const Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(Icons.lightbulb_outline_rounded, color: AppColors.driver),
          SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Conseil du jour',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                    color: AppColors.textPrimary,
                  ),
                ),
                SizedBox(height: 4),
                Text(
                  'Activez le GPS dans l\'onglet "Véhicule" pour aider les quincailliers et artisans à localiser vos livraisons plus rapidement.',
                  style: TextStyle(
                    fontSize: 12.5,
                    height: 1.4,
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ── Tab 2: Requests / Deliveries ───────────────────────────────────────────
  Widget _buildRequestsTab() {
    return KeyedSubtree(
      key: const ValueKey('requests'),
      child: Obx(() {
        final active = controller.driverActiveMissions;
        final available = controller.driverAvailableMissions;

        return Column(
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
              _buildEmptyDeliveriesCard('Aucune course active en cours.')
            else
              Column(
                children: active.map((m) => _buildActiveDeliveryCard(m)).toList(),
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
              _buildEmptyDeliveriesCard('Aucune course de livraison disponible.')
            else
              Column(
                children: available.map((m) => _buildAvailableDeliveryCard(m)).toList(),
              ),
          ],
        );
      }),
    );
  }

  Widget _buildEmptyDeliveriesCard(String text) {
    return Container(
      padding: const EdgeInsets.symmetric(vertical: 24, horizontal: 16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.border),
      ),
      child: Center(
        child: Text(
          text,
          textAlign: TextAlign.center,
          style: const TextStyle(
            fontSize: 13,
            color: AppColors.textSecondary,
            fontStyle: FontStyle.italic,
          ),
        ),
      ),
    );
  }

  Widget _buildActiveDeliveryCard(MissionModel mission) {
    final rawStatus = mission.rawStatus;
    final deliveryFee = mission.id == 301 ? 1500 : 1200;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.border),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 8,
            offset: const Offset(0, 3),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'CMD-#${mission.id}',
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textPrimary,
                ),
              ),
              Text(
                '+ ${Formatters.fcfa(deliveryFee)}',
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                  color: AppColors.success,
                ),
              ),
            ],
          ),
          const Divider(height: 20),
          _buildDeliveryStep(
            stepNumber: '1',
            title: 'Enlèvement Boutique (Fournisseur)',
            value: '${mission.artisanName ?? 'Quincaillerie Centrale'} (${mission.location ?? 'Cocody'})',
          ),
          const SizedBox(height: 12),
          _buildDeliveryStep(
            stepNumber: '2',
            title: 'Livraison Client',
            value: '${mission.clientName ?? 'Client'} (${mission.location ?? 'Cocody'})',
          ),
          const SizedBox(height: 16),
          if (rawStatus == 'driver_assigned' || rawStatus == 'prepared')
            ElevatedButton.icon(
              onPressed: () => _promptPickupCode(mission),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.warning,
                foregroundColor: Colors.white,
                minimumSize: const Size(double.infinity, 44),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              icon: const Icon(Icons.check_circle_outline, size: 18),
              label: const Text('Confirmer Enlèvement au Magasin', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800)),
            )
          else
            ElevatedButton.icon(
              onPressed: () => _promptDropoffCode(mission),
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.success,
                foregroundColor: Colors.white,
                minimumSize: const Size(double.infinity, 44),
                shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              ),
              icon: const Icon(Icons.check_circle, size: 18),
              label: const Text('Valider Livraison chez le Client', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800)),
            ),
        ],
      ),
    );
  }

  Widget _buildDeliveryStep({
    required String stepNumber,
    required String title,
    required String value,
  }) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 18,
          height: 18,
          decoration: const BoxDecoration(
            color: AppColors.primary,
            shape: BoxShape.circle,
          ),
          child: Center(
            child: Text(
              stepNumber,
              style: const TextStyle(color: Colors.white, fontSize: 10, fontWeight: FontWeight.bold),
            ),
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title.toUpperCase(),
                style: const TextStyle(fontSize: 9, fontWeight: FontWeight.w800, color: AppColors.textSecondary),
              ),
              const SizedBox(height: 2),
              Text(
                value,
                style: const TextStyle(fontSize: 12.5, fontWeight: FontWeight.w600, color: AppColors.textPrimary),
              ),
            ],
          ),
        ),
      ],
    );
  }

  void _promptPickupCode(MissionModel mission) {
    final textController = TextEditingController();
    Get.dialog(
      AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Code d\'enlèvement magasin', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Entrez le code d\'enlèvement fourni par la quincaillerie.',
              style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: textController,
              decoration: InputDecoration(
                hintText: 'Ex: RET-${mission.id}',
                helperText: 'Pour tester, saisissez : RET-${mission.id}',
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Get.back(), child: const Text('Annuler')),
          ElevatedButton(
            onPressed: () {
              Get.back();
              controller.handleDriverPickupFromStore(mission, textController.text);
            },
            child: const Text('Confirmer'),
          ),
        ],
      ),
    );
  }

  void _promptDropoffCode(MissionModel mission) {
    final textController = TextEditingController();
    Get.dialog(
      AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        title: const Text('Code de réception client', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800)),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Saisissez le code de confirmation OTP envoyé au client.',
              style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: textController,
              decoration: InputDecoration(
                hintText: 'Ex: REC-${mission.id}',
                helperText: 'Pour tester, saisissez : REC-${mission.id}',
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(10)),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(onPressed: () => Get.back(), child: const Text('Annuler')),
          ElevatedButton(
            onPressed: () {
              Get.back();
              controller.handleDriverDropoffToClient(mission, textController.text);
            },
            child: const Text('Valider'),
          ),
        ],
      ),
    );
  }

  Widget _buildAvailableDeliveryCard(MissionModel mission) {
    final deliveryFee = mission.id == 301 ? 1500 : 1200;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Magasin : ${mission.artisanName ?? 'Quincaillerie Centrale'}',
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                    color: AppColors.textPrimary,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'Destination : ${mission.location ?? 'Cocody, Angré'}',
                  style: const TextStyle(
                    fontSize: 12,
                    color: AppColors.textSecondary,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  'Matériel : ${mission.description ?? 'Articles divers'}',
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    fontSize: 12,
                    color: AppColors.textSecondary,
                    fontStyle: FontStyle.italic,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Text(
                Formatters.fcfa(deliveryFee),
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                  color: AppColors.driver,
                ),
              ),
              const SizedBox(height: 8),
              ElevatedButton(
                onPressed: () => controller.handleAcceptDelivery(mission),
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.primary,
                  foregroundColor: Colors.white,
                  minimumSize: const Size(80, 32),
                  padding: const EdgeInsets.symmetric(horizontal: 12),
                  shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                ),
                child: const Text('Accepter', style: TextStyle(fontSize: 11, fontWeight: FontWeight.bold)),
              ),
            ],
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
            value: _selectedVehicle,
            decoration: InputDecoration(
              labelText: 'Catégorie de Véhicule',
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
            ),
            items: const [
              DropdownMenuItem(value: 'Moto', child: Text('Moto')),
              DropdownMenuItem(value: 'Tricycle', child: Text('Tricycle')),
              DropdownMenuItem(value: 'Camionnette', child: Text('Camionnette')),
            ],
            onChanged: (val) {
              if (val != null) setState(() => _selectedVehicle = val);
            },
          ),
          const SizedBox(height: 16),
          TextField(
            controller: _plateCtrl,
            decoration: InputDecoration(
              labelText: 'Plaque d\'immatriculation',
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
            ),
          ),
          const SizedBox(height: 16),
          Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _basePriceCtrl,
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(
                    labelText: 'Frais fixes (FCFA)',
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: TextField(
                  controller: _priceKmCtrl,
                  keyboardType: TextInputType.number,
                  decoration: InputDecoration(
                    labelText: 'Frais par Km (FCFA)',
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
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
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Row(
                      children: [
                        Icon(Icons.location_on, color: AppColors.primary, size: 20),
                        SizedBox(width: 6),
                        Text(
                          'Localisation Actuelle (GPS)',
                          style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: AppColors.textPrimary),
                        ),
                      ],
                    ),
                    ElevatedButton.icon(
                      onPressed: _loadingGeo ? null : _handleGeoLocation,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
                      ),
                      icon: _loadingGeo
                          ? const SizedBox(
                              width: 12,
                              height: 12,
                              child: CircularProgressIndicator(strokeWidth: 2, color: Colors.white),
                            )
                          : const Icon(Icons.my_location, size: 14),
                      label: const Text('GPS', style: TextStyle(fontSize: 10, fontWeight: FontWeight.bold)),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.surface,
                    borderRadius: BorderRadius.circular(10),
                    border: Border.all(color: AppColors.border),
                  ),
                  child: Text(
                    _gpsCoords.isEmpty ? 'Aucune position GPS enregistrée' : _gpsCoords,
                    style: const TextStyle(fontFamily: 'monospace', fontSize: 12, color: AppColors.textPrimary),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _addressCtrl,
                  decoration: InputDecoration(
                    labelText: 'Adresse manuelle',
                    fillColor: AppColors.surface,
                    filled: true,
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
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
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
              shadowColor: AppColors.driver.withValues(alpha: 0.3),
              elevation: 4,
            ),
            child: const Text('Sauvegarder les modifications', style: TextStyle(fontSize: 14, fontWeight: FontWeight.w800)),
          ),
        ],
      ),
    );
  }
}
