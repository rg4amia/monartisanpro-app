import 'package:equatable/equatable.dart';
import 'package:intl/intl.dart';

/// Domain model representing a dispute (Litige)
///
/// Requirements: 9.1, 9.5
class Dispute extends Equatable {
  final String id;
  final String missionId;
  final String reporterId;
  final String defendantId;
  final DisputeType type;
  final String description;
  final List<String> evidence;
  final DisputeStatus status;
  final Mediation? mediation;
  final Arbitration? arbitration;
  final Resolution? resolution;
  final DateTime createdAt;
  final DateTime? resolvedAt;

  const Dispute({
    required this.id,
    required this.missionId,
    required this.reporterId,
    required this.defendantId,
    required this.type,
    required this.description,
    required this.evidence,
    required this.status,
    this.mediation,
    this.arbitration,
    this.resolution,
    required this.createdAt,
    this.resolvedAt,
  });

  factory Dispute.fromJson(Map<String, dynamic> json) {
    return Dispute(
      id: json['id'],
      missionId: json['mission_id'],
      reporterId: json['reporter_id'],
      defendantId: json['defendant_id'],
      type: DisputeType.fromJson(json['type']),
      description: json['description'],
      evidence: List<String>.from(json['evidence'] ?? []),
      status: DisputeStatus.fromJson(json['status']),
      mediation: json['mediation'] != null
          ? Mediation.fromJson(json['mediation'])
          : null,
      arbitration: json['arbitration'] != null
          ? Arbitration.fromJson(json['arbitration'])
          : null,
      resolution: json['resolution'] != null
          ? Resolution.fromJson(json['resolution'])
          : null,
      createdAt: DateTime.parse(json['created_at']),
      resolvedAt: json['resolved_at'] != null
          ? DateTime.parse(json['resolved_at'])
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'mission_id': missionId,
      'reporter_id': reporterId,
      'defendant_id': defendantId,
      'type': type.toJson(),
      'description': description,
      'evidence': evidence,
      'status': status.toJson(),
      'mediation': mediation?.toJson(),
      'arbitration': arbitration?.toJson(),
      'resolution': resolution?.toJson(),
      'created_at': createdAt.toIso8601String(),
      'resolved_at': resolvedAt?.toIso8601String(),
    };
  }

  bool get isOpen => status.value == 'OPEN';
  bool get isInMediation => status.value == 'IN_MEDIATION';
  bool get isInArbitration => status.value == 'IN_ARBITRATION';
  bool get isResolved => status.value == 'RESOLVED';
  bool get isClosed => status.value == 'CLOSED';

  bool involvesUser(String userId) {
    return reporterId == userId || defendantId == userId;
  }

  String getOtherParty(String userId) {
    if (reporterId == userId) return defendantId;
    if (defendantId == userId) return reporterId;
    throw ArgumentError('User is not involved in this dispute');
  }

  @override
  List<Object?> get props => [
    id,
    missionId,
    reporterId,
    defendantId,
    type,
    description,
    evidence,
    status,
    mediation,
    arbitration,
    resolution,
    createdAt,
    resolvedAt,
  ];
}

/// Dispute type value object
class DisputeType extends Equatable {
  final String value;
  final String label;

  const DisputeType({required this.value, required this.label});

  factory DisputeType.fromJson(Map<String, dynamic> json) {
    return DisputeType(value: json['value'], label: json['label']);
  }

  Map<String, dynamic> toJson() {
    return {'value': value, 'label': label};
  }

  static const quality = DisputeType(value: 'QUALITY', label: 'Qualité');
  static const payment = DisputeType(value: 'PAYMENT', label: 'Paiement');
  static const delay = DisputeType(value: 'DELAY', label: 'Retard');
  static const other = DisputeType(value: 'OTHER', label: 'Autre');

  static List<DisputeType> get allTypes => [quality, payment, delay, other];

  @override
  List<Object?> get props => [value, label];
}

/// Dispute status value object
class DisputeStatus extends Equatable {
  final String value;
  final String label;

  const DisputeStatus({required this.value, required this.label});

  factory DisputeStatus.fromJson(Map<String, dynamic> json) {
    return DisputeStatus(value: json['value'], label: json['label']);
  }

  Map<String, dynamic> toJson() {
    return {'value': value, 'label': label};
  }

  @override
  List<Object?> get props => [value, label];
}

/// Mediation model
class Mediation extends Equatable {
  final String mediatorId;
  final bool isActive;
  final int communicationsCount;
  final List<Communication> communications;
  final DateTime startedAt;
  final DateTime? endedAt;

  const Mediation({
    required this.mediatorId,
    required this.isActive,
    required this.communicationsCount,
    required this.communications,
    required this.startedAt,
    this.endedAt,
  });

