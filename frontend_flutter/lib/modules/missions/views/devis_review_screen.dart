import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/devis_model.dart';
import '../../../data/models/payment_model.dart';
import '../controllers/devis_controller.dart';

// ─── Design Tokens ────────────────────────────────────────────────────────────
abstract class _C {
  static const bg = AppColors.background;
  static const surface = AppColors.surface;
  static const primary = AppColors.primary;
  static const primaryLight = AppColors.secondary;
  static const ink = AppColors.textPrimary;
  static const muted = AppColors.textSecondary;
  static const subtle = AppColors.border;
  static const success = AppColors.success;
  static const successLight = AppColors.supplierSoft;
  static const warning = AppColors.accent;
  static const warningLight = AppColors.artisanSoft;
  static const danger = AppColors.danger;
  static const dangerLight = Color(0xFFFEE2E2);
}

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
      backgroundColor: _C.bg,
      body: SafeArea(
        child: Column(
          children: [
            _AppBar(),
            Expanded(
              child: Obx(() {
                if (controller.isLoading.value) {
                  return const Center(child: CircularProgressIndicator());
                }

                final devis = controller.currentDevis.value;
                if (devis == null) {
                  return _ErrorState(
                    onRetry: () => controller.loadDevis(devisId ?? 0),
                  );
                }

                return SingleChildScrollView(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _StatusCard(
                        devis: devis,
                        hasPendingPayment: controller.hasPendingPaymentFor(
                          devis.id,
                        ),
                      ),
                      if (controller.hasPendingPaymentFor(devis.id) &&
                          controller.pendingProvider.value == 'virement_bancaire') ...[
                        const SizedBox(height: 24),
                        _PendingVirementInstructionsCard(
                          instructions: controller.pendingVirementInstructions.value,
                        ),
                      ] else if (devis.totalGeneral >= 2000000 &&
                          devis.statut == 'soumis') ...[
                        const SizedBox(height: 24),
                        _GrandsComptesWarningCard(),
                      ],
                      const SizedBox(height: 24),
                      _ArtisanInfoCard(devis: devis),
                      const SizedBox(height: 24),
                      _LignesSection(devis: devis),
                      const SizedBox(height: 24),
                      _JalonsSection(devis: devis),
                      const SizedBox(height: 24),
                      _RecapSection(devis: devis),
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
          return _PendingPaymentButtons(
            controller: controller,
            devisId: devis.id,
          );
        }
        if (devis.statut != 'soumis') {
          return const SizedBox.shrink();
        }
        return _ActionButtons(
          controller: controller,
          devisId: devis.id,
        );
      }),
    );
  }
}

// ─── App Bar ──────────────────────────────────────────────────────────────────
class _AppBar extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
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
                color: _C.surface,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: _C.subtle),
              ),
              child: const Icon(Icons.arrow_back, size: 20),
            ),
          ),
          const Expanded(
            child: Text(
              'Devis reçu',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: _C.ink,
              ),
            ),
          ),
          const SizedBox(width: 40),
        ],
      ),
    );
  }
}

// ─── Status Card ──────────────────────────────────────────────────────────────
class _StatusCard extends StatelessWidget {
  final DevisModel devis;
  final bool hasPendingPayment;

  const _StatusCard({
    required this.devis,
    required this.hasPendingPayment,
  });

