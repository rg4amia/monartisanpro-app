import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/storage/storage_service.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/dispute_debt_model.dart';
import '../controllers/dispute_debt_controller.dart';

/// Bandeau « Remboursement dû » de l'accueil du fournisseur et du livreur.
/// Invisible sans dette en cours ; il possède son contrôleur.
class DisputeDebtBanner extends StatefulWidget {
  const DisputeDebtBanner({this.controller, super.key});

  /// Injectable pour les tests.
  final DisputeDebtController? controller;

  @override
  State<DisputeDebtBanner> createState() => _DisputeDebtBannerState();
}

class _DisputeDebtBannerState extends State<DisputeDebtBanner> {
  late final DisputeDebtController controller;

  @override
  void initState() {
    super.initState();
    controller = widget.controller ?? (DisputeDebtController()..onInit());
  }

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      final due = controller.totalDue;
      if (due <= 0) return const SizedBox.shrink();

      return Container(
        margin: const EdgeInsets.only(bottom: 16),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: AppColors.dangerSoft,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: AppColors.danger),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Remboursement dû : ${Formatters.fcfa(due)}',
              style: const TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w800,
                color: AppColors.danger,
              ),
            ),
            const SizedBox(height: 4),
            const Text(
              'Un litige de commande a été tranché en faveur du client. Votre compte est bloqué jusqu\'au règlement de cette somme.',
              style: TextStyle(fontSize: 13, color: AppColors.textPrimary),
            ),
            const SizedBox(height: 10),
            ElevatedButton(
              onPressed: () async {
                await Get.toNamed(Routes.disputeDebts);
                await controller.load();
              },
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.danger,
                foregroundColor: Colors.white,
              ),
              child: const Text('Régler maintenant'),
            ),
          ],
        ),
      );
    });
  }
}

/// Bandeau réservé aux rôles qui peuvent être désignés responsables d'un
/// litige de commande.
class DisputeDebtBannerForRole extends StatelessWidget {
  const DisputeDebtBannerForRole({super.key});

  @override
  Widget build(BuildContext context) {
    final role = StorageService.getRole();
    if (role != 'fournisseur' && role != 'livreur' && role != 'driver') {
      return const SizedBox.shrink();
    }

    return const DisputeDebtBanner();
  }
}

/// « Remboursements dus » : dettes de litige, leur historique de
/// recouvrement et le règlement direct par Wave ou Orange Money.
class DisputeDebtScreen extends StatefulWidget {
  const DisputeDebtScreen({this.controller, super.key});

  final DisputeDebtController? controller;

  @override
  State<DisputeDebtScreen> createState() => _DisputeDebtScreenState();
}

class _DisputeDebtScreenState extends State<DisputeDebtScreen> {
  static final DateFormat _day = DateFormat('dd/MM/yyyy', 'fr_FR');

  late final DisputeDebtController controller;

  @override
  void initState() {
    super.initState();
    controller = widget.controller ?? (DisputeDebtController()..onInit());
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Remboursements dus'),
        backgroundColor: AppColors.surface,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
      ),
      body: Obx(() {
        if (controller.isLoading.value && controller.debts.isEmpty) {
          return const Center(child: CircularProgressIndicator());
        }

        final error = controller.errorMsg.value;
        final message = controller.paymentMessage.value;

        return RefreshIndicator(
          onRefresh: controller.load,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
            children: [
              if (error != null)
                _Notice(
                  text: error,
                  color: AppColors.danger,
                  action: TextButton(
                    onPressed: controller.load,
                    child: const Text('Réessayer'),
                  ),
                ),
              if (message != null)
                _Notice(text: message, color: AppColors.primary),
              if (controller.debts.isEmpty && error == null)
                const Padding(
                  padding: EdgeInsets.only(top: 72),
                  child: Column(
                    children: [
                      Icon(
                        Icons.verified_outlined,
                        size: 48,
                        color: AppColors.success,
                      ),
                      SizedBox(height: 12),
                      Text(
                        'Aucun remboursement dû',
                        style: TextStyle(fontWeight: FontWeight.w700),
                      ),
                      SizedBox(height: 6),
                      Text(
                        'Une somme apparaît ici si un litige de commande est tranché contre vous.',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: 13,
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
              for (final debt in controller.debts)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: _DebtCard(
                    debt: debt,
                    paying: controller.payingDebtId.value == debt.id,
                    busy: controller.payingDebtId.value != null,
                    onPay: (provider) =>
                        controller.pay(debt, provider: provider),
                    formatDay: _day.format,
                  ),
                ),
            ],
          ),
        );
      }),
    );
  }
}

class _DebtCard extends StatelessWidget {
  const _DebtCard({
    required this.debt,
    required this.paying,
    required this.busy,
    required this.onPay,
    required this.formatDay,
  });

  final DisputeDebt debt;
  final bool paying;
  final bool busy;
  final void Function(String provider) onPay;
  final String Function(DateTime) formatDay;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  'Litige de la commande #${debt.orderId ?? '—'}',
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w800,
                    color: AppColors.textPrimary,
                  ),
                ),
              ),
              Text(
                debt.statutLabel,
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w800,
                  color: debt.isOpen ? AppColors.danger : AppColors.success,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text('Somme due : ${Formatters.fcfa(debt.montant)}'),
          Text('Déjà remboursé : ${Formatters.fcfa(debt.recouvre)}'),
          if (debt.isOpen)
            Text(
              'Reste à régler : ${Formatters.fcfa(debt.restant)}',
              style: const TextStyle(fontWeight: FontWeight.w800),
            ),
          for (final entry in debt.entries)
            Padding(
              padding: const EdgeInsets.only(top: 6),
              child: Text(
                '${entry.createdAt != null ? '${formatDay(entry.createdAt!)} · ' : ''}'
                '${entry.sourceLabel} : ${Formatters.fcfa(entry.montant)}',
                style: const TextStyle(
                  fontSize: 12.5,
                  color: AppColors.textSecondary,
                ),
              ),
            ),
          if (debt.isOpen) ...[
            const SizedBox(height: 12),
            if (paying)
              const Center(child: CircularProgressIndicator())
            else
              Row(
                children: [
                  Expanded(
                    child: ElevatedButton(
                      onPressed: busy ? null : () => onPay('wave'),
                      child: const Text('Régler par Wave'),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: OutlinedButton(
                      onPressed: busy ? null : () => onPay('orange_money'),
                      child: const Text('Orange Money'),
                    ),
                  ),
                ],
              ),
            const SizedBox(height: 6),
            const Text(
              'La somme est aussi prélevée sur vos prochains gains tant qu\'elle n\'est pas réglée.',
              style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
            ),
          ],
        ],
      ),
    );
  }
}

class _Notice extends StatelessWidget {
  const _Notice({required this.text, required this.color, this.action});

  final String text;
  final Color color;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          Expanded(
            child: Text(
              text,
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: color,
              ),
            ),
          ),
          if (action != null) action!,
        ],
      ),
    );
  }
}
