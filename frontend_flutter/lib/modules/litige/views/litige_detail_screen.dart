import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/network/api_client.dart';
import '../../../core/network/api_endpoints.dart';

class LitigeDetailController extends GetxController {
  final ApiClient _client = ApiClient();
  final isLoading = false.obs;
  final litige = Rx<Map<String, dynamic>?>(null);
  late int litigeId;

  @override
  void onInit() {
    super.onInit();
    litigeId = Get.arguments['litigeId'];
    loadLitige();
  }

  Future<void> loadLitige() async {
    isLoading.value = true;
    try {
      final res = await _client.get('${ApiEndpoints.litiges}/$litigeId');
      litige.value = res.data['data'];
    } finally {
      isLoading.value = false;
    }
  }
}

class LitigeDetailScreen extends GetView<LitigeDetailController> {
  const LitigeDetailScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Détails du Litige')),
      body: Obx(() {
        if (controller.isLoading.value) {
          return const Center(child: CircularProgressIndicator());
        }
        final data = controller.litige.value;
        if (data == null) return const Center(child: Text('Erreur de chargement'));

        final isResolved = data['statut'] == 'resolu';

        return SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _StatusBadge(statut: data['statut']),
              const SizedBox(height: 16),
              const Text(
                'Description du signalement',
                style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
              ),
              const SizedBox(height: 8),
              Text(data['description'] ?? ''),
              const SizedBox(height: 32),
              if (isResolved) ...[
                const Divider(),
                const SizedBox(height: 24),
                const Text(
                  'Décision d\'arbitrage',
                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 18, color: AppColors.primary),
                ),
                const SizedBox(height: 12),
                _DecisionCard(decision: data['decision'], notes: data['adminNotes']),
              ] else ...[
                const Card(
                  color: AppColors.infoLight,
                  child: Padding(
                    padding: EdgeInsets.all(16),
                    child: Text(
                      'Votre dossier est en cours d\'instruction par un administrateur ProsArtisan. Vous serez notifié dès qu\'une décision sera prise.',
                      style: TextStyle(fontSize: 13),
                    ),
                  ),
                ),
              ],
            ],
          ),
        );
      }),
    );
  }
}

class _StatusBadge extends StatelessWidget {
  final String statut;
  const _StatusBadge({required this.statut});

  @override
  Widget build(BuildContext context) {
    Color color = AppColors.warning;
    String label = 'En cours';
    if (statut == 'resolu') {
      color = AppColors.success;
      label = 'Résolu';
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: color),
      ),
      child: Text(
        label,
        style: TextStyle(color: color, fontWeight: FontWeight.bold, fontSize: 12),
      ),
    );
  }
}

class _DecisionCard extends StatelessWidget {
  final String? decision;
  final String? notes;
  const _DecisionCard({this.decision, this.notes});

  @override
  Widget build(BuildContext context) {
    String label = 'Arbitrage effectué';
    if (decision == 'client') label = 'Remboursement Client';
    if (decision == 'artisan') label = 'Paiement Artisan';
    if (decision == 'gel') label = 'Fonds Gelés / Enquête Terrain';

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.primary, width: 2),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
          ),
          if (notes != null && notes!.isNotEmpty) ...[
            const SizedBox(height: 12),
            const Text('Notes de l\'administrateur :', style: TextStyle(fontWeight: FontWeight.w600, fontSize: 13)),
            const SizedBox(height: 4),
            Text(notes!, style: const TextStyle(fontStyle: FontStyle.italic)),
          ],
        ],
      ),
    );
  }
}
