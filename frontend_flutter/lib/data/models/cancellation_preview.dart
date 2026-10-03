import 'package:frontend_flutter/core/utils/json_readers.dart';

/// Coût de l'annulation d'une mission, établi par le serveur : rien de ce que
/// l'application affiche ici n'est calculé par elle.
class CancellationPreview {
  const CancellationPreview({
    required this.allowed,
    required this.funded,
    required this.escrow,
    required this.penaltyRate,
    required this.penalty,
    required this.refund,
    this.reason,
  });

  /// Faux quand le chantier a commencé : [reason] dit pourquoi.
  final bool allowed;

  /// Vrai si un séquestre est constitué : l'annulation entraîne une pénalité.
  final bool funded;

  final int escrow;
  final double penaltyRate;
  final int penalty;
  final int refund;
  final String? reason;

  factory CancellationPreview.fromJson(Map<dynamic, dynamic> json) {
    return CancellationPreview(
      allowed: readBool(json['allowed']) ?? false,
      funded: readBool(json['funded']) ?? false,
      escrow: readInt(json['escrow']) ?? 0,
      penaltyRate: readDouble(json['penalty_rate']) ?? 0,
      penalty: readInt(json['penalty']) ?? 0,
      refund: readInt(json['refund']) ?? 0,
      reason: readString(json['reason']),
    );
  }
}
