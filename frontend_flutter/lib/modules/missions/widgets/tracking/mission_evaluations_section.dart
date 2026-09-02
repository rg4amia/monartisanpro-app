import 'dart:async';

import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../../app/routes/app_routes.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../data/models/mission_model.dart';
import '../../../../data/repositories/evaluation_repository.dart';
import 'section_container.dart';

/// Section d'évaluation multi-acteurs (client, mission terminée) : charge la
/// liste des intervenants via l'API (fallback artisan par défaut) et propose
/// une carte de notation par acteur.
class MissionEvaluationsSection extends StatefulWidget {
  const MissionEvaluationsSection({
    required this.mission,
    required this.onEvaluated,
    super.key,
  });

  final MissionModel mission;
  final VoidCallback onEvaluated;

  @override
  State<MissionEvaluationsSection> createState() =>
      _MissionEvaluationsSectionState();
}

class _MissionEvaluationsSectionState extends State<MissionEvaluationsSection> {
  final EvaluationRepository _evaluationRepo = EvaluationRepository();
  bool _isLoading = true;
  List<Map<String, dynamic>> _actors = [];

  @override
  void initState() {
    super.initState();
    _loadActors();
  }

  Future<void> _loadActors() async {
    setState(() => _isLoading = true);
    final data = await _evaluationRepo.getMissionActors(widget.mission.id);
    if (!mounted) return;

    if (data != null &&
        data['actors'] is List &&
        (data['actors'] as List).isNotEmpty) {
      setState(() {
        _actors = List<Map<String, dynamic>>.from(data['actors'] as List);
        _isLoading = false;
      });
    } else {
      // Fallback : artisan principal par défaut.
      setState(() {
        _actors = [
          {
            'id': widget.mission.artisanId,
            'name': widget.mission.artisanName ?? 'Artisan',
            'role': 'artisan',
            'role_label': 'Artisan',
            'subtitle': 'Artisan principal de la mission',
            'is_evaluated': false,
            'evaluation': null,
          },
        ];
        _isLoading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return SectionContainer(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: const Color(0xFFFEF3C7),
                  borderRadius: BorderRadius.circular(10),
                ),
                child: const Icon(
                  Icons.star_rounded,
                  size: 20,
                  color: Color(0xFFD97706),
                ),
              ),
              const SizedBox(width: 10),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Évaluations des intervenants',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    Text(
                      'Partagez votre avis pour valoriser le travail bien fait',
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
          const SizedBox(height: 16),
          if (_isLoading)
            const Center(
              child: Padding(
                padding: EdgeInsets.all(20),
                child: CircularProgressIndicator(),
              ),
            )
          else if (_actors.isEmpty)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: const Color(0xFFF9FAFB),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: AppColors.border),
              ),
              child: const Text(
                'Aucun intervenant à évaluer.',
                style: TextStyle(color: AppColors.textSecondary, fontSize: 13),
              ),
            )
          else
            ListView.separated(
              shrinkWrap: true,
              physics: const NeverScrollableScrollPhysics(),
              itemCount: _actors.length,
              separatorBuilder: (_, __) => const SizedBox(height: 12),
              itemBuilder: (context, index) {
                final actor = _actors[index];
                return _ActorEvaluationCard(
                  actor: actor,
                  onRate: () async {
                    final res = await Get.toNamed(
                      Routes.rating,
                      arguments: <String, dynamic>{
                        'missionId': widget.mission.id,
                        'evalueId': actor['id'],
                        'targetName': actor['name'] ?? 'Intervenant',
                        'targetRole': actor['role'] ?? 'artisan',
                        'targetSubtitle': actor['subtitle'] ?? '',
                      },
                    );
                    if (res == true) {
                      unawaited(_loadActors());
                      widget.onEvaluated();
                    }
                  },
                );
              },
            ),
        ],
      ),
    );
  }
}

class _ActorEvaluationCard extends StatelessWidget {
  const _ActorEvaluationCard({
    required this.actor,
    required this.onRate,
  });

  final Map<String, dynamic> actor;
  final VoidCallback onRate;

