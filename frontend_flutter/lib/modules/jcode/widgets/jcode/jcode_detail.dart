import 'package:flutter/material.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/formatters.dart';
import '../../../../data/models/jcode_model.dart';
import 'jcode_item_tiles.dart';
import 'jcode_section_card.dart';
import 'materials_photo_section.dart';

class JcodeDetail extends StatelessWidget {
  final JcodeModel jcode;

  const JcodeDetail({super.key, required this.jcode});

  @override
  Widget build(BuildContext context) {
    final isActif = jcode.statut == 'actif';

    return ListView(
      padding: const EdgeInsets.all(24),
      children: [
        JcodeSectionCard(
          child: Column(
            children: [
              if (isActif)
                QrImageView(
                  data: jcode.id.toString(),
                  version: QrVersions.auto,
                  size: 190,
                )
              else
                const Icon(Icons.qr_code, size: 180, color: AppColors.border),
              const SizedBox(height: 18),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                decoration: BoxDecoration(
                  color: AppColors.primary.withValues(alpha: 0.08),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Text(
                  jcode.code,
                  style: const TextStyle(
                    fontSize: 28,
                    fontWeight: FontWeight.bold,
                    letterSpacing: 4,
                    color: AppColors.primary,
                  ),
                ),
              ),
              const SizedBox(height: 10),
              JcodeStatusBadge(statut: jcode.statut),
            ],
          ),
        ),
        const SizedBox(height: 16),
        if (jcode.isMultiSupplier) ...[
          JcodeSectionCard(
            child: Row(
              children: [
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: AppColors.secondary.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: const Icon(
                    Icons.alt_route_rounded,
                    color: AppColors.secondary,
                    size: 28,
                  ),
                ),
                const SizedBox(width: 14),
                const Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Bon Matériaux Multi-Quincailleries',
                        style: TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 16,
                          color: AppColors.secondary,
                        ),
                      ),
                      SizedBox(height: 4),
                      Text(
                        'Valable pour retrait partiel ou total dans n\'importe quelle quincaillerie agréée.',
                        style: TextStyle(
                          fontSize: 12,
                          color: AppColors.textSecondary,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 16),
        ],
        JcodeSectionCard(
          child: Column(
            children: [
              JcodeDetailRow(
                label: 'Mission',
                value: '#${jcode.missionId}',
              ),
              const Divider(height: 20),
              JcodeDetailRow(
                label: 'Montant initial',
                value: Formatters.fcfa(jcode.montant),
                valueStyle: const TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 16,
                  color: AppColors.textPrimary,
                ),
              ),
              if (jcode.montantConsomme > 0) ...[
                const Divider(height: 20),
                JcodeDetailRow(
                  label: 'Montant retiré',
                  value: Formatters.fcfa(jcode.montantConsomme),
                  valueStyle: const TextStyle(
                    fontWeight: FontWeight.w600,
                    fontSize: 16,
                    color: AppColors.warning,
                  ),
                ),
              ],
              const Divider(height: 20),
              JcodeDetailRow(
                label: 'Solde restant',
                value: Formatters.fcfa(jcode.montantRestant),
                valueStyle: TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 18,
                  color: jcode.montantRestant > 0
                      ? AppColors.success
                      : AppColors.textSecondary,
                ),
              ),
              const SizedBox(height: 12),
              ClipRRect(
                borderRadius: BorderRadius.circular(6),
                child: LinearProgressIndicator(
                  value: jcode.montant > 0
                      ? (jcode.montantConsomme / jcode.montant).clamp(0.0, 1.0)
                      : 1.0,
                  backgroundColor: AppColors.border,
                  valueColor: AlwaysStoppedAnimation<Color>(
                    jcode.montantRestant == 0
                        ? AppColors.info
                        : AppColors.success,
                  ),
                  minHeight: 8,
                ),
              ),
              const Divider(height: 20),
              JcodeDetailRow(
                label: 'Expire le',
                value: Formatters.dateTime(jcode.expiresAt),
              ),
              if (jcode.scannedAt != null) ...[
                const Divider(height: 20),
                JcodeDetailRow(
                  label: 'Dernier scan le',
                  value: Formatters.dateTime(jcode.scannedAt!),
                ),
              ],
            ],
          ),
        ),
        if (jcode.supplier != null) ...[
          const SizedBox(height: 16),
          JcodeSectionCard(
            child: ListTile(
              contentPadding: EdgeInsets.zero,
              leading: Container(
                padding: const EdgeInsets.all(10),
                decoration: BoxDecoration(
                  color: AppColors.success.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: const Icon(Icons.storefront, color: AppColors.success),
              ),
              title: Text(
                jcode.supplier!.shopName,
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
              subtitle: Text(Formatters.phone(jcode.supplier!.phone)),
            ),
          ),
        ],
        if (jcode.items.isNotEmpty) ...[
          const SizedBox(height: 16),
          JcodeSectionCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Articles demandés',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
                const SizedBox(height: 12),
                ...jcode.items.map((item) => JcodeServedItemTile(item: item)),
              ],
            ),
          ),
        ],
        if (jcode.redemptions.isNotEmpty) ...[
          const SizedBox(height: 16),
          JcodeSectionCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    const Icon(
                      Icons.receipt_long,
                      color: AppColors.primary,
                      size: 20,
                    ),
                    const SizedBox(width: 8),
                    Text(
                      'Historique des retraits (${jcode.redemptions.length})',
                      style: const TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w700,
                        color: AppColors.textPrimary,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                ...jcode.redemptions.map((redemption) {
                  return Container(
                    margin: const EdgeInsets.only(bottom: 10),
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: AppColors.surface,
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: AppColors.border),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Expanded(
                              child: Text(
                                redemption.fournisseur?.shopName ??
                                    'Quincaillerie partenaire',
                                style: const TextStyle(
                                  fontWeight: FontWeight.bold,
                                  fontSize: 14,
                                ),
                              ),
                            ),
                            Text(
                              Formatters.fcfa(redemption.montant),
                              style: const TextStyle(
                                fontWeight: FontWeight.bold,
                                color: AppColors.primary,
                                fontSize: 15,
                              ),
                            ),
                          ],
                        ),
                        if (redemption.scannedAt != null) ...[
                          const SizedBox(height: 4),
                          Text(
                            Formatters.dateTime(redemption.scannedAt!),
                            style: const TextStyle(
                              fontSize: 12,
                              color: AppColors.textSecondary,
                            ),
                          ),
                        ],
                      ],
                    ),
                  );
                }),
              ],
            ),
          ),
        ],
        if (jcode.isUsed) ...[
          const SizedBox(height: 16),
          JcodeSectionCard(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Preuve de réception chantier',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
                const SizedBox(height: 12),
                MaterialsPhotoSection(jcode: jcode),
              ],
            ),
          ),
        ],
        const SizedBox(height: 16),
        JcodeSectionCard(
          child: ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.phone_in_talk, color: AppColors.primary),
            title: const Text('Code USSD'),
            subtitle: Text(
              jcode.ussdCode ?? '*144#',
              style: const TextStyle(
                fontWeight: FontWeight.bold,
                fontSize: 18,
                letterSpacing: 2,
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class JcodeStatusDot extends StatelessWidget {
  final String label;
  final Color color;

  const JcodeStatusDot({super.key, required this.label, required this.color});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontSize: 12,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

class JcodeStatusBadge extends StatelessWidget {
  final String statut;

  const JcodeStatusBadge({super.key, required this.statut});

  @override
  Widget build(BuildContext context) {
    final colors = {
      'actif': AppColors.success,
      'partiellement_utilise': AppColors.warning,
      'utilise': AppColors.info,
      'expire': AppColors.danger,
    };
    final color = colors[statut] ?? AppColors.textSecondary;
    return Chip(
      label: Text(
        Formatters.jcodeStatus(statut),
        style: const TextStyle(color: Colors.white, fontSize: 12),
      ),
      backgroundColor: color,
    );
  }
}

class JcodeDetailRow extends StatelessWidget {
  final String label;
  final String value;
  final TextStyle? valueStyle;

  const JcodeDetailRow({
    super.key,
    required this.label,
    required this.value,
    this.valueStyle,
  });

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(label, style: const TextStyle(color: AppColors.textSecondary)),
        Text(
          value,
          style: valueStyle ?? const TextStyle(fontWeight: FontWeight.w600),
        ),
      ],
    );
  }
}
