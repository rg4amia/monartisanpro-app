import 'package:prosartisan_mobile/features/worksite/domain/models/jalon.dart';

/// Domain model for Chantier (Worksite)
///
/// Represents a worksite with milestones and progress tracking
/// Requirements: 6.1, 6.2
class Chantier {
  final String id;
  final String missionId;
  final String clientId;
  final String artisanId;
  final String status;
  final String statusLabel;
  final DateTime startedAt;
  final DateTime? completedAt;
  final double progressPercentage;
  final bool canBeCompleted;

  // Milestone counts
  final int milestonesCount;
  final int completedMilestonesCount;
  final int pendingMilestonesCount;

  // Financial information
  final MoneyAmount totalLaborAmount;
  final MoneyAmount completedLaborAmount;

  // Milestones
  final Jalon? nextMilestone;
  final List<Jalon> milestones;

  const Chantier({
    required this.id,
    required this.missionId,
    required this.clientId,
    required this.artisanId,
    required this.status,
    required this.statusLabel,
    required this.startedAt,
    this.completedAt,
    required this.progressPercentage,
    required this.canBeCompleted,
    required this.milestonesCount,
    required this.completedMilestonesCount,
    required this.pendingMilestonesCount,
    required this.totalLaborAmount,
    required this.completedLaborAmount,
    this.nextMilestone,
    required this.milestones,
  });

  factory Chantier.fromJson(Map<String, dynamic> json) {
    return Chantier(
      id: json['id'] as String,
      missionId: json['mission_id'] as String,
      clientId: json['client_id'] as String,
      artisanId: json['artisan_id'] as String,
      status: json['status'] as String,
      statusLabel: json['status_label'] as String,
      startedAt: DateTime.parse(json['started_at'] as String),
      completedAt: json['completed_at'] != null
          ? DateTime.parse(json['completed_at'] as String)
          : null,
      progressPercentage: (json['progress_percentage'] as num).toDouble(),
      canBeCompleted: json['can_be_completed'] as bool,
      milestonesCount: json['milestones_count'] as int,
      completedMilestonesCount: json['completed_milestones_count'] as int,
      pendingMilestonesCount: json['pending_milestones_count'] as int,
      totalLaborAmount: MoneyAmount.fromJson(
        json['total_labor_amount'] as Map<String, dynamic>,
      ),
      completedLaborAmount: MoneyAmount.fromJson(
        json['completed_labor_amount'] as Map<String, dynamic>,
      ),
      nextMilestone: json['next_milestone'] != null
          ? Jalon.fromJson(json['next_milestone'] as Map<String, dynamic>)
          : null,
      milestones: (json['milestones'] as List<dynamic>)
          .map((milestone) => Jalon.fromJson(milestone as Map<String, dynamic>))
          .toList(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'mission_id': missionId,
      'client_id': clientId,
      'artisan_id': artisanId,
      'status': status,
      'status_label': statusLabel,
      'started_at': startedAt.toIso8601String(),
      'completed_at': completedAt?.toIso8601String(),
      'progress_percentage': progressPercentage,
      'can_be_completed': canBeCompleted,
      'milestones_count': milestonesCount,
      'completed_milestones_count': completedMilestonesCount,
      'pending_milestones_count': pendingMilestonesCount,
      'total_labor_amount': totalLaborAmount.toJson(),
      'completed_labor_amount': completedLaborAmount.toJson(),
      'next_milestone': nextMilestone?.toJson(),
      'milestones': milestones.map((milestone) => milestone.toJson()).toList(),
    };
  }

  bool get isInProgress => status == 'IN_PROGRESS';
  bool get isCompleted => status == 'COMPLETED';
  bool get isDisputed => status == 'DISPUTED';

  List<Jalon> get pendingMilestones =>
      milestones.where((milestone) => !milestone.isCompleted).toList();

  List<Jalon> get completedMilestones =>
      milestones.where((milestone) => milestone.isCompleted).toList();
}

/// Money amount representation
class MoneyAmount {
  final int centimes;
  final String formatted;
  final String currency;

  const MoneyAmount({
    required this.centimes,
    required this.formatted,
    required this.currency,
  });

  factory MoneyAmount.fromJson(Map<String, dynamic> json) {
    return MoneyAmount(
      centimes: json['centimes'] as int,
      formatted: json['formatted'] as String,
      currency: json['currency'] as String,
    );
  }

  Map<String, dynamic> toJson() {
    return {'centimes': centimes, 'formatted': formatted, 'currency': currency};
  }

  double get amount => centimes / 100.0;
}
