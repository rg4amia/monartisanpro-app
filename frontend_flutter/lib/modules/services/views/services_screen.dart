import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../controllers/services_controller.dart';
import '../models/sector_model.dart';
import '../models/trade_model.dart';

class ServicesScreen extends StatelessWidget {
  const ServicesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.put(ServicesController());

    return Scaffold(
      backgroundColor: const Color(0xFFF8FAFC),
      body: SafeArea(
        child: Column(
          children: [
            _buildAppBar(),
            _buildSearchBar(),
            Expanded(
              child: Obx(() {
                if (controller.isLoading.value) {
                  return const Center(
                    child: CircularProgressIndicator(
                      color: Color(0xFF4F46E5),
                    ),
                  );
                }

                if (controller.sectors.isEmpty) {
                  return const Center(
                    child: Text('Aucun service disponible'),
                  );
                }

                return ListView(
                  padding: const EdgeInsets.all(20),
                  children: [
                    const Text(
                      'Catégories de service',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w700,
                        color: Color(0xFF0F172A),
                      ),
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'Trouvez les meilleurs professionnels locaux pour vos besoins domestiques et personnels.',
                      style: TextStyle(
                        fontSize: 14,
                        color: Color(0xFF64748B),
                      ),
                    ),
                    const SizedBox(height: 20),
                    ...controller.sectors.map((sector) => Padding(
                          padding: const EdgeInsets.only(bottom: 12),
                          child: _ServiceCard(
                            sector: sector,
                            onTap: () => _showTradesBottomSheet(
                              context,
                              controller,
                              sector,
                            ),
                          ),
                        )),
                    const SizedBox(height: 20),
                    _NeedHelpCard(),
                  ],
                );
              }),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildAppBar() {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 16),
      child: Row(
        children: [
          GestureDetector(
            onTap: () => Get.back(),
            child: Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: const Color(0xFFE5E7EB)),
              ),
              child: const Icon(Icons.arrow_back, size: 20),
            ),
          ),
          const Expanded(
            child: Text(
              'Nos Services',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: Color(0xFF0F172A),
              ),
            ),
          ),
          const SizedBox(width: 40),
        ],
      ),
    );
  }

  Widget _buildSearchBar() {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 20),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFFE5E7EB)),
        ),
        child: const TextField(
          decoration: InputDecoration(
            hintText: 'Rechercher un service (ex. Plombier)',
            hintStyle: TextStyle(
              color: Color(0xFF94A3B8),
              fontSize: 14,
            ),
            border: InputBorder.none,
            icon: Icon(
              Icons.search,
              color: Color(0xFF94A3B8),
              size: 20,
            ),
          ),
        ),
      ),
    );
  }

  void _showTradesBottomSheet(
    BuildContext context,
    ServicesController controller,
    SectorModel sector,
  ) {
    controller.loadTrades(sector);

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => Container(
        height: MediaQuery.of(context).size.height * 0.7,
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: Column(
          children: [
            const SizedBox(height: 12),
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: const Color(0xFFE5E7EB),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            const SizedBox(height: 20),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 20),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'Services de ${sector.name}',
                          style: const TextStyle(
                            fontSize: 20,
                            fontWeight: FontWeight.w700,
                            color: Color(0xFF0F172A),
                          ),
                        ),
                        const SizedBox(height: 4),
                        const Text(
                          'Sélectionnez une tâche spécifique',
                          style: TextStyle(
                            fontSize: 14,
                            color: Color(0xFF64748B),
                          ),
                        ),
                      ],
                    ),
                  ),
                  GestureDetector(
                    onTap: () => Navigator.pop(context),
                    child: Container(
                      width: 32,
                      height: 32,
                      decoration: BoxDecoration(
                        color: const Color(0xFFF1F5F9),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: const Icon(
                        Icons.close,
                        size: 18,
                        color: Color(0xFF64748B),
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 20),
            Expanded(
              child: Obx(() {
                if (controller.isLoadingTrades.value) {
                  return const Center(
                    child: CircularProgressIndicator(
                      color: Color(0xFF4F46E5),
                    ),
                  );
                }

                if (controller.trades.isEmpty) {
                  return const Center(
                    child: Text('Aucune tâche spécifique disponible'),
                  );
                }

                return ListView.separated(
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  itemCount: controller.trades.length,
                  separatorBuilder: (_, __) => const SizedBox(height: 8),
                  itemBuilder: (context, index) {
                    final trade = controller.trades[index];
                    return _TradeItem(
                      trade: trade,
                      onTap: () {
                        Navigator.pop(context);
                        controller.selectTrade(trade);
                      },
                    );
                  },
                );
              }),
            ),
          ],
        ),
      ),
    );
  }
}

