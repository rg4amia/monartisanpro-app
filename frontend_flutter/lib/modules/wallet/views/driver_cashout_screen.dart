import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/payout_model.dart';
import '../controllers/driver_cashout_controller.dart';

/// Retrait des gains de course vers Mobile Money ou compte bancaire.
class DriverCashoutScreen extends StatefulWidget {
  const DriverCashoutScreen({super.key, this.controller});

  /// Injectable pour les tests ; sinon résolu via GetX.
  final DriverCashoutController? controller;

  @override
  State<DriverCashoutScreen> createState() => _DriverCashoutScreenState();
}

class _DriverCashoutScreenState extends State<DriverCashoutScreen> {
  late final DriverCashoutController controller;
  final _formKey = GlobalKey<FormState>();
  final _amount = TextEditingController();
  final _phone = TextEditingController();
  final _bank = TextEditingController();
  final _account = TextEditingController();

  static const _modes = {
    'wave': 'Wave',
    'orange_money': 'Orange Money',
    'virement_bancaire': 'Virement bancaire',
  };

  @override
  void initState() {
    super.initState();
    controller = widget.controller ??
        (Get.isRegistered<DriverCashoutController>()
            ? Get.find<DriverCashoutController>()
            : Get.put(DriverCashoutController()));
    _amount.addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _amount.dispose();
    _phone.dispose();
    _bank.dispose();
    _account.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    final message = await controller.submit(
      amount: int.parse(_amount.text),
      beneficiaryPhone: _phone.text.trim(),
      bankName: _bank.text.trim(),
      bankAccountNumber: _account.text.trim(),
    );

    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          message ?? controller.errorMsg.value ?? 'Échec de la demande.',
        ),
        backgroundColor: message != null ? AppColors.success : AppColors.danger,
      ),
    );
    if (message != null) _amount.clear();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Retirer mes gains'),
        backgroundColor: Colors.white,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
      ),
      body: Obx(() {
        if (controller.isLoading.value && controller.cashouts.isEmpty) {
          return const Center(child: CircularProgressIndicator());
        }

        final stats = controller.stats.value;
        final amount = int.tryParse(_amount.text);

        return RefreshIndicator(
          onRefresh: controller.load,
          child: ListView(
            padding: const EdgeInsets.all(20),
            children: [
              _BalanceHeader(stats: stats),
              const SizedBox(height: 20),
              Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    TextFormField(
                      controller: _amount,
                      keyboardType: TextInputType.number,
                      inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                      decoration: const InputDecoration(
                        labelText: 'Montant à retirer (FCFA)',
                        border: OutlineInputBorder(),
                      ),
                      validator: (v) =>
                          controller.validateAmount(int.tryParse(v ?? '')),
                    ),
                    const SizedBox(height: 14),
                    Wrap(
                      spacing: 8,
                      children: [
                        for (final entry in _modes.entries)
                          ChoiceChip(
                            label: Text(entry.value),
                            selected: controller.mode.value == entry.key,
                            onSelected: (_) =>
                                controller.mode.value = entry.key,
                          ),
                      ],
                    ),
                    const SizedBox(height: 14),
                    if (controller.mode.value == 'virement_bancaire') ...[
                      TextFormField(
                        controller: _bank,
                        decoration: const InputDecoration(
                          labelText: 'Banque',
                          border: OutlineInputBorder(),
                        ),
                        validator: (v) => (v ?? '').trim().isEmpty
                            ? 'Indiquez votre banque.'
                            : null,
                      ),
                      const SizedBox(height: 12),
                      TextFormField(
                        controller: _account,
                        decoration: const InputDecoration(
                          labelText: 'Numéro de compte (IBAN / RIB)',
                          border: OutlineInputBorder(),
                        ),
                        validator: (v) => (v ?? '').trim().isEmpty
                            ? 'Indiquez votre numéro de compte.'
                            : null,
                      ),
                    ] else
                      TextFormField(
                        controller: _phone,
                        keyboardType: TextInputType.phone,
                        decoration: const InputDecoration(
                          labelText: 'Numéro de réception (facultatif)',
                          helperText:
                              'Par défaut : votre numéro de reversement Mobile Money.',
                          hintText: '+225XXXXXXXXXX',
                          border: OutlineInputBorder(),
                        ),
                        validator: (v) {
                          final value = (v ?? '').trim();
                          if (value.isEmpty) return null;
                          return RegExp(r'^\+225[0-9]{10}$').hasMatch(value)
                              ? null
                              : 'Format attendu : +225 suivi de 10 chiffres.';
                        },
                      ),
                    const SizedBox(height: 12),
                    if (amount != null && amount > 0)
                      Text(
                        stats.commissionRate > 0
                            ? 'Vous recevrez ${Formatters.fcfa(controller.netFor(amount))} '
                                '(frais ${(stats.commissionRate * 100).toStringAsFixed(1)} %).'
                            : 'Vous recevrez ${Formatters.fcfa(amount)} — aucun frais de retrait.',
                        style: const TextStyle(
                          fontSize: 12.5,
                          color: AppColors.textSecondary,
                        ),
                      ),
                    const SizedBox(height: 16),
                    FilledButton.icon(
                      onPressed: controller.isSubmitting.value ? null : _submit,
                      icon: controller.isSubmitting.value
                          ? const SizedBox(
                              width: 16,
                              height: 16,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.send_rounded),
                      label: const Text('Demander le retrait'),
                      style: FilledButton.styleFrom(
                        backgroundColor: AppColors.driver,
                        padding: const EdgeInsets.symmetric(vertical: 14),
                      ),
                    ),
                  ],
                ),
              ),
              const SizedBox(height: 28),
              const Text(
                'Mes demandes de retrait',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                  color: AppColors.textPrimary,
                ),
              ),
              const SizedBox(height: 10),
              if (controller.cashouts.isEmpty)
                const Text(
                  'Aucune demande pour le moment. Vos retraits apparaîtront ici avec leur suivi.',
                  style:
                      TextStyle(fontSize: 12.5, color: AppColors.textSecondary),
                )
              else
                for (final cashout in controller.cashouts)
                  _CashoutTile(cashout: cashout),
            ],
          ),
        );
      }),
    );
  }
}

