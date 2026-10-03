import 'package:frontend_flutter/core/utils/json_readers.dart';

/// Une page d'un historique paginé par le serveur.
class HistoryPage<T> {
  const HistoryPage({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    required this.total,
  });

  final List<T> items;
  final int currentPage;
  final int lastPage;
  final int total;

  bool get hasMore => currentPage < lastPage;

  /// Lit l'enveloppe `{data: [...], meta: {...}}`. Une réponse sans liste lève
  /// une `FormatException` : une panne ne passe jamais pour un historique vide.
  factory HistoryPage.fromResponse(
    dynamic body,
    T Function(Map<String, dynamic>) fromJson,
  ) {
    final rows = readDataList(body);
    final meta = readMap(readMap(body)?['meta']) ?? const {};

    return HistoryPage<T>(
      items: rows.map(fromJson).toList(),
      currentPage: readInt(meta['current_page']) ?? 1,
      lastPage: readInt(meta['last_page']) ?? 1,
      total: readInt(meta['total']) ?? rows.length,
    );
  }
}

DateTime? _date(dynamic value) {
  final text = readString(value);
  return text == null ? null : DateTime.tryParse(text)?.toLocal();
}

/// Un changement d'état d'une mission.
class MissionStateLine {
  const MissionStateLine({
    required this.id,
    required this.fromLabel,
    required this.toLabel,
    required this.reconstituted,
    required this.unknownDate,
    this.actorName,
    this.actorRole,
    this.reason,
    this.at,
  });

  final int id;
  final String fromLabel;
  final String toLabel;
  final String? actorName;
  final String? actorRole;
  final String? reason;
  final bool reconstituted;
  final bool unknownDate;
  final DateTime? at;

  /// Auteur du changement : son nom et son rôle, le rôle seul pour un agent
  /// ProsArtisan, « Automatique » quand personne n'a agi.
  String get actorLabel {
    if (actorRole == null) {
      return reconstituted ? 'Auteur non conservé' : 'Automatique';
    }
    return actorName == null ? actorRole! : '$actorName ($actorRole)';
  }

  factory MissionStateLine.fromJson(Map<String, dynamic> json) {
    final user = readMap(json['user']);

    return MissionStateLine(
      id: readInt(json['id']) ?? 0,
      fromLabel: readString(json['from_state_label']) ??
          readString(json['from_state']) ??
          '—',
      toLabel: readString(json['to_state_label']) ??
          readString(json['to_state']) ??
          '—',
      actorName: readString(user?['name']),
      actorRole: user == null
          ? null
          : (readString(json['role_label']) ?? readString(user['role'])),
      reason: readString(json['reason']),
      reconstituted: readBool(json['reconstituted']) ?? false,
      unknownDate: readBool(json['unknown_date']) ?? false,
      at: _date(json['transitioned_at']),
    );
  }
}

const Map<String, String> _litigeStatusLabels = {
  'ouvert': 'Ouvert',
  'en_cours': 'En cours',
  'resolu': 'Résolu',
};

const Map<String, String> _litigeDecisionLabels = {
  'client': 'En faveur du client',
  'artisan': 'En faveur de l\'artisan',
  'mixte': 'Responsabilité partagée',
  'gel': 'Fonds gelés, visite demandée',
};

String litigeStatusLabel(String statut) =>
    _litigeStatusLabels[statut] ?? statut;

String? litigeDecisionLabel(String? decision) =>
    decision == null ? null : (_litigeDecisionLabels[decision] ?? decision);

/// Un litige dans la liste « Mes litiges » du client ou de l'artisan.
class LitigeSummary {
  const LitigeSummary({
    required this.id,
    required this.missionId,
    required this.motif,
    required this.statut,
    this.decision,
    this.createdAt,
    this.resolvedAt,
  });

  final int id;
  final int missionId;
  final String motif;
  final String statut;
  final String? decision;
  final DateTime? createdAt;
  final DateTime? resolvedAt;

  bool get isResolved => statut == 'resolu';
  String get statutLabel => litigeStatusLabel(statut);
  String? get decisionLabel => litigeDecisionLabel(decision);

  factory LitigeSummary.fromJson(Map<String, dynamic> json) {
    return LitigeSummary(
      id: readInt(json['id']) ?? 0,
      missionId: readInt(json['missionId']) ?? 0,
      motif: readString(json['motif']) ?? 'Litige',
      statut: readString(json['statut']) ?? 'ouvert',
      decision: readString(json['decision']),
      createdAt: _date(json['createdAt']),
      resolvedAt: _date(json['resoluAt']),
    );
  }
}

/// Résumé d'un chantier pour le Référent : jamais de nom ni de téléphone.
class ReferentMissionSummary {
  const ReferentMissionSummary({
    required this.id,
    required this.description,
    required this.montantTotal,
    required this.statusLabel,
    this.address,
  });