class _ServiceCard extends StatelessWidget {
  final SectorModel sector;
  final VoidCallback onTap;

  const _ServiceCard({
    required this.sector,
    required this.onTap,
  });

  IconData _getIcon() {
    switch (sector.name.toLowerCase()) {
      case 'plumbing':
        return Icons.plumbing_outlined;
      case 'electricity':
        return Icons.electric_bolt_outlined;
      case 'masonry':
        return Icons.foundation_outlined;
      case 'painting':
        return Icons.format_paint_outlined;
      case 'carpentry':
        return Icons.carpenter_outlined;
      case 'air conditioning':
        return Icons.ac_unit_outlined;
      case 'tailoring':
        return Icons.checkroom_outlined;
      case 'electronics':
        return Icons.devices_outlined;
      default:
        return Icons.build_outlined;
    }
  }

  Color _getColor() {
    if (sector.color != null) {
      try {
        return Color(int.parse(sector.color!.replaceFirst('#', '0xFF')));
      } catch (_) {}
    }
    return const Color(0xFF4F46E5);
  }

  @override
  Widget build(BuildContext context) {
    final color = _getColor();
    final icon = _getIcon();

    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          boxShadow: [
            BoxShadow(
              color: const Color(0xFF0F172A).withValues(alpha: 0.04),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Row(
          children: [
            Container(
              width: 56,
              height: 56,
              decoration: BoxDecoration(
                color: color.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(icon, color: color, size: 28),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    sector.name,
                    style: const TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w700,
                      color: Color(0xFF0F172A),
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    'Voir les services disponibles',
                    style: TextStyle(
                      fontSize: 13,
                      color: const Color(0xFF64748B),
                    ),
                  ),
                ],
              ),
            ),
            const Icon(
              Icons.chevron_right,
              color: Color(0xFF94A3B8),
              size: 24,
            ),
          ],
        ),
      ),
    );
  }
}

class _TradeItem extends StatelessWidget {
  final TradeModel trade;
  final VoidCallback onTap;

  const _TradeItem({
    required this.trade,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: const Color(0xFFF8FAFC),
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: const Color(0xFFE5E7EB)),
        ),
        child: Row(
          children: [
            Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: const Color(0xFF4F46E5).withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(10),
              ),
              child: const Icon(
                Icons.build_outlined,
                color: Color(0xFF4F46E5),
                size: 20,
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Text(
                trade.name,
                style: const TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                  color: Color(0xFF0F172A),
                ),
              ),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              decoration: BoxDecoration(
                color: const Color(0xFF4F46E5),
                borderRadius: BorderRadius.circular(8),
              ),
              child: const Text(
                'Choisir',
                style: TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: Colors.white,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _NeedHelpCard extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            Color(0xFF4F46E5),
            Color(0xFF6366F1),
          ],
        ),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Besoin d\'autre chose ?',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w700,
              color: Colors.white,
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Vous ne trouvez pas le service que vous cherchez ? Dites-le nous.',
            style: TextStyle(
              fontSize: 14,
              color: Colors.white,
            ),
          ),
          const SizedBox(height: 16),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: () {},
              style: ElevatedButton.styleFrom(
                backgroundColor: Colors.white,
                foregroundColor: const Color(0xFF4F46E5),
                padding: const EdgeInsets.symmetric(vertical: 12),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                ),
              ),
              child: const Text(
                'Demander un service',
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
