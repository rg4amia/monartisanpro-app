import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../../core/theme/app_colors.dart';
import '../../../../core/utils/error_handler.dart';
import '../../../../data/models/history_models.dart';
import '../../../../data/repositories/history_repository.dart';

/// « Historique de la mission » : chaque changement d'état, du plus récent au
/// plus ancien. Repliée par défaut ; l'historique n'est demandé au serveur
/// qu'à l'ouverture.
class MissionStateHistorySection extends StatefulWidget {
  const MissionStateHistorySection({
    required this.missionId,
    this.repository,
    this.initiallyOpen = false,
    super.key,
  });

  final int missionId;
  final HistoryRepository? repository;

  /// Ouvre la section et charge l'historique dès l'affichage.
  final bool initiallyOpen;

  @override
  State<MissionStateHistorySection> createState() =>
      _MissionStateHistorySectionState();
}

class _MissionStateHistorySectionState
    extends State<MissionStateHistorySection> {
  static final DateFormat _format = DateFormat('dd/MM/yyyy HH:mm', 'fr_FR');

  late final HistoryRepository _repo = widget.repository ?? HistoryRepository();

  late bool _open = widget.initiallyOpen;

  @override
  void initState() {
    super.initState();
    if (_open) _load();
  }

  bool _loading = false;
  String? _error;
  List<MissionStateLine>? _lines;

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });

    try {
      final lines = await _repo.missionTimeline(widget.missionId);
      if (!mounted) return;
      setState(() => _lines = lines);
    } catch (e) {
      if (!mounted) return;
      setState(() => _error = ErrorHandler.getErrorMessage(e));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _toggle() {
    setState(() => _open = !_open);
    if (_open && _lines == null && !_loading) _load();
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          InkWell(
            borderRadius: BorderRadius.circular(18),
            onTap: _toggle,
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Row(
                children: [
                  const Icon(Icons.history_rounded, color: AppColors.primary),
                  const SizedBox(width: 10),
                  const Expanded(
                    child: Text(
                      'Historique de la mission',
                      style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                        color: AppColors.textPrimary,
                      ),
                    ),
                  ),
                  Icon(
                    _open
                        ? Icons.expand_less_rounded
                        : Icons.expand_more_rounded,
                    color: AppColors.textSecondary,
                  ),
                ],
              ),
            ),
          ),
          if (_open)
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
              child: _body(),
            ),
        ],
      ),
    );
  }

  Widget _body() {
    if (_loading) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 12),
        child: Center(child: CircularProgressIndicator()),
      );
    }

    if (_error != null) {
      return Row(
        children: [
          Expanded(
            child: Text(
              _error!,
              style: const TextStyle(fontSize: 13, color: AppColors.danger),
            ),
          ),
          TextButton(onPressed: _load, child: const Text('Réessayer')),
        ],
      );
    }

    final lines = _lines ?? const <MissionStateLine>[];
    if (lines.isEmpty) {
      return const Text(
        'Aucun changement d\'état enregistré pour cette mission.',
        style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (final line in lines)
          Padding(
            padding: const EdgeInsets.only(bottom: 12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '${line.fromLabel} → ${line.toLabel}',
                  style: const TextStyle(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w700,
                    color: AppColors.textPrimary,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  [
                    line.unknownDate || line.at == null
                        ? 'Date non conservée'
                        : _format.format(line.at!),
                    line.actorLabel,
                  ].join(' · '),
                  style: const TextStyle(
                    fontSize: 12,
                    color: AppColors.textSecondary,
                  ),
                ),
                if (line.reason != null && line.reason!.isNotEmpty)
                  Text(
                    line.reason!,
                    style: const TextStyle(
                      fontSize: 12.5,
                      color: AppColors.textSecondary,
                    ),
                  ),
                if (line.reconstituted)
                  const Text(
                    'Reconstitué d\'après les paiements, étapes et litiges enregistrés',
                    style: TextStyle(
                      fontSize: 11.5,
                      fontStyle: FontStyle.italic,
                      color: AppColors.textSecondary,
                    ),
                  ),
              ],
            ),
          ),
      ],
    );
  }
}