  final int id;
  final String description;
  final String? address;
  final int montantTotal;
  final String statusLabel;

  factory ReferentMissionSummary.fromJson(Map<String, dynamic> json) {
    return ReferentMissionSummary(
      id: readInt(json['id']) ?? 0,
      description: readString(json['description']) ?? '',
      address: readString(json['address']),
      montantTotal: readInt(json['montant_total']) ?? 0,
      statusLabel: readString(json['status_label']) ?? '',
    );
  }
}

/// Une inspection réalisée par le Référent connecté.
class ReferentInspection {
  const ReferentInspection({required this.mission, this.inspectedAt});

  final ReferentMissionSummary mission;
  final DateTime? inspectedAt;

  factory ReferentInspection.fromJson(Map<String, dynamic> json) {
    return ReferentInspection(
      mission: ReferentMissionSummary.fromJson(json),
      inspectedAt: _date(json['inspected_at']),
    );
  }
}

/// Un litige d'un chantier que le Référent doit visiter ou a visité.
class ReferentLitige {
  const ReferentLitige({
    required this.id,
    required this.motif,
    required this.description,
    required this.statutLabel,
    required this.visitRequired,
    required this.visitedByMe,
    this.decisionLabel,
    this.mission,
    this.createdAt,
    this.visitedAt,
  });

  final int id;
  final String motif;
  final String description;
  final String statutLabel;
  final String? decisionLabel;
  final ReferentMissionSummary? mission;
  final bool visitRequired;
  final bool visitedByMe;
  final DateTime? createdAt;
  final DateTime? visitedAt;

  factory ReferentLitige.fromJson(Map<String, dynamic> json) {
    final mission = readMap(json['mission']);
    final statut = readString(json['statut']) ?? 'ouvert';

    return ReferentLitige(
      id: readInt(json['id']) ?? 0,
      motif: readString(json['motif']) ?? 'Litige',
      description: readString(json['description']) ?? '',
      statutLabel:
          readString(json['statut_label']) ?? litigeStatusLabel(statut),
      decisionLabel: readString(json['decision_label']),
      mission:
          mission == null ? null : ReferentMissionSummary.fromJson(mission),
      visitRequired: readBool(json['visit_required']) ?? false,
      visitedByMe: readBool(json['visited_by_me']) ?? false,
      createdAt: _date(json['created_at']),
      visitedAt: _date(json['visited_at']),
    );
  }
}

/// Une course passée du livreur. Aucun code de retrait ou de réception n'y
/// figure : le serveur ne les lui transmet pas.
class DeliveryRecord {
  const DeliveryRecord({
    required this.id,
    required this.status,
    required this.fare,
    this.supplierName,
    this.city,
    this.createdAt,
  });

  final int id;
  final String status;
  final int fare;
  final String? supplierName;
  final String? city;
  final DateTime? createdAt;

  factory DeliveryRecord.fromJson(Map<String, dynamic> json) {
    final supplier = readMap(json['supplier']);
    final shop = readMap(supplier?['fournisseur_agree']);

    return DeliveryRecord(
      id: readInt(json['id']) ?? 0,
      status: readString(json['status']) ?? '',
      fare:
          readInt(json['delivery_fare']) ?? readInt(json['delivery_cost']) ?? 0,
      supplierName:
          readString(shop?['nom_boutique']) ?? readString(supplier?['name']),
      city: readString(json['delivery_city']),
      createdAt: _date(json['created_at']),
    );
  }
}

/// Un litige de commande et son issue. Aucun nom ni téléphone n'y figure.
class OrderDisputeRecord {
  const OrderDisputeRecord({
    required this.id,
    required this.orderId,
    required this.statut,
    required this.statutLabel,
    required this.orderTotal,
    this.reason,
    this.outcomeLabel,
    this.resolutionNote,
    this.openedAt,
    this.resolvedAt,
  });

  final int id;
  final int orderId;
  final String statut;
  final String statutLabel;
  final int orderTotal;
  final String? reason;
  final String? outcomeLabel;
  final String? resolutionNote;
  final DateTime? openedAt;
  final DateTime? resolvedAt;

  bool get isResolved => statut == 'resolu';

  factory OrderDisputeRecord.fromJson(Map<String, dynamic> json) {
    final statut = readString(json['statut']) ?? 'ouvert';

    return OrderDisputeRecord(
      id: readInt(json['id']) ?? 0,
      orderId: readInt(json['order_id']) ?? 0,
      statut: statut,
      statutLabel: readString(json['statut_label']) ??
          (statut == 'resolu' ? 'Résolu' : 'En cours'),
      orderTotal: readInt(json['order_total']) ?? 0,
      reason: readString(json['reason']),
      outcomeLabel: readString(json['outcome_label']),
      resolutionNote: readString(json['resolution_note']),
      openedAt: _date(json['opened_at']),
      resolvedAt: _date(json['resolved_at']),
    );
  }
}
