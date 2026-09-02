import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

import '../../../core/storage/storage_service.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/transaction_model.dart';
import '../controllers/wallet_controller.dart';

class WalletScreen extends StatelessWidget {
  const WalletScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final controller = Get.isRegistered<WalletController>()
        ? Get.find<WalletController>()
        : Get.put(WalletController());

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Portefeuille'),
        backgroundColor: Colors.white,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
      ),
      body: Obx(() {
        if (controller.isLoading.value) {
          return const Center(child: CircularProgressIndicator());
        }

        return RefreshIndicator(
          onRefresh: controller.refresh,
          color: AppColors.primary,
          child: CustomScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.all(20.0),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      _BalanceCards(controller: controller),
                      const SizedBox(height: 24),
                      const Text(
                        'Historique des transactions',
                        style: TextStyle(
                          fontSize: 18,
                          fontWeight: FontWeight.w700,
                          color: AppColors.textPrimary,
                        ),
                      ),
                      const SizedBox(height: 16),
                    ],
                  ),
                ),
              ),
              SliverPadding(
                padding: const EdgeInsets.symmetric(horizontal: 20.0),
                sliver: controller.transactions.isEmpty
                    ? SliverToBoxAdapter(
                        child: Center(
                          child: Text(
                            'Aucune transaction trouvée.',
                            style: TextStyle(color: AppColors.textSecondary),
                          ),
                        ),
                      )
                    : SliverList(
                        delegate: SliverChildBuilderDelegate(
                          (context, index) {
                            final transaction = controller.transactions[index];
                            return _TransactionTile(transaction: transaction);
                          },
                          childCount: controller.transactions.length,
                        ),
                      ),
              ),
              const SliverToBoxAdapter(child: SizedBox(height: 40)),
            ],
          ),
        );
      }),
    );
  }
}

class _BalanceCards extends StatelessWidget {
  final WalletController controller;

  const _BalanceCards({required this.controller});