  @override
  Widget build(BuildContext context) {
    final config = _getStatusConfig(devis.statut);
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: config['bg'] as Color,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: config['borderColor'] as Color),
      ),
      child: Row(
        children: [
          Icon(config['icon'] as IconData,
              color: config['color'] as Color, size: 24),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  config['title'] as String,
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                    color: config['color'] as Color,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  config['subtitle'] as String,
                  style: const TextStyle(
                    fontSize: 13,
                    color: _C.muted,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Map<String, dynamic> _getStatusConfig(String statut) {
    if (hasPendingPayment) {
      return {
        'icon': Icons.payment,
        'title': 'Paiement de l\'acompte en attente',
        'subtitle':
            'Validez le paiement sur votre Mobile Money puis revenez vérifier le statut.',
        'color': _C.primary,
        'bg': _C.primaryLight,
        'borderColor': _C.primary.withValues(alpha: 0.2),
      };
    }

    switch (statut) {
      case 'soumis':
        return {
          'icon': Icons.schedule,
          'title': 'Devis en attente de votre validation',
          'subtitle': 'Consultez les détails et décidez d\'accepter ou refuser',
          'color': _C.warning,
          'bg': _C.warningLight,
          'borderColor': _C.warning.withValues(alpha: 0.2),
        };
      case 'accepte':
        return {
          'icon': Icons.check_circle,
          'title': 'Mission financée',
          'subtitle': 'Le devis a été accepté et le séquestre est en place',
          'color': _C.success,
          'bg': _C.successLight,
          'borderColor': _C.success.withValues(alpha: 0.2),
        };
      case 'refuse':
        return {
          'icon': Icons.cancel,
          'title': 'Devis refusé',
          'subtitle': 'Vous avez refusé ce devis',
          'color': _C.danger,
          'bg': _C.dangerLight,
          'borderColor': _C.danger.withValues(alpha: 0.2),
        };
      default:
        return {
          'icon': Icons.info,
          'title': 'Devis en brouillon',
          'subtitle': 'L\'artisan prépare le devis',
          'color': _C.muted,
          'bg': _C.subtle,
          'borderColor': _C.muted.withValues(alpha: 0.2),
        };
    }
  }
}

// ─── Artisan Info Card ────────────────────────────────────────────────────────
class _ArtisanInfoCard extends StatelessWidget {
  final DevisModel devis;
  const _ArtisanInfoCard({required this.devis});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _C.subtle),
      ),
      child: Row(
        children: [
          Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(
              color: _C.primaryLight,
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(Icons.person, color: _C.primary, size: 28),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  devis.artisanName ?? 'Artisan #${devis.artisanId}',
                  style: const TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w700,
                    color: _C.ink,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'Créé le ${_formatDate(devis.createdAt)}',
                  style: const TextStyle(
                    fontSize: 13,
                    color: _C.muted,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _formatDate(String date) {
    try {
      final dt = DateTime.parse(date);
      return DateFormat('dd/MM/yyyy à HH:mm').format(dt);
    } catch (_) {
      return date;
    }
  }
}

// ─── Lignes Section ───────────────────────────────────────────────────────────
class _LignesSection extends StatelessWidget {
  final DevisModel devis;
  const _LignesSection({required this.devis});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Détail des travaux',
          style: TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.w700,
            color: _C.ink,
          ),
        ),
        const SizedBox(height: 12),
        ...devis.lignes.map((ligne) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _LigneCard(ligne: ligne),
            )),
      ],
    );
  }
}

// ─── Jalons Section ───────────────────────────────────────────────────────────
class _JalonsSection extends StatelessWidget {
  final DevisModel devis;
  const _JalonsSection({required this.devis});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Jalons de paiement',
          style: TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.w700,
            color: _C.ink,
          ),
        ),
        const SizedBox(height: 8),
        Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: _C.primaryLight,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Row(
            children: [
              const Icon(Icons.info_outline, color: _C.primary, size: 18),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  'Les paiements seront libérés jalon par jalon après validation avec code OTP',
                  style: TextStyle(
                    fontSize: 12,
                    color: _C.primary,
                  ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 12),
        ...devis.jalons.map((jalon) => Padding(
              padding: const EdgeInsets.only(bottom: 12),
              child: _JalonCard(jalon: jalon),
            )),
      ],
    );
  }
}