class _BalanceHeader extends StatelessWidget {
  const _BalanceHeader({required this.stats});

  final DriverCashoutStats stats;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: AppColors.driverSoft,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.driver.withValues(alpha: 0.2)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Solde retirable',
            style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
          ),
          const SizedBox(height: 6),
          Text(
            Formatters.fcfa(stats.availableBalance),
            style: const TextStyle(
              fontSize: 28,
              fontWeight: FontWeight.w900,
              color: AppColors.textPrimary,
            ),
          ),
          if (stats.pendingAmount > 0) ...[
            const SizedBox(height: 6),
            Text(
              '${Formatters.fcfa(stats.pendingAmount)} en cours de retrait',
              style:
                  const TextStyle(fontSize: 12, color: AppColors.textSecondary),
            ),
          ],
          const SizedBox(height: 4),
          Text(
            'Déjà retiré : ${Formatters.fcfa(stats.totalWithdrawn)}',
            style:
                const TextStyle(fontSize: 12, color: AppColors.textSecondary),
          ),
        ],
      ),
    );
  }
}

class _CashoutTile extends StatelessWidget {
  const _CashoutTile({required this.cashout});

  final DriverCashoutModel cashout;

  Color get _color {
    if (cashout.payout?.isFailed ?? false) return AppColors.danger;
    switch (cashout.statut) {
      case 'complete':
        return AppColors.success;
      case 'rejete':
        return AppColors.danger;
      default:
        return Colors.orange.shade800;
    }
  }

  String get _statusText {
    if (cashout.payout?.isFailed ?? false) {
      return 'Virement échoué — relance en cours';
    }
    return cashout.statutLabel;
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  cashout.reference,
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  _statusText,
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: _color,
                  ),
                ),
                if (cashout.statut == 'rejete' && cashout.notes != null)
                  Text(
                    cashout.notes!,
                    style: const TextStyle(
                      fontSize: 11.5,
                      color: AppColors.textSecondary,
                    ),
                  ),
              ],
            ),
          ),
          Text(
            Formatters.fcfa(cashout.montantNet),
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w800,
              color: AppColors.textPrimary,
            ),
          ),
        ],
      ),
    );
  }
}
