import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/supplier_cashout_model.dart';
import '../controllers/supplier_cashout_controller.dart';

class SupplierCashoutScreen extends StatelessWidget {
  const SupplierCashoutScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.put(SupplierCashoutController());

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Mes virements & Cash-Out'),
        backgroundColor: AppColors.surface,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
      ),
      body: Obx(() {
        if (controller.isLoading.value && controller.cashouts.isEmpty) {
          return const Center(child: CircularProgressIndicator());
        }

        final stats = controller.stats.value;

        return RefreshIndicator(
          color: AppColors.success,
          onRefresh: controller.load,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              // 1. Carte Principale Solde & Trésorerie
              _BalanceHeroCard(
                stats: stats,
                onRequestTap: () => _openCashoutSheet(context, controller),
              ),
              const SizedBox(height: 16),

              // 2. Bannière de garantie J+1
              _GuaranteeBanner(),
              const SizedBox(height: 24),

              // 3. Titre Historique
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'Historique des virements',
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w800,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  Text(
                    '${controller.cashouts.length} demande(s)',
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),

              if (controller.cashouts.isEmpty)
                const _EmptyHistoryCard()
              else
                ...controller.cashouts.map(
                  (c) => Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: _CashoutItemCard(cashout: c),
                  ),
                ),
            ],
          ),
        );
      }),
    );
  }

  void _openCashoutSheet(
    BuildContext context,
    SupplierCashoutController controller,
  ) {
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (ctx) => _CashoutRequestSheet(controller: controller),
    );
  }
}

class _BalanceHeroCard extends StatelessWidget {
  const _BalanceHeroCard({
    required this.stats,
    required this.onRequestTap,
  });

  final SupplierCashoutStatsModel stats;
  final VoidCallback onRequestTap;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [
            AppColors.success,
            AppColors.success.withValues(alpha: 0.85),
          ],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: AppColors.success.withValues(alpha: 0.3),
            blurRadius: 15,
            offset: const Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          const Row(
            children: [
              Icon(Icons.account_balance_wallet_outlined, color: Colors.white, size: 20),
              SizedBox(width: 8),
              Text(
                'Solde disponible au retrait',
                style: TextStyle(
                  color: Colors.white70,
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Text(
            Formatters.fcfa(stats.availableBalance),
            style: const TextStyle(
              color: Colors.white,
              fontSize: 32,
              fontWeight: FontWeight.w900,
              letterSpacing: -0.5,
            ),
          ),
          const SizedBox(height: 16),
          const Divider(color: Colors.white24, height: 1),
          const SizedBox(height: 14),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'En cours (J+1)',
                    style: TextStyle(color: Colors.white70, fontSize: 12),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    Formatters.fcfa(stats.pendingAmount),
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  const Text(
                    'Total retiré',
                    style: TextStyle(color: Colors.white70, fontSize: 12),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    Formatters.fcfa(stats.totalWithdrawn),
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ),
            ],
          ),
          const SizedBox(height: 18),
          ElevatedButton.icon(
            onPressed: stats.availableBalance >= 1000 ? onRequestTap : null,
            style: ElevatedButton.styleFrom(
              backgroundColor: Colors.white,
              foregroundColor: AppColors.success,
              disabledBackgroundColor: Colors.white38,
              disabledForegroundColor: Colors.white70,
              elevation: 0,
              padding: const EdgeInsets.symmetric(vertical: 14),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
              ),
            ),
            icon: const Icon(Icons.arrow_upward_rounded),
            label: const Text(
              'Demander un virement',
              style: TextStyle(fontWeight: FontWeight.w800, fontSize: 15),
            ),
          ),
        ],
      ),
    );
  }
}

