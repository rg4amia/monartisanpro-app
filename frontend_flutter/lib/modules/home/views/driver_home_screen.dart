import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:geolocator/geolocator.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/mission_model.dart';
import '../../notifications/controllers/notifications_controller.dart';
import '../../../shared/widgets/communication_banner.dart';
import '../controllers/home_controller.dart';
import 'delivery_route_planner_screen.dart';

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
        _gpsCtrl.text = '${pos.latitude.toStringAsFixed(6)}, ${pos.longitude.toStringAsFixed(6)}';
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
                SliverToBoxAdapter(child: _buildHeader()),
                SliverToBoxAdapter(child: _buildSubTabBar()),
                SliverToBoxAdapter(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 20),
                    child: Column(
                      children: [
                        CommunicationBanner(announcements: controller.announcements),
                        LeSaviezVousCarousel(tips: controller.tips),
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
              Stack(
                clipBehavior: Clip.none,
                children: [
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
                  (() {
                    final unread = Get.isRegistered<NotificationsController>()
                        ? Get.find<NotificationsController>().unreadCount
                        : 0;
                    if (unread == 0) return const SizedBox.shrink();
                    return Positioned(
                      right: -2,
                      top: -2,
                      child: Container(
                        padding: const EdgeInsets.all(4),
                        decoration: const BoxDecoration(
                          color: Colors.red,
                          shape: BoxShape.circle,
                        ),
                        constraints: const BoxConstraints(
                          minWidth: 16,
                          minHeight: 16,
                        ),
                        child: Center(
                          child: Text(
                            '$unread',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 9,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                        ),
                      ),
                    );
                  })(),
                ],
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
                child: _buildStatCard(
                  title: 'Mon Portefeuille',
                  value: Formatters.fcfa(controller.walletMo.value),
                  subtitle: 'Solde disponible',
                  color: AppColors.primary,
                  background: AppColors.secondary,
                  icon: Icons.account_balance_wallet_outlined,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _buildStatCard(
                  title: 'Score Fluidité',
                  value: '${controller.fluidityScore.value} pts',
                  subtitle: 'Statut : ${controller.fluidityStatus}',
                  color: controller.fluidityScore.value > 150 ? Colors.amber.shade700 : AppColors.driver,
                  background: controller.fluidityScore.value > 150 ? Colors.amber.shade50 : AppColors.driverSoft,
                  icon: controller.fluidityScore.value > 150 ? Icons.workspace_premium_rounded : Icons.military_tech,
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
      ),
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
          Row(
            children: [
              Expanded(
                child: OutlinedButton.icon(
                  onPressed: () => Get.to(() => DeliveryRoutePlannerScreen(mission: mission)),
                  style: OutlinedButton.styleFrom(
                    foregroundColor: AppColors.driver,
                    side: const BorderSide(color: AppColors.driver),
                    minimumSize: const Size(0, 44),
                    shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                  ),
                  icon: const Icon(Icons.map_outlined, size: 18),
                  label: const Text('Itinéraire', style: TextStyle(fontSize: 12, fontWeight: FontWeight.bold)),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: rawStatus == 'driver_assigned' || rawStatus == 'prepared'
                    ? ElevatedButton.icon(
                        onPressed: () => _promptPickupCode(mission),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.warning,
                          foregroundColor: Colors.white,
                          minimumSize: const Size(0, 44),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                        icon: const Icon(Icons.check_circle_outline, size: 18),
                        label: const Text('Enlèvement', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800)),
                      )
                    : ElevatedButton.icon(
                        onPressed: () => _promptDropoffCode(mission),
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.success,
                          foregroundColor: Colors.white,
                          minimumSize: const Size(0, 44),
                          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
                        ),
                        icon: const Icon(Icons.check_circle, size: 18),
                        label: const Text('Livraison', style: TextStyle(fontSize: 12, fontWeight: FontWeight.w800)),
                      ),
              ),
            ],
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
            initialValue: _selectedVehicle,
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
              border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
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
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
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
                TextField(
                  controller: _gpsCtrl,
                  decoration: InputDecoration(
                    labelText: 'Coordonnées GPS (Ex: 5.3482, -4.0169)',
                    fillColor: AppColors.surface,
                    filled: true,
                    border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
                    hintText: 'lat, lng',
                    suffixIcon: IconButton(
                      icon: _loadingGeo
                          ? const SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(strokeWidth: 2, color: AppColors.primary),
                            )
                          : const Icon(Icons.my_location, color: AppColors.primary),
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
