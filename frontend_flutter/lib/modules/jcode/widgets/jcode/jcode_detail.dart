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
        JcodeSectionCard(
          child: Column(
            children: [
              JcodeDetailRow(
                label: 'Mission',
                value: '#${jcode.missionId}',
              ),
              const Divider(height: 20),
              JcodeDetailRow(
                label: 'Montant',
                value: Formatters.fcfa(jcode.montant),
                valueStyle: const TextStyle(
                  fontWeight: FontWeight.bold,
                  fontSize: 18,
                  color: AppColors.success,
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
                  label: 'Scanné le',
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