class _GuaranteeBanner extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.border),
      ),
      child: const Row(
        children: [
          Icon(Icons.verified_outlined, color: AppColors.accent, size: 28),
          SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  'Virement J+1 Garanti ProsArtisan',
                  style: TextStyle(
                    fontWeight: FontWeight.w700,
                    fontSize: 13,
                    color: AppColors.textPrimary,
                  ),
                ),
                SizedBox(height: 2),
                Text(
                  'Toute livraison de matériaux confirmée ou demande validée est décaissée sous 24h ouvrées.',
                  style: TextStyle(
                    fontSize: 11,
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
}

class _CashoutItemCard extends StatelessWidget {
  const _CashoutItemCard({required this.cashout});

  final SupplierCashoutModel cashout;

  Color get _statusColor {
    if (cashout.isCompleted) return AppColors.success;
    if (cashout.isApproved) return AppColors.primary;
    if (cashout.isRejected) return AppColors.danger;
    return AppColors.accent;
  }

  IconData get _modeIcon {
    if (cashout.modeRetrait == 'virement_bancaire') return Icons.account_balance;
    if (cashout.modeRetrait == 'orange_money') return Icons.phone_android;
    return Icons.waves;
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Row(
                children: [
                  Icon(_modeIcon, size: 16, color: AppColors.textSecondary),
                  const SizedBox(width: 6),
                  Text(
                    cashout.reference,
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 14,
                      color: AppColors.textPrimary,
                    ),
                  ),
                ],
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                decoration: BoxDecoration(
                  color: _statusColor.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  cashout.statutLabel,
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: _statusColor,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    'Net à percevoir : ${Formatters.fcfa(cashout.montantNet)}',
                    style: const TextStyle(
                      fontWeight: FontWeight.w800,
                      fontSize: 15,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    'Brut : ${Formatters.fcfa(cashout.montantBrut)} • Com : ${Formatters.fcfa(cashout.montantCommission)}',
                    style: const TextStyle(
                      fontSize: 12,
                      color: AppColors.textSecondary,
                    ),
                  ),
                ],
              ),
              Text(
                cashout.modeRetraitLabel,
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w600,
                  color: AppColors.textPrimary,
                ),
              ),
            ],
          ),
          if (cashout.bankName != null && cashout.bankName!.isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              'Banque : ${cashout.bankName} • Compte : ${cashout.bankAccountNumber ?? "N/A"}',
              style: const TextStyle(
                fontSize: 11,
                color: AppColors.textSecondary,
              ),
            ),
          ],
        ],
      ),
    );
  }
}

class _EmptyHistoryCard extends StatelessWidget {
  const _EmptyHistoryCard();

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(32),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.border),
      ),
      child: const Center(
        child: Column(
          children: [
            Icon(Icons.history_outlined, size: 48, color: AppColors.textSecondary),
            SizedBox(height: 12),
            Text(
              'Aucun virement enregistré',
              style: TextStyle(
                fontWeight: FontWeight.w700,
                fontSize: 15,
                color: AppColors.textPrimary,
              ),
            ),
            SizedBox(height: 4),
            Text(
              'Vos demandes de retrait et paiements J+1 apparaîtront ici.',
              textAlign: TextAlign.center,
              style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
            ),
          ],
        ),
      ),
    );
  }
}

class _CashoutRequestSheet extends StatelessWidget {
  const _CashoutRequestSheet({required this.controller});

  final SupplierCashoutController controller;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: const BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      padding: EdgeInsets.fromLTRB(
        20,
        20,
        20,
        MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      child: SingleChildScrollView(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          mainAxisSize: MainAxisSize.min,
          children: [
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                const Text(
                  'Demande de virement',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w800,
                    color: AppColors.textPrimary,
                  ),
                ),
                IconButton(
                  icon: const Icon(Icons.close),
                  onPressed: () => Navigator.of(context).pop(),
                ),
              ],
            ),
            const SizedBox(height: 12),

            // Saisie du montant
            TextField(
              controller: controller.amountController,
              keyboardType: TextInputType.number,
              decoration: InputDecoration(
                labelText: 'Montant à retirer (FCFA)',
                hintText: 'Ex: 50 000',
                border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
                prefixIcon: const Icon(Icons.payments_outlined),
              ),
            ),
            const SizedBox(height: 10),