  @override
  Widget build(BuildContext context) {
    final String role = StorageService.getRole() ?? 'driver';

    return Column(
      children: [
        Container(
          width: double.infinity,
          padding: const EdgeInsets.all(24),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              colors: [AppColors.primary, Color(0xFF1E293B)],
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
            ),
            borderRadius: BorderRadius.circular(24),
            boxShadow: [
              BoxShadow(
                color: AppColors.primary.withValues(alpha: 0.35),
                blurRadius: 20,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  Row(
                    children: [
                      Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.15),
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(
                          Icons.account_balance_wallet_rounded,
                          color: Colors.white,
                          size: 20,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Text(
                        role == 'driver'
                            ? 'Gains Disponibles (Livreur)'
                            : 'Gains Disponibles (MO)',
                        style: const TextStyle(
                          color: Colors.white70,
                          fontWeight: FontWeight.w600,
                          fontSize: 14,
                          letterSpacing: 0.5,
                        ),
                      ),
                    ],
                  ),
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.15),
                      borderRadius: BorderRadius.circular(20),
                    ),
                    child: const Row(
                      children: [
                        Icon(
                          Icons.check_circle_rounded,
                          color: AppColors.success,
                          size: 12,
                        ),
                        SizedBox(width: 4),
                        Text(
                          'Actif',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 10,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 24),
              Text(
                Formatters.fcfa(controller.walletMo.value),
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w900,
                  fontSize: 36,
                  letterSpacing: -0.5,
                ),
              ),
              const SizedBox(height: 10),
              const Text(
                'Fonds disponibles pour transfert Mobile Money immédiat.',
                style: TextStyle(
                  color: Colors.white60,
                  fontSize: 12,
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 16),
        if (role == 'driver')
          _buildEscrowCard(
            title: 'Séquestre Livraisons (à percevoir)',
            value: controller.walletEscrowLivreur.value,
            icon: Icons.local_shipping_outlined,
            color: AppColors.driver,
            bgColor: AppColors.driverSoft,
            tooltip: 'Libéré après validation du code de réception client',
          )
        else if (role == 'artisan')
          _buildEscrowCard(
            title: 'Séquestre Chantiers (Jalons en cours)',
            value: controller.walletMateriaux.value,
            icon: Icons.lock_outline_rounded,
            color: Colors.amber.shade700,
            bgColor: Colors.amber.shade50,
            tooltip: 'Fonds débloqués après validation OTP client par jalon',
          ),
      ],
    );
  }

  Widget _buildEscrowCard({
    required String title,
    required int value,
    required IconData icon,
    required Color color,
    required Color bgColor,
    required String tooltip,
  }) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: AppColors.border),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 10,
            offset: const Offset(0, 4),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: bgColor,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Icon(icon, color: color, size: 24),
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    color: AppColors.textSecondary,
                    fontWeight: FontWeight.w600,
                    fontSize: 13,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  Formatters.fcfa(value),
                  style: const TextStyle(
                    color: AppColors.textPrimary,
                    fontWeight: FontWeight.w800,
                    fontSize: 20,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  tooltip,
                  style: TextStyle(
                    color: AppColors.textSecondary.withValues(alpha: 0.8),
                    fontSize: 11,
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

class _TransactionTile extends StatelessWidget {
  final dynamic transaction;

  const _TransactionTile({required this.transaction});

  @override
  Widget build(BuildContext context) {
    final String type = transaction.type ?? '';
    final String statut = (transaction.statut ?? '').toString().toLowerCase();
    final bool isCredit =
        type == 'liberation_jalon' || type == 'acompte' || type == 'credit';
    final Color color = isCredit ? AppColors.success : const Color(0xFFE11D48);
    final String sign = isCredit ? '+' : '-';

    DateTime? date;
    try {
      date = DateTime.parse(transaction.createdAt);
    } catch (_) {}

    final String? missionDesc =
        transaction is TransactionModel ? transaction.missionDescription : null;
    final String? clientName =
        transaction is TransactionModel ? transaction.clientName : null;
    final int? missionId =
        transaction is TransactionModel ? transaction.missionId : null;
    final String provider =
        (transaction.provider ?? '').toString().toLowerCase();

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.border),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.02),
            blurRadius: 6,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(
                  isCredit
                      ? Icons.arrow_downward_rounded
                      : Icons.arrow_upward_rounded,
                  color: color,
                  size: 20,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            _formatType(type),
                            style: const TextStyle(
                              fontWeight: FontWeight.w700,
                              fontSize: 14,
                              color: AppColors.textPrimary,
                            ),
                          ),
                        ),
                        Text(
                          '$sign${Formatters.fcfa(transaction.montant)}',
                          style: TextStyle(
                            fontWeight: FontWeight.w800,
                            fontSize: 15,
                            color: color,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      (() {
                        if (date == null) return '';
                        try {
                          return DateFormat("dd MMM yyyy 'à' HH:mm", 'fr_FR')
                              .format(date);
                        } catch (_) {
                          try {
                            return DateFormat('dd/MM/yyyy HH:mm').format(date);
                          } catch (e) {
                            return date.toString();
                          }
                        }
                      })(),
                      style: const TextStyle(
                        fontSize: 12,
                        color: AppColors.textSecondary,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (missionDesc != null && missionDesc.isNotEmpty ||
              clientName != null && clientName.isNotEmpty) ...[
            const SizedBox(height: 10),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
              decoration: BoxDecoration(
                color: const Color(0xFFF9FAFB),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0xFFF3F4F6)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (missionId != null ||
                      (missionDesc != null && missionDesc.isNotEmpty))
                    Row(
                      children: [
                        const Icon(
                          Icons.handyman_outlined,
                          size: 13,
                          color: AppColors.textSecondary,
                        ),
                        const SizedBox(width: 6),
                        Expanded(
                          child: Text(
                            'Mission ${missionId != null ? '#$missionId ' : ''}: ${missionDesc ?? ''}',
                            style: const TextStyle(
                              fontSize: 11.5,
                              fontWeight: FontWeight.w600,
                              color: AppColors.textPrimary,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                      ],
                    ),
                  if (clientName != null && clientName.isNotEmpty) ...[
                    const SizedBox(height: 3),
                    Row(
                      children: [
                        const Icon(
                          Icons.person_outline,
                          size: 13,
                          color: AppColors.textSecondary,
                        ),
                        const SizedBox(width: 6),
                        Text(
                          'Client : $clientName',
                          style: const TextStyle(
                            fontSize: 11.5,
                            color: AppColors.textSecondary,
                          ),
                        ),
                      ],
                    ),
                  ],
                ],
              ),
            ),
          ],
          const SizedBox(height: 8),
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              if (provider.isNotEmpty)
                Container(
                  padding:
                      const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                  decoration: BoxDecoration(
                    color: _getProviderColor(provider).withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(6),
                    border: Border.all(
                      color: _getProviderColor(provider).withValues(alpha: 0.3),
                    ),
                  ),
                  child: Text(
                    _formatProvider(provider),
                    style: TextStyle(
                      fontSize: 10.5,
                      fontWeight: FontWeight.w700,
                      color: _getProviderColor(provider),
                    ),
                  ),
                )
              else
                const SizedBox.shrink(),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: statut == 'confirme' || statut == 'paid'
                      ? AppColors.success.withValues(alpha: 0.1)
                      : Colors.orange.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(6),
                ),
                child: Text(
                  statut == 'confirme' || statut == 'paid'
                      ? 'Confirmé / Reversé'
                      : 'En traitement',
                  style: TextStyle(
                    fontSize: 10.5,
                    fontWeight: FontWeight.w700,
                    color: statut == 'confirme' || statut == 'paid'
                        ? AppColors.success
                        : Colors.orange.shade800,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  String _formatType(String type) {
    switch (type) {
      case 'liberation_jalon':
        return 'Jalon validé & Reversé';
      case 'paiement_fournisseur':
        return 'Paiement fournisseur';
      case 'acompte':
        return 'Acompte séquestre';
      case 'remboursement':
        return 'Remboursement';
      case 'credit':
        return 'Micro-crédit octroyé';
      default:
        return type.replaceAll('_', ' ').capitalizeFirst ?? type;
    }
  }

  String _formatProvider(String provider) {
    switch (provider) {
      case 'wave':
        return 'Wave CI';
      case 'orange_money':
        return 'Orange Money';
      case 'mtn_money':
        return 'MTN MoMo';
      case 'moov_money':
        return 'Moov Money';
      case 'virement_bancaire':
        return 'Virement Bancaire';
      default:
        return provider.capitalizeFirst ?? provider;
    }
  }

  Color _getProviderColor(String provider) {
    switch (provider) {
      case 'wave':
        return const Color(0xFF00A3FF);
      case 'orange_money':
        return const Color(0xFFFF7900);
      case 'mtn_money':
        return const Color(0xFFEAB308);
      case 'moov_money':
        return const Color(0xFF005BA6);
      default:
        return AppColors.primary;
    }
  }
}
