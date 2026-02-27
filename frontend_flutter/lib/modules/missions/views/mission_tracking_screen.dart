import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../../../data/models/mission_model.dart';
import '../controllers/missions_controller.dart';

class MissionTrackingScreen extends StatefulWidget {
  const MissionTrackingScreen({super.key});

  @override
  State<MissionTrackingScreen> createState() => _MissionTrackingScreenState();
}

class _MissionTrackingScreenState extends State<MissionTrackingScreen> {
  final _otpCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    final mission = Get.arguments as MissionModel?;
    if (mission != null) {
      Get.find<MissionsController>().loadMission(mission.id);
    }
  }

  @override
  void dispose() {
    _otpCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final c = Get.find<MissionsController>();

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(title: const Text('Suivi de mission')),
      body: Obx(() {
        final mission = c.currentMission.value;
        if (c.isLoading.value || mission == null) {
          return const Center(child: CircularProgressIndicator());
        }
        return RefreshIndicator(
          onRefresh: () => c.loadMission(mission.id),
          child: SingleChildScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Referent banner
                if (mission.needsReferent) _referentBanner(),
                // Status stepper
                _StatusStepper(status: mission.status),
                const SizedBox(height: 20),
                // Escrow wallets
                _EscrowCard(mission: mission),
                const SizedBox(height: 20),
                // Jalons
                const Text('Jalons',
                    style: TextStyle(
                        fontSize: 17,
                        fontWeight: FontWeight.w700,
                        color: AppColors.textPrimary)),
                const SizedBox(height: 12),
                ...c.jalons.map((jalon) => _JalonTile(
                      jalon: jalon,
                      onSubmit: () => c.submitJalon(jalon.id),
                      onRequestOtp: () => c.requestOtp(jalon.id),
                      onValidateOtp: (otp) => c.validateOtp(jalon.id, otp),
                    )),
                const SizedBox(height: 20),
                // Actions
                if (mission.status == 'en_cours')
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: () => Get.toNamed(Routes.litige,
                              arguments: mission),
                          icon: const Icon(Icons.warning_outlined,
                              color: AppColors.danger),
                          label: const Text('Signaler',
                              style: TextStyle(color: AppColors.danger)),
                          style: OutlinedButton.styleFrom(
                            side: const BorderSide(color: AppColors.danger),
                          ),
                        ),
                      ),
                    ],
                  ),
              ],
            ),
          ),
        );
      }),
    );
  }

  Widget _referentBanner() {
    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.warning.withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.warning.withValues(alpha: 0.3)),
      ),
      child: const Row(
        children: [
          Icon(Icons.gavel, color: AppColors.warning, size: 20),
          SizedBox(width: 10),
          Expanded(
            child: Text(
              'Validation physique du Référent de zone requise (mission > 2 000 000 FCFA)',
              style: TextStyle(
                  fontSize: 13,
                  color: AppColors.warning,
                  fontWeight: FontWeight.w500),
            ),
          ),
        ],
      ),
    );
  }
}

class _StatusStepper extends StatelessWidget {
  final String status;
  static const _steps = [
    ('en_attente', 'Devis'),
    ('financee', 'Financée'),
    ('en_cours', 'En cours'),
    ('terminee', 'Terminée'),
  ];

  const _StatusStepper({required this.status});

  int get _currentIndex {
    final idx = _steps.indexWhere((s) => s.$1 == status);
    return idx < 0 ? 0 : idx;
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.border),
      ),
      child: Row(
        children: List.generate(_steps.length * 2 - 1, (i) {
          if (i.isOdd) {
            return Expanded(
              child: Container(
                height: 2,
                color: i ~/ 2 < _currentIndex
                    ? AppColors.success
                    : AppColors.border,
              ),
            );
          }
          final step = i ~/ 2;
          final done = step < _currentIndex;
          final active = step == _currentIndex;
          return Column(
            children: [
              Container(
                width: 28,
                height: 28,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: done
                      ? AppColors.success
                      : active
                          ? AppColors.primary
                          : AppColors.border,
                ),
                child: Center(
                  child: done
                      ? const Icon(Icons.check, size: 14, color: Colors.white)
                      : Text('${step + 1}',
                          style: TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w600,
                              color: active ? Colors.white : AppColors.textSecondary)),
                ),
              ),
              const SizedBox(height: 4),
              Text(_steps[step].$2,
                  style: TextStyle(
                      fontSize: 9,
                      fontWeight: active ? FontWeight.w700 : FontWeight.normal,
                      color: active
                          ? AppColors.primary
                          : AppColors.textSecondary)),
            ],
          );
        }),
      ),
    );
  }
}

