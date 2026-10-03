import 'package:frontend_flutter/core/utils/json_readers.dart';

/// Un recouvrement d'une dette de litige : prélèvement sur les gains ou
/// règlement direct par Mobile Money.
class DisputeDebtEntry {
  const DisputeDebtEntry({
    required this.montant,
    required this.sourceLabel,
    this.createdAt,
  });

  final int montant;
  final String sourceLabel;
  final DateTime? createdAt;

  factory DisputeDebtEntry.fromJson(Map<String, dynamic> json) {
    return DisputeDebtEntry(
      montant: readInt(json['montant']) ?? 0,
      sourceLabel: readString(json['source_label']) ?? 'Remboursement',
      createdAt:
          DateTime.tryParse(readString(json['created_at']) ?? '')?.toLocal(),
    );
  }
}

/// Somme due à ProsArtisan après un litige de commande. Tant qu'elle est en
/// cours, le compte du fournisseur ou du livreur est bloqué.
class DisputeDebt {
  const DisputeDebt({
    required this.id,
    required this.montant,
    required this.recouvre,
    required this.restant,
    required this.statut,
    required this.statutLabel,
    this.orderId,
    this.entries = const [],
  });

  final int id;
  final int? orderId;
  final int montant;
  final int recouvre;
  final int restant;
  final String statut;
  final String statutLabel;
  final List<DisputeDebtEntry> entries;

  bool get isOpen => statut == 'en_cours';

  factory DisputeDebt.fromJson(Map<String, dynamic> json) {
    final statut = readString(json['statut']) ?? 'en_cours';

    return DisputeDebt(
      id: readInt(json['id']) ?? 0,
      orderId: readInt(json['order_id']),
      montant: readInt(json['montant']) ?? 0,
      recouvre: readInt(json['montant_recouvre']) ?? 0,
      restant: readInt(json['restant']) ?? 0,
      statut: statut,
      statutLabel: readString(json['statut_label']) ?? statut,
      entries:
          readMapList(json['entries']).map(DisputeDebtEntry.fromJson).toList(),
    );
  }
}