  @override
  Widget build(BuildContext context) {
    final bool isEvaluated = actor['is_evaluated'] == true;
    final String role = actor['role'] ?? 'artisan';
    final String name = actor['name'] ?? 'Intervenant';
    final String roleLabel = actor['role_label'] ??
        (role == 'artisan'
            ? 'Artisan'
            : role == 'livreur'
                ? 'Livreur'
                : 'Fournisseur');
    final String subtitle = actor['subtitle'] ?? '';
    final Map<String, dynamic>? eval = actor['evaluation'] != null
        ? Map<String, dynamic>.from(actor['evaluation'] as Map)
        : null;
    final int note = eval?['note'] as int? ?? 0;
    final String? comment = eval?['commentaire'] as String?;

    final Color roleColor = role == 'artisan'
        ? const Color(0xFFE67E22)
        : role == 'livreur'
            ? const Color(0xFFF1C40F)
            : const Color(0xFF10B981);

    final IconData roleIcon = role == 'artisan'
        ? Icons.handyman_rounded
        : role == 'livreur'
            ? Icons.local_shipping_rounded
            : Icons.storefront_rounded;

    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(
          color: isEvaluated ? const Color(0xFFBBF7D0) : AppColors.border,
        ),
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
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: roleColor.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(roleIcon, size: 22, color: roleColor),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Flexible(
                          child: Text(
                            name,
                            style: const TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w700,
                              color: AppColors.textPrimary,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        const SizedBox(width: 6),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 6,
                            vertical: 2,
                          ),
                          decoration: BoxDecoration(
                            color: roleColor.withValues(alpha: 0.1),
                            borderRadius: BorderRadius.circular(6),
                          ),
                          child: Text(
                            roleLabel,
                            style: TextStyle(
                              fontSize: 10,
                              fontWeight: FontWeight.w700,
                              color: roleColor,
                            ),
                          ),
                        ),
                      ],
                    ),
                    if (subtitle.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(
                        subtitle,
                        style: const TextStyle(
                          fontSize: 12,
                          color: AppColors.textSecondary,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          if (isEvaluated) ...[
            _EvaluatedBadge(note: note),
            if (comment != null && comment.isNotEmpty) ...[
              const SizedBox(height: 6),
              Padding(
                padding: const EdgeInsets.only(left: 4),
                child: Text(
                  '« $comment »',
                  style: const TextStyle(
                    fontSize: 12,
                    fontStyle: FontStyle.italic,
                    color: AppColors.textSecondary,
                  ),
                ),
              ),
            ],
          ] else
            SizedBox(
              width: double.infinity,
              child: ElevatedButton.icon(
                onPressed: onRate,
                icon: const Icon(Icons.star_outline_rounded, size: 16),
                label: Text(
                  role == 'artisan'
                      ? 'Noter l\'artisan'
                      : role == 'livreur'
                          ? 'Noter le livreur'
                          : 'Noter la quincaillerie',
                  style: const TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                style: ElevatedButton.styleFrom(
                  backgroundColor: roleColor,
                  foregroundColor: Colors.white,
                  padding: const EdgeInsets.symmetric(vertical: 10),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(10),
                  ),
                  elevation: 0,
                ),
              ),
            ),
        ],
      ),
    );
  }
}

class _EvaluatedBadge extends StatelessWidget {
  const _EvaluatedBadge({required this.note});

  final int note;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      decoration: BoxDecoration(
        color: const Color(0xFFF0FDF4),
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: const Color(0xFFDCFCE7)),
      ),
      child: Row(
        children: [
          const Icon(Icons.check_circle, size: 16, color: Color(0xFF16A34A)),
          const SizedBox(width: 6),
          const Text(
            'Avis enregistré : ',
            style: TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w600,
              color: Color(0xFF15803D),
            ),
          ),
          Row(
            children: List.generate(5, (i) {
              return Icon(
                i < note ? Icons.star_rounded : Icons.star_outline_rounded,
                size: 16,
                color: const Color(0xFFF59E0B),
              );
            }),
          ),
          const Spacer(),
          Text(
            '$note/5',
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w800,
              color: Color(0xFFD97706),
            ),
          ),
        ],
      ),
    );
  }
}