            // Boutons rapides
            Row(
              children: [
                _QuickButton(label: '25%', onTap: () => controller.setQuickPercent(0.25)),
                const SizedBox(width: 8),
                _QuickButton(label: '50%', onTap: () => controller.setQuickPercent(0.50)),
                const SizedBox(width: 8),
                _QuickButton(label: '100% (Tout)', onTap: () => controller.setQuickPercent(1.0)),
              ],
            ),
            const SizedBox(height: 16),

            // Sélection du moyen de retrait
            const Text(
              'Mode de versement',
              style: TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
            ),
            const SizedBox(height: 8),
            Obx(
              () => Wrap(
                spacing: 8,
                children: [
                  ChoiceChip(
                    label: const Text('Wave CI'),
                    selected: controller.selectedMode.value == 'wave',
                    onSelected: (s) => controller.selectedMode.value = 'wave',
                  ),
                  ChoiceChip(
                    label: const Text('Orange Money'),
                    selected: controller.selectedMode.value == 'orange_money',
                    onSelected: (s) => controller.selectedMode.value = 'orange_money',
                  ),
                  ChoiceChip(
                    label: const Text('Virement bancaire'),
                    selected: controller.selectedMode.value == 'virement_bancaire',
                    onSelected: (s) => controller.selectedMode.value = 'virement_bancaire',
                  ),
                ],
              ),
            ),
            const SizedBox(height: 16),

            // Champs spécifiques virement bancaire
            Obx(() {
              if (controller.selectedMode.value != 'virement_bancaire') {
                return const SizedBox.shrink();
              }
              return Column(
                children: [
                  TextField(
                    controller: controller.bankNameController,
                    decoration: InputDecoration(
                      labelText: 'Nom de la banque',
                      hintText: 'Ex: NSIA, Ecobank, SGBCI...',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
                      prefixIcon: const Icon(Icons.account_balance_outlined),
                    ),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: controller.bankAccountController,
                    decoration: InputDecoration(
                      labelText: 'Numéro de compte / RIB (CI...)',
                      hintText: 'Ex: CI0920100100234567890123',
                      border: OutlineInputBorder(borderRadius: BorderRadius.circular(14)),
                      prefixIcon: const Icon(Icons.numbers_outlined),
                    ),
                  ),
                  const SizedBox(height: 16),
                ],
              );
            }),

            // Récapitulatif dynamique
            Obx(() {
              final net = controller.calculatedNet.value;
              final com = controller.calculatedCommission.value;
              if (net <= 0) return const SizedBox.shrink();

              return Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: AppColors.background,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    Text(
                      'Frais ProsArtisan (2.5%) : ${Formatters.fcfa(com)}',
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.textSecondary,
                      ),
                    ),
                    Text(
                      'Net : ${Formatters.fcfa(net)}',
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w800,
                        color: AppColors.success,
                      ),
                    ),
                  ],
                ),
              );
            }),
            const SizedBox(height: 20),

            // Bouton de validation
            Obx(
              () => ElevatedButton(
                onPressed: controller.isSubmitting.value
                    ? null
                    : () async {
                        final success = await controller.submitCashout();
                        if (success && context.mounted) {
                          Navigator.of(context).pop();
                        }
                      },
                style: ElevatedButton.styleFrom(
                  backgroundColor: AppColors.success,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
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
                    : const Text(
                        'Confirmer le retrait',
                        style: TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 15,
                        ),
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _QuickButton extends StatelessWidget {
  const _QuickButton({required this.label, required this.onTap});

  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: OutlinedButton(
        onPressed: onTap,
        style: OutlinedButton.styleFrom(
          padding: const EdgeInsets.symmetric(vertical: 8),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(10)),
        ),
        child: Text(label, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w700)),
      ),
    );
  }
}
