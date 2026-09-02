import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../controllers/devis_controller.dart';
import '../widgets/devis_review/artisan_info_card.dart';
import '../widgets/devis_review/grands_comptes_warning_card.dart';
import '../widgets/devis_review/jalons_section.dart';
import '../widgets/devis_review/lignes_section.dart';
import '../widgets/devis_review/pending_payment_buttons.dart';
import '../widgets/devis_review/pending_virement_instructions_card.dart';
import '../widgets/devis_review/recap_section.dart';
import '../widgets/devis_review/review_action_buttons.dart';
import '../widgets/devis_review/review_app_bar.dart';
import '../widgets/devis_review/review_error_state.dart';
import '../widgets/devis_review/review_status_card.dart';

/// Vue de consultation et validation/refus de devis pour le client
///
/// WORKFLOW:
/// 1. Client crée mission + sélectionne artisan → Notification artisan
/// 2. Artisan crée devis → Notification client
/// 3. **Client valide/refuse devis (cette vue)**
///    - Si validé → Paiement → Mission financée
///    - Si refusé → Retour à l'artisan
class DevisReviewScreen extends StatelessWidget {
  const DevisReviewScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.put(DevisController());
    final devisId = Get.arguments as int?;

    if (devisId != null) {
      controller.loadDevis(devisId);
    }

    return Scaffold(
      backgroundColor: AppColors.background,
      body: SafeArea(
        child: Column(
          children: [
            const ReviewAppBar(),
            Expanded(
              child: Obx(() {
                if (controller.isLoading.value) {
                  return const Center(child: CircularProgressIndicator());
                }

                final devis = controller.currentDevis.value;
                if (devis == null) {
                  return ReviewErrorState(
                    onRetry: () => controller.loadDevis(devisId ?? 0),
                  );
                }

                return SingleChildScrollView(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      ReviewStatusCard(
                        devis: devis,
                        hasPendingPayment: controller.hasPendingPaymentFor(
                          devis.id,
                        ),
                      ),
                      if (controller.hasPendingPaymentFor(devis.id) &&
                          controller.pendingProvider.value ==
                              'virement_bancaire') ...[
                        const SizedBox(height: 24),
                        PendingVirementInstructionsCard(
                          instructions:
                              controller.pendingVirementInstructions.value,
                        ),
                      ] else if (devis.totalGeneral >= 2000000 &&
                          devis.statut == 'soumis') ...[
                        const SizedBox(height: 24),
                        const GrandsComptesWarningCard(),
                      ],
                      const SizedBox(height: 24),
                      ArtisanInfoCard(devis: devis),
                      const SizedBox(height: 24),
                      LignesSection(devis: devis),
                      const SizedBox(height: 24),
                      JalonsSection(devis: devis),
                      const SizedBox(height: 24),
                      RecapSection(devis: devis),
                      const SizedBox(height: 120),
                    ],
                  ),
                );
              }),
            ),
          ],
        ),
      ),
      bottomNavigationBar: Obx(() {
        final devis = controller.currentDevis.value;
        if (devis == null) {
          return const SizedBox.shrink();
        }
        if (controller.hasPendingPaymentFor(devis.id)) {
          return PendingPaymentButtons(
            controller: controller,
            devisId: devis.id,
          );
        }
        if (devis.statut != 'soumis') {
          return const SizedBox.shrink();
        }
        return ReviewActionButtons(
          controller: controller,
          devisId: devis.id,
        );
      }),
    );
  }
}