class _EscrowCard extends StatelessWidget {
  final MissionModel mission;
  const _EscrowCard({required this.mission});

  @override
  Widget build(BuildContext context) {
    final matPct = (mission.ratioMateriaux * 100).round();
    final moPct = 100 - matPct;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Séquestre',
              style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w700,
                  color: AppColors.textPrimary)),
          const SizedBox(height: 14),
          Row(
            children: [
              Expanded(
                child: _WalletBar(
                  label: 'Matériaux',
                  amount: mission.montantMateriaux,
                  pct: matPct,
                  color: AppColors.accent,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: _WalletBar(
                  label: 'Main d\'œuvre',
                  amount: mission.montantMo,
                  pct: moPct,
                  color: AppColors.primary,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

class _WalletBar extends StatelessWidget {
  final String label;
  final int amount;
  final int pct;
  final Color color;

  const _WalletBar({
    required this.label,
    required this.amount,
    required this.pct,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(label,
            style: const TextStyle(
                fontSize: 12, color: AppColors.textSecondary)),
        const SizedBox(height: 4),
        Text(Formatters.fcfa(amount),
            style: TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w700,
                color: color)),
        const SizedBox(height: 6),
        ClipRRect(
          borderRadius: BorderRadius.circular(4),
          child: LinearProgressIndicator(
            value: pct / 100,
            backgroundColor: color.withValues(alpha: 0.15),
            valueColor: AlwaysStoppedAnimation(color),
            minHeight: 6,
          ),
        ),
        const SizedBox(height: 4),
        Text('$pct% du total',
            style: const TextStyle(
                fontSize: 11, color: AppColors.textMuted)),
      ],
    );
  }
}

class _JalonTile extends StatelessWidget {
  final dynamic jalon;
  final VoidCallback onSubmit;
  final VoidCallback onRequestOtp;
  final Function(String) onValidateOtp;

  const _JalonTile({
    required this.jalon,
    required this.onSubmit,
    required this.onRequestOtp,
    required this.onValidateOtp,
  });

  @override
  Widget build(BuildContext context) {
    final statut = jalon.statut as String;
    final color = {
          'en_attente': AppColors.textMuted,
          'soumis': AppColors.warning,
          'valide': AppColors.info,
          'paye': AppColors.success,
        }[statut] ??
        AppColors.textMuted;

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.card,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 28,
                height: 28,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: color.withValues(alpha: 0.15),
                  border: Border.all(color: color),
                ),
                child: Center(
                  child: Text('${jalon.ordre}',
                      style: TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          color: color)),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(jalon.description as String,
                    style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w600,
                        color: AppColors.textPrimary)),
              ),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: color.withValues(alpha: 0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Text(
                  Formatters.jalonStatus(statut),
                  style: TextStyle(
                      fontSize: 11,
                      color: color,
                      fontWeight: FontWeight.w500),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(Formatters.fcfa(jalon.montant as int),
              style: const TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w700,
                  color: AppColors.primary)),
          if (statut == 'en_attente')
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: ElevatedButton(
                onPressed: onSubmit,
                style: ElevatedButton.styleFrom(
                    minimumSize: const Size(double.infinity, 40)),
                child: const Text('Soumettre le jalon'),
              ),
            ),
          if (statut == 'soumis')
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: ElevatedButton(
                onPressed: onRequestOtp,
                style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.warning,
                    minimumSize: const Size(double.infinity, 40)),
                child: const Text('Demander OTP validation'),
              ),
            ),
          if (statut == 'valide')
            Padding(
              padding: const EdgeInsets.only(top: 8),
              child: Column(
                children: [
                  TextFormField(
                    keyboardType: TextInputType.number,
                    maxLength: 4,
                    textAlign: TextAlign.center,
                    decoration: const InputDecoration(
                      hintText: 'Code OTP (4 chiffres)',
                      counterText: '',
                    ),
                    onChanged: (v) {
                      if (v.length == 4) onValidateOtp(v);
                    },
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}