// ─── Recap Section ────────────────────────────────────────────────────────────
class _RecapSection extends StatelessWidget {
  final DevisModel devis;
  const _RecapSection({required this.devis});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _C.subtle),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Récapitulatif financier',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w700,
              color: _C.ink,
            ),
          ),
          const SizedBox(height: 16),
          _RecapRow(
            label: 'Main d\'œuvre',
            value: _formatFCFA(devis.montantMo),
            valueColor: _C.success,
          ),
          const SizedBox(height: 8),
          _RecapRow(
            label: 'Matériaux',
            value: _formatFCFA(devis.montantMateriaux),
            valueColor: _C.warning,
          ),
          const Divider(height: 24),
          _RecapRow(
            label: 'MONTANT TOTAL À PAYER',
            value: _formatFCFA(devis.totalGeneralTtc),
            valueColor: _C.primary,
            isBold: true,
            isLarge: true,
          ),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: _C.bg,
              borderRadius: BorderRadius.circular(8),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Répartition automatique du séquestre',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                    color: _C.ink,
                  ),
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: _ProgressBar(
                        label: 'Matériaux',
                        percentage: devis.totalGeneralTtc > 0
                            ? (devis.montantMateriaux / devis.totalGeneralTtc * 100)
                            : 0.0,
                        color: _C.warning,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Row(
                  children: [
                    Expanded(
                      child: _ProgressBar(
                        label: 'Main d\'œuvre',
                        percentage: devis.totalGeneralTtc > 0
                            ? (devis.montantMo / devis.totalGeneralTtc * 100)
                            : 0.0,
                        color: _C.success,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _formatFCFA(int amount) {
    return '${amount.toString().replaceAllMapped(
          RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
          (Match m) => '${m[1]} ',
        )} FCFA';
  }
}

// ─── Action Buttons ───────────────────────────────────────────────────────────
class _ActionButtons extends StatelessWidget {
  final DevisController controller;
  final int devisId;

  const _ActionButtons({
    required this.controller,
    required this.devisId,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: _C.surface,
        boxShadow: [
          BoxShadow(
            color: _C.ink.withValues(alpha: 0.08),
            blurRadius: 16,
            offset: const Offset(0, -4),
          ),
        ],
      ),
      child: SafeArea(
        child: Obx(() => Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                // Bouton Accepter
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: controller.isSubmitting.value
                        ? null
                        : () => _showAcceptDialog(context),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: _C.success,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: controller.isSubmitting.value
                        ? const SizedBox(
                            height: 20,
                            width: 20,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : const Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Icon(Icons.check_circle, size: 20),
                              SizedBox(width: 8),
                              Text(
                                'Accepter et payer',
                                style: TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                            ],
                          ),
                  ),
                ),
                const SizedBox(height: 12),
                // Bouton Refuser
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton(
                    onPressed: controller.isSubmitting.value
                        ? null
                        : () => _showRefuseDialog(context),
                    style: OutlinedButton.styleFrom(
                      foregroundColor: _C.danger,
                      side: const BorderSide(color: _C.danger),
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: const Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.cancel, size: 20),
                        SizedBox(width: 8),
                        Text(
                          'Refuser ce devis',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            )),
      ),
    );
  }

  void _showAcceptDialog(BuildContext context) {
    final devis = controller.currentDevis.value;
    if (devis == null) return;

    final int totalGeneralTtc = devis.totalGeneralTtc;
    final int montantMateriaux = devis.montantMateriaux;

    String paymentType = totalGeneralTtc >= 2000000 ? 'hybrid' : 'total';
    String selectedProvider = (paymentType == 'hybrid' ? montantMateriaux : totalGeneralTtc) >= 2000000
        ? 'virement_bancaire'
        : 'wave';

    Get.dialog(
      Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: StatefulBuilder(
            builder: (context, setState) {
              final int amountToPay = paymentType == 'hybrid' ? montantMateriaux : totalGeneralTtc;
              final bool isGrandsComptes = amountToPay >= 2000000;

              if (isGrandsComptes && selectedProvider != 'virement_bancaire') {
                selectedProvider = 'virement_bancaire';
              }

              return Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 50,
                    height: 50,
                    decoration: BoxDecoration(
                      color: _C.successLight,
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: const Icon(Icons.payment, color: _C.success, size: 28),
                  ),
                  const SizedBox(height: 12),
                  const Text(
                    'Accepter le devis',
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w700,
                      color: _C.ink,
                    ),
                  ),
                  const SizedBox(height: 16),
                  
                  const Align(
                    alignment: Alignment.centerLeft,
                    child: Text(
                      'Option de Financement',
                      style: TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: _C.muted),
                    ),
                  ),
                  const SizedBox(height: 8),
                  
                  InkWell(
                    onTap: () {
                      setState(() {
                        paymentType = 'total';
                        if (totalGeneralTtc >= 2000000) {
                          selectedProvider = 'virement_bancaire';
                        }
                      });
                    },
                    child: Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        border: Border.all(
                          color: paymentType == 'total' ? _C.primary : _C.subtle,
                          width: paymentType == 'total' ? 2 : 1,
                        ),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(
                        children: [
                          Icon(
                            paymentType == 'total' ? Icons.radio_button_checked : Icons.radio_button_unchecked,
                            color: paymentType == 'total' ? _C.primary : _C.muted,
                            size: 22,
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text(
                                  'Financement Intégral',
                                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  'Préfinancez l\'intégralité (${totalGeneralTtc.toString().replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (Match m) => '${m[1]} ')} FCFA).',
                                  style: const TextStyle(fontSize: 11, color: _C.muted),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  const SizedBox(height: 8),
                  
                  InkWell(
                    onTap: () {
                      setState(() {
                        paymentType = 'hybrid';
                        if (montantMateriaux >= 2000000) {
                          selectedProvider = 'virement_bancaire';
                        }
                      });
                    },
                    child: Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        border: Border.all(
                          color: paymentType == 'hybrid' ? _C.primary : _C.subtle,
                          width: paymentType == 'hybrid' ? 2 : 1,
                        ),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: Row(
                        children: [
                          Icon(
                            paymentType == 'hybrid' ? Icons.radio_button_checked : Icons.radio_button_unchecked,
                            color: paymentType == 'hybrid' ? _C.primary : _C.muted,
                            size: 22,
                          ),
                          const SizedBox(width: 8),
                          Expanded(
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                const Text(
                                  'Financement Hybride (Matériaux)',
                                  style: TextStyle(fontWeight: FontWeight.bold, fontSize: 13),
                                ),
                                const SizedBox(height: 2),
                                Text(
                                  'Payez les matériaux (${montantMateriaux.toString().replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (Match m) => '${m[1]} ')} FCFA). MO par jalons.',
                                  style: const TextStyle(fontSize: 11, color: _C.muted),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  
                  const SizedBox(height: 20),
                  
                  Align(
                    alignment: Alignment.centerLeft,
                    child: Text(
                      'Moyen de Paiement (${amountToPay.toString().replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (Match m) => '${m[1]} ')} FCFA)',
                      style: const TextStyle(fontSize: 13, fontWeight: FontWeight.bold, color: _C.muted),
                    ),
                  ),
                  const SizedBox(height: 8),
                  
                  if (isGrandsComptes) ...[
                    Container(
                      padding: const EdgeInsets.all(10),
                      margin: const EdgeInsets.only(bottom: 12),
                      decoration: BoxDecoration(
                        color: _C.dangerLight,
                        borderRadius: BorderRadius.circular(8),
                        border: Border.all(color: _C.danger.withValues(alpha: 0.3)),
                      ),
                      child: const Row(
                        children: [
                          Icon(Icons.warning, color: _C.danger, size: 18),
                          SizedBox(width: 8),
                          Expanded(
                            child: Text(
                              'Les paiements >= 2 000 000 FCFA doivent être réglés par Virement Bancaire.',
                              style: TextStyle(
                                color: _C.danger,
                                fontSize: 11,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                  
                  _PaymentOption(
                    label: 'Wave CI',
                    value: 'wave',
                    groupValue: selectedProvider,
                    icon: Icons.waves,
                    disabled: isGrandsComptes,
                    onChanged: (v) => setState(() => selectedProvider = v!),
                  ),
                  const SizedBox(height: 8),
                  _PaymentOption(
                    label: 'Orange Money CI',
                    value: 'orange_money',
                    groupValue: selectedProvider,
                    icon: Icons.phone_android,
                    disabled: isGrandsComptes,
                    onChanged: (v) => setState(() => selectedProvider = v!),
                  ),
                  const SizedBox(height: 8),
                  _PaymentOption(
                    label: 'Virement Bancaire',
                    value: 'virement_bancaire',
                    groupValue: selectedProvider,
                    icon: Icons.account_balance,
                    onChanged: (v) => setState(() => selectedProvider = v!),
                  ),
                  
                  const SizedBox(height: 24),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () => Get.back(),
                          style: OutlinedButton.styleFrom(
                            padding: const EdgeInsets.symmetric(vertical: 14),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                          ),
                          child: const Text('Annuler'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: ElevatedButton(
                          onPressed: () async {
                            Get.back();
                            final success = await controller.acceptDevis(
                              devis.id,
                              provider: selectedProvider,
                              paymentType: paymentType,
                            );
                            if (success) {
                              Get.back(result: true);
                            }
                          },
                          style: ElevatedButton.styleFrom(
                            backgroundColor: _C.success,
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 14),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(12),
                            ),
                          ),
                          child: const Text('Confirmer'),
                        ),
                      ),
                    ],
                  ),
                ],
              );
            },
          ),
        ),
      ),
    );
  }

  void _showRefuseDialog(BuildContext context) {
    Get.dialog(
      Dialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 60,
                height: 60,
                decoration: BoxDecoration(
                  color: _C.dangerLight,
                  borderRadius: BorderRadius.circular(16),
                ),
                child: const Icon(Icons.warning, color: _C.danger, size: 32),
              ),
              const SizedBox(height: 16),
              const Text(
                'Refuser le devis',
                style: TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w700,
                  color: _C.ink,
                ),
              ),
              const SizedBox(height: 8),
              const Text(
                'Êtes-vous sûr de vouloir refuser ce devis ? L\'artisan en sera informé.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 14,
                  color: _C.muted,
                ),
              ),
              const SizedBox(height: 24),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => Get.back(),
                      style: OutlinedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: const Text('Annuler'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: () async {
                        Get.back();
                        final success = await controller.refuseDevis(devisId);
                        if (success) {
                          Get.back(result: false);
                        }
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: _C.danger,
                        foregroundColor: Colors.white,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                      ),
                      child: const Text('Refuser'),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _PendingPaymentButtons extends StatelessWidget {
  final DevisController controller;
  final int devisId;

  const _PendingPaymentButtons({
    required this.controller,
    required this.devisId,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: _C.surface,
        boxShadow: [
          BoxShadow(
            color: _C.ink.withValues(alpha: 0.08),
            blurRadius: 16,
            offset: const Offset(0, -4),
          ),
        ],
      ),
      child: SafeArea(
        child: Obx(
          () => Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (controller.pendingProvider.value != 'virement_bancaire') ...[
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton(
                    onPressed: controller.isSubmitting.value ||
                            controller.isCheckingPayment.value ||
                            !controller.canReopenPendingPayment
                        ? null
                        : () => controller.reopenPendingPayment(),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: _C.primary,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 16),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(12),
                      ),
                    ),
                    child: const Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.open_in_new, size: 20),
                        SizedBox(width: 8),
                        Text(
                          'Ouvrir le paiement',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
                const SizedBox(height: 12),
              ],
              SizedBox(
                width: double.infinity,
                child: OutlinedButton(
                  onPressed: controller.isSubmitting.value ||
                          controller.isCheckingPayment.value
                      ? null
                      : () async {
                          final success = await controller.verifyPendingPayment(
                            devisId,
                            maxAttempts: 1,
                          );
                          if (success) {
                            Get.back(result: true);
                          }
                        },
                  style: OutlinedButton.styleFrom(
                    foregroundColor: _C.primary,
                    side: const BorderSide(color: _C.primary),
                    padding: const EdgeInsets.symmetric(vertical: 16),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                  ),
                  child: controller.isCheckingPayment.value
                      ? const SizedBox(
                          height: 20,
                          width: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Row(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.refresh, size: 20),
                            SizedBox(width: 8),
                            Text(
                              'Vérifier le paiement',
                              style: TextStyle(
                                fontSize: 16,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ],
                        ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ─── Widgets utilitaires ──────────────────────────────────────────────────────
class _LigneCard extends StatelessWidget {
  final DevisLigne ligne;
  const _LigneCard({required this.ligne});

  @override
  Widget build(BuildContext context) {
    final isMo = ligne.type == 'mo';
    final materialDetail = !isMo
        ? '${ligne.resolvedQuantity} x ${_formatFCFA(ligne.resolvedUnitPrice)}'
        : null;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _C.subtle),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(8),
            decoration: BoxDecoration(
              color: isMo ? _C.successLight : _C.warningLight,
              borderRadius: BorderRadius.circular(8),
            ),
            child: Icon(
              isMo ? Icons.build : Icons.category,
              size: 20,
              color: isMo ? _C.success : _C.warning,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  ligne.description,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: _C.ink,
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 4),
                Text(
                  isMo ? 'Main d\'œuvre' : 'Matériaux',
                  style: TextStyle(
                    fontSize: 12,
                    color: isMo ? _C.success : _C.warning,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                if (materialDetail != null) ...[
                  const SizedBox(height: 2),
                  Text(
                    materialDetail,
                    style: const TextStyle(
                      fontSize: 12,
                      color: _C.muted,
                    ),
                  ),
                ],
              ],
            ),
          ),
          Text(
            _formatFCFA(ligne.montant),
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w700,
              color: isMo ? _C.success : _C.warning,
            ),
          ),
        ],
      ),
    );
  }

  String _formatFCFA(int amount) {
    return '${amount.toString().replaceAllMapped(
          RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
          (Match m) => '${m[1]} ',
        )} FCFA';
  }
}

class _JalonCard extends StatelessWidget {
  final DevisJalon jalon;
  const _JalonCard({required this.jalon});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _C.subtle),
      ),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: _C.primaryLight,
              borderRadius: BorderRadius.circular(8),
            ),
            child: Center(
              child: Text(
                '${jalon.ordre}',
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                  color: _C.primary,
                ),
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  jalon.description,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: _C.ink,
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                const SizedBox(height: 4),
                Row(
                  children: [
                    const Icon(Icons.calendar_today, size: 12, color: _C.muted),
                    const SizedBox(width: 4),
                    Text(
                      _formatDate(jalon.dateCible),
                      style: const TextStyle(
                        fontSize: 12,
                        color: _C.muted,
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
          Text(
            _formatFCFA(jalon.montant),
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w700,
              color: _C.primary,
            ),
          ),
        ],
      ),
    );
  }

  String _formatFCFA(int amount) {
    return '${amount.toString().replaceAllMapped(
          RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
          (Match m) => '${m[1]} ',
        )} FCFA';
  }

  String _formatDate(String date) {
    try {
      final dt = DateTime.parse(date);
      return DateFormat('dd/MM/yyyy').format(dt);
    } catch (_) {
      return date;
    }
  }
}

class _RecapRow extends StatelessWidget {
  final String label;
  final String value;
  final Color valueColor;
  final bool isBold;
  final bool isLarge;

  const _RecapRow({
    required this.label,
    required this.value,
    required this.valueColor,
    this.isBold = false,
    this.isLarge = false,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Expanded(
          child: Text(
            label,
            style: TextStyle(
              fontSize: isLarge ? 16 : 14,
              fontWeight: isBold ? FontWeight.w700 : FontWeight.w500,
              color: _C.ink,
            ),
          ),
        ),
        Text(
          value,
          style: TextStyle(
            fontSize: isLarge ? 18 : 14,
            fontWeight: FontWeight.w700,
            color: valueColor,
          ),
        ),
      ],
    );
  }
}