  factory Mediation.fromJson(Map<String, dynamic> json) {
    return Mediation(
      mediatorId: json['mediator_id'],
      isActive: json['is_active'],
      communicationsCount: json['communications_count'],
      communications: (json['communications'] as List)
          .map((comm) => Communication.fromJson(comm))
          .toList(),
      startedAt: DateTime.parse(json['started_at']),
      endedAt: json['ended_at'] != null
          ? DateTime.parse(json['ended_at'])
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'mediator_id': mediatorId,
      'is_active': isActive,
      'communications_count': communicationsCount,
      'communications': communications.map((comm) => comm.toJson()).toList(),
      'started_at': startedAt.toIso8601String(),
      'ended_at': endedAt?.toIso8601String(),
    };
  }

  @override
  List<Object?> get props => [
    mediatorId,
    isActive,
    communicationsCount,
    communications,
    startedAt,
    endedAt,
  ];
}

/// Communication model for mediation
class Communication extends Equatable {
  final String message;
  final String senderId;
  final DateTime sentAt;

  const Communication({
    required this.message,
    required this.senderId,
    required this.sentAt,
  });

  factory Communication.fromJson(Map<String, dynamic> json) {
    return Communication(
      message: json['message'],
      senderId: json['sender_id'],
      sentAt: DateTime.parse(json['sent_at']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'message': message,
      'sender_id': senderId,
      'sent_at': sentAt.toIso8601String(),
    };
  }

  @override
  List<Object?> get props => [message, senderId, sentAt];
}

/// Arbitration model
class Arbitration extends Equatable {
  final String arbitratorId;
  final ArbitrationDecision decision;
  final String justification;
  final DateTime renderedAt;

  const Arbitration({
    required this.arbitratorId,
    required this.decision,
    required this.justification,
    required this.renderedAt,
  });

  factory Arbitration.fromJson(Map<String, dynamic> json) {
    return Arbitration(
      arbitratorId: json['arbitrator_id'],
      decision: ArbitrationDecision.fromJson(json['decision']),
      justification: json['justification'],
      renderedAt: DateTime.parse(json['rendered_at']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'arbitrator_id': arbitratorId,
      'decision': decision.toJson(),
      'justification': justification,
      'rendered_at': renderedAt.toIso8601String(),
    };
  }

  @override
  List<Object?> get props => [
    arbitratorId,
    decision,
    justification,
    renderedAt,
  ];
}

/// Arbitration decision model
class ArbitrationDecision extends Equatable {
  final DecisionType type;
  final MoneyAmount? amount;

  const ArbitrationDecision({required this.type, this.amount});

  factory ArbitrationDecision.fromJson(Map<String, dynamic> json) {
    return ArbitrationDecision(
      type: DecisionType.fromJson(json['type']),
      amount: json['amount'] != null
          ? MoneyAmount.fromJson(json['amount'])
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {'type': type.toJson(), 'amount': amount?.toJson()};
  }

  @override
  List<Object?> get props => [type, amount];
}

/// Decision type for arbitration
class DecisionType extends Equatable {
  final String value;
  final String label;

  const DecisionType({required this.value, required this.label});

  factory DecisionType.fromJson(Map<String, dynamic> json) {
    return DecisionType(value: json['value'], label: json['label']);
  }

  Map<String, dynamic> toJson() {
    return {'value': value, 'label': label};
  }

  @override
  List<Object?> get props => [value, label];
}

/// Resolution model
class Resolution extends Equatable {
  final String outcome;
  final MoneyAmount? amount;
  final String notes;
  final DateTime resolvedAt;

  const Resolution({
    required this.outcome,
    this.amount,
    required this.notes,
    required this.resolvedAt,
  });

  factory Resolution.fromJson(Map<String, dynamic> json) {
    return Resolution(
      outcome: json['outcome'],
      amount: json['amount'] != null
          ? MoneyAmount.fromJson(json['amount'])
          : null,
      notes: json['notes'],
      resolvedAt: DateTime.parse(json['resolved_at']),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'outcome': outcome,
      'amount': amount?.toJson(),
      'notes': notes,
      'resolved_at': resolvedAt.toIso8601String(),
    };
  }

  @override
  List<Object?> get props => [outcome, amount, notes, resolvedAt];
}

/// Money amount model (assuming it exists in shared models)
class MoneyAmount extends Equatable {
  final int centimes;
  final String currency;

  const MoneyAmount({required this.centimes, required this.currency});

  factory MoneyAmount.fromJson(Map<String, dynamic> json) {
    return MoneyAmount(centimes: json['centimes'], currency: json['currency']);
  }

  Map<String, dynamic> toJson() {
    return {'centimes': centimes, 'currency': currency};
  }

  double get amount => centimes / 100.0;

  String get formattedAmount {
    final formatter = NumberFormat.currency(
      locale: 'fr_FR',
      symbol: 'FCFA',
      decimalDigits: 0,
    );
    return formatter.format(amount);
  }

  @override
  List<Object?> get props => [centimes, currency];
}