class _ProgressBar extends StatelessWidget {
  final String label;
  final double percentage;
  final Color color;

  const _ProgressBar({
    required this.label,
    required this.percentage,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              label,
              style: const TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: _C.ink,
              ),
            ),
            Text(
              '${percentage.toStringAsFixed(1)}%',
              style: TextStyle(
                fontSize: 12,
                fontWeight: FontWeight.w600,
                color: color,
              ),
            ),
          ],
        ),
        const SizedBox(height: 6),
        ClipRRect(
          borderRadius: BorderRadius.circular(4),
          child: SizedBox(
            height: 8,
            child: LinearProgressIndicator(
              value: percentage / 100,
              backgroundColor: _C.subtle,
              valueColor: AlwaysStoppedAnimation(color),
            ),
          ),
        ),
      ],
    );
  }
}

class _PaymentOption extends StatelessWidget {
  final String label;
  final String value;
  final String groupValue;
  final IconData icon;
  final ValueChanged<String?> onChanged;
  final bool disabled;

  const _PaymentOption({
    required this.label,
    required this.value,
    required this.groupValue,
    required this.icon,
    required this.onChanged,
    this.disabled = false,
  });

  @override
  Widget build(BuildContext context) {
    final isSelected = value == groupValue;
    return GestureDetector(
      onTap: disabled ? null : () => onChanged(value),
      child: Opacity(
        opacity: disabled ? 0.45 : 1.0,
        child: Container(
          padding: const EdgeInsets.all(12),
          decoration: BoxDecoration(
            color: isSelected ? _C.primaryLight : _C.surface,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: isSelected ? _C.primary : _C.subtle,
              width: isSelected ? 2 : 1,
            ),
          ),
          child: Row(
            children: [
              Container(
                width: 20,
                height: 20,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(
                    color: isSelected ? _C.primary : _C.muted,
                    width: 2,
                  ),
                  color: isSelected ? _C.primary : _C.surface,
                ),
                child: isSelected
                    ? const Center(
                        child: Icon(
                          Icons.check,
                          size: 12,
                          color: Colors.white,
                        ),
                      )
                    : null,
              ),
              const SizedBox(width: 12),
              Icon(icon, color: isSelected ? _C.primary : _C.muted, size: 20),
              const SizedBox(width: 8),
              Expanded(
                child: Text(
                  label,
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: isSelected ? FontWeight.w600 : FontWeight.w500,
                    color: isSelected ? _C.primary : _C.ink,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ErrorState extends StatelessWidget {
  final VoidCallback onRetry;
  const _ErrorState({required this.onRetry});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 80,
            height: 80,
            decoration: BoxDecoration(
              color: _C.dangerLight,
              borderRadius: BorderRadius.circular(20),
            ),
            child: const Icon(
              Icons.error_outline,
              size: 40,
              color: _C.danger,
            ),
          ),
          const SizedBox(height: 16),
          const Text(
            'Impossible de charger le devis',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w700,
              color: _C.ink,
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Vérifiez votre connexion et réessayez',
            style: TextStyle(
              fontSize: 14,
              color: _C.muted,
            ),
          ),
          const SizedBox(height: 24),
          ElevatedButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh, size: 18),
            label: const Text('Réessayer'),
            style: ElevatedButton.styleFrom(
              backgroundColor: _C.primary,
              foregroundColor: Colors.white,
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(12),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _GrandsComptesWarningCard extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _C.warningLight,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _C.warning.withValues(alpha: 0.3)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Icon(Icons.warning_amber_rounded, color: _C.warning, size: 24),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Limite Grands Comptes Active',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w700,
                    color: _C.ink,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  'Ce devis dépasse 2 000 000 FCFA. Conformément à la réglementation de sécurité de ProsArtisan, le paiement par Mobile Money est désactivé. Veuillez choisir le Virement Bancaire.',
                  style: TextStyle(
                    fontSize: 13,
                    color: _C.muted,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _PendingVirementInstructionsCard extends StatelessWidget {
  final VirementInstructionsModel? instructions;

  const _PendingVirementInstructionsCard({required this.instructions});

  @override
  Widget build(BuildContext context) {
    if (instructions == null) return const SizedBox.shrink();

    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _C.primary.withValues(alpha: 0.3)),
        boxShadow: [
          BoxShadow(
            color: _C.ink.withValues(alpha: 0.05),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: _C.primaryLight,
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(Icons.account_balance, color: _C.primary, size: 20),
              ),
              const SizedBox(width: 12),
              const Text(
                'Instructions de Virement',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                  color: _C.ink,
                ),
              ),
            ],
          ),
          const Divider(height: 24),
          const Text(
            'Veuillez effectuer un virement bancaire sur le compte séquestre ProsArtisan :',
            style: TextStyle(
              fontSize: 13,
              color: _C.muted,
            ),
          ),
          const SizedBox(height: 16),
          _InstructionDetail(
            label: 'Banque',
            value: instructions!.bankName,
          ),
          const SizedBox(height: 12),
          _InstructionDetail(
            label: 'Titulaire du compte',
            value: instructions!.accountName,
          ),
          const SizedBox(height: 12),
          _InstructionDetail(
            label: 'IBAN',
            value: instructions!.iban,
            canCopy: true,
          ),
          const SizedBox(height: 12),
          _InstructionDetail(
            label: 'Référence du virement (à indiquer obligatoirement)',
            value: instructions!.reference,
            canCopy: true,
            highlighted: true,
          ),
          const SizedBox(height: 16),
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: _C.primaryLight,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Row(
              children: [
                const Icon(Icons.info, color: _C.primary, size: 18),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    'Le traitement d\'un virement bancaire peut prendre de 24h à 48h ouvrables. Cliquez sur "Vérifier le paiement" une fois le virement émis.',
                    style: TextStyle(
                      fontSize: 12,
                      color: _C.primary,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _InstructionDetail extends StatelessWidget {
  final String label;
  final String value;
  final bool canCopy;
  final bool highlighted;

  const _InstructionDetail({
    required this.label,
    required this.value,
    this.canCopy = false,
    this.highlighted = false,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w600,
            color: _C.muted,
          ),
        ),
        const SizedBox(height: 4),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
          decoration: BoxDecoration(
            color: highlighted ? _C.primaryLight : _C.bg,
            borderRadius: BorderRadius.circular(8),
            border: highlighted
                ? Border.all(color: _C.primary.withValues(alpha: 0.3))
                : null,
          ),
          child: Row(
            children: [
              Expanded(
                child: SelectableText(
                  value,
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: highlighted ? FontWeight.w700 : FontWeight.w600,
                    color: highlighted ? _C.primary : _C.ink,
                  ),
                ),
              ),
              if (canCopy) ...[
                const SizedBox(width: 8),
                GestureDetector(
                  onTap: () {
                    Clipboard.setData(ClipboardData(text: value));
                    Get.snackbar(
                      'Copié !',
                      '$value copié dans le presse-papiers.',
                      snackPosition: SnackPosition.TOP,
                      duration: const Duration(seconds: 2),
                    );
                  },
                  child: Icon(
                    Icons.copy,
                    size: 16,
                    color: highlighted ? _C.primary : _C.muted,
                  ),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }
}
