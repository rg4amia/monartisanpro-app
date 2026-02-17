import 'package:json_annotation/json_annotation.dart';

part 'milestone_model.g.dart';

@JsonSerializable()
class Milestone {
  final int id;
  @JsonKey(name: 'project_id')
  final int projectId;
  final String title;
  final String description;
  @JsonKey(name: 'labor_percentage')
  final double laborPercentage;
  @JsonKey(name: 'labor_amount')
  final double laborAmount;
  @JsonKey(name: 'sequence_order')
  final int sequenceOrder;
  final String status; // pending, in_progress, completed, validated, paid
  @JsonKey(name: 'requires_photo')
  final bool requiresPhoto;
  @JsonKey(name: 'photo_url')
  final String? photoUrl;
  @JsonKey(name: 'photo_uploaded_at')
  final DateTime? photoUploadedAt;
  @JsonKey(name: 'requires_otp')
  final bool requiresOtp;
  @JsonKey(name: 'otp_verified_at')
  final DateTime? otpVerifiedAt;
  @JsonKey(name: 'completed_at')
  final DateTime? completedAt;
  @JsonKey(name: 'validated_at')
  final DateTime? validatedAt;
  @JsonKey(name: 'paid_at')
  final DateTime? paidAt;
  @JsonKey(name: 'created_at')
  final DateTime createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime updatedAt;

  Milestone({
    required this.id,
    required this.projectId,
    required this.title,
    required this.description,
    required this.laborPercentage,
    required this.laborAmount,
    required this.sequenceOrder,
    required this.status,
    required this.requiresPhoto,
    this.photoUrl,
    this.photoUploadedAt,
    required this.requiresOtp,
    this.otpVerifiedAt,
    this.completedAt,
    this.validatedAt,
    this.paidAt,
    required this.createdAt,
    required this.updatedAt,
  });

  factory Milestone.fromJson(Map<String, dynamic> json) =>
      _$MilestoneFromJson(json);
  Map<String, dynamic> toJson() => _$MilestoneToJson(this);

  // Helper getters
  bool get isPending => status == 'pending';
  bool get isInProgress => status == 'in_progress';
  bool get isCompleted => status == 'completed';
  bool get isValidated => status == 'validated';
  bool get isPaid => status == 'paid';

  bool get canComplete => isPending || isInProgress;
  bool get canValidate => isCompleted;
  bool get needsPhoto => requiresPhoto && photoUrl == null;
  bool get needsOtp => requiresOtp && otpVerifiedAt == null;
  bool get isReadyForValidation => isCompleted && !needsPhoto && !needsOtp;

  String get statusLabel {
    switch (status) {
      case 'pending':
        return 'En attente';
      case 'in_progress':
        return 'En cours';
      case 'completed':
        return 'Terminé';
      case 'validated':
        return 'Validé';
      case 'paid':
        return 'Payé';
      default:
        return status;
    }
  }

  String get formattedAmount => '${laborAmount.toStringAsFixed(0)} FCFA';
  String get formattedPercentage => '${laborPercentage.toStringAsFixed(0)}%';
}

@JsonSerializable()
class CreateMilestoneRequest {
  @JsonKey(name: 'project_id')
  final int projectId;
  final String title;
  final String description;
  @JsonKey(name: 'labor_percentage')
  final double laborPercentage;
  @JsonKey(name: 'sequence_order')
  final int sequenceOrder;
  @JsonKey(name: 'requires_photo')
  final bool requiresPhoto;
  @JsonKey(name: 'requires_otp')
  final bool requiresOtp;

  CreateMilestoneRequest({
    required this.projectId,
    required this.title,
    required this.description,
    required this.laborPercentage,
    required this.sequenceOrder,
    this.requiresPhoto = true,
    this.requiresOtp = true,
  });

  Map<String, dynamic> toJson() => _$CreateMilestoneRequestToJson(this);
}

@JsonSerializable()
class CompleteMilestoneRequest {
  @JsonKey(name: 'milestone_id')
  final int milestoneId;
  @JsonKey(name: 'photo_path')
  final String? photoPath;
  final String? notes;

  CompleteMilestoneRequest({
    required this.milestoneId,
    this.photoPath,
    this.notes,
  });

  Map<String, dynamic> toJson() => _$CompleteMilestoneRequestToJson(this);
}

@JsonSerializable()
class ValidateMilestoneRequest {
  @JsonKey(name: 'milestone_id')
  final int milestoneId;
  @JsonKey(name: 'otp_code')
  final String? otpCode;
  final bool approved;
  @JsonKey(name: 'rejection_reason')
  final String? rejectionReason;

  ValidateMilestoneRequest({
    required this.milestoneId,
    this.otpCode,
    required this.approved,
    this.rejectionReason,
  });

  Map<String, dynamic> toJson() => _$ValidateMilestoneRequestToJson(this);
}

@JsonSerializable()
class LaborPayment {
  final int id;
  @JsonKey(name: 'project_id')
  final int projectId;
  @JsonKey(name: 'milestone_id')
  final int milestoneId;
  @JsonKey(name: 'artisan_id')
  final int artisanId;
  final double amount;
  final String status; // pending, processing, completed, failed
  @JsonKey(name: 'payment_method')
  final String? paymentMethod;
  @JsonKey(name: 'transaction_reference')
  final String? transactionReference;
  @JsonKey(name: 'scheduled_at')
  final DateTime scheduledAt;
  @JsonKey(name: 'processed_at')
  final DateTime? processedAt;
  @JsonKey(name: 'completed_at')
  final DateTime? completedAt;
  @JsonKey(name: 'failure_reason')
  final String? failureReason;
  @JsonKey(name: 'created_at')
  final DateTime createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime updatedAt;

  LaborPayment({
    required this.id,
    required this.projectId,
    required this.milestoneId,
    required this.artisanId,
    required this.amount,
    required this.status,
    this.paymentMethod,
    this.transactionReference,
    required this.scheduledAt,
    this.processedAt,
    this.completedAt,
    this.failureReason,
    required this.createdAt,
    required this.updatedAt,
  });

  factory LaborPayment.fromJson(Map<String, dynamic> json) =>
      _$LaborPaymentFromJson(json);
  Map<String, dynamic> toJson() => _$LaborPaymentToJson(this);

  // Helper getters
  bool get isPending => status == 'pending';
  bool get isProcessing => status == 'processing';
  bool get isCompleted => status == 'completed';
  bool get isFailed => status == 'failed';

  String get statusLabel {
    switch (status) {
      case 'pending':
        return 'En attente';
      case 'processing':
        return 'En cours';
      case 'completed':
        return 'Complété';
      case 'failed':
        return 'Échoué';
      default:
        return status;
    }
  }

  String get formattedAmount => '${amount.toStringAsFixed(0)} FCFA';

  Duration get timeUntilPayment {
    if (isCompleted || isFailed) return Duration.zero;
    return scheduledAt.difference(DateTime.now());
  }

  String get paymentTimeText {
    if (isCompleted) return 'Payé';
    if (isFailed) return 'Échoué';

    final duration = timeUntilPayment;
    if (duration.isNegative) return 'En cours de traitement';

    final days = duration.inDays;
    final hours = duration.inHours % 24;

    if (days > 0) return 'Dans $days jour${days > 1 ? 's' : ''}';
    if (hours > 0) return 'Dans $hours heure${hours > 1 ? 's' : ''}';
    return 'Bientôt';
  }
}

@JsonSerializable()
class ProjectProgress {
  @JsonKey(name: 'project_id')
  final int projectId;
  @JsonKey(name: 'total_milestones')
  final int totalMilestones;
  @JsonKey(name: 'completed_milestones')
  final int completedMilestones;
  @JsonKey(name: 'validated_milestones')
  final int validatedMilestones;
  @JsonKey(name: 'total_labor_amount')
  final double totalLaborAmount;
  @JsonKey(name: 'released_labor_amount')
  final double releasedLaborAmount;
  @JsonKey(name: 'pending_labor_amount')
  final double pendingLaborAmount;
  @JsonKey(name: 'completion_percentage')
  final double completionPercentage;

  ProjectProgress({
    required this.projectId,
    required this.totalMilestones,
    required this.completedMilestones,
    required this.validatedMilestones,
    required this.totalLaborAmount,
    required this.releasedLaborAmount,
    required this.pendingLaborAmount,
    required this.completionPercentage,
  });

  factory ProjectProgress.fromJson(Map<String, dynamic> json) =>
      _$ProjectProgressFromJson(json);
  Map<String, dynamic> toJson() => _$ProjectProgressToJson(this);

  // Helper getters
  bool get isComplete => completionPercentage >= 100;
  bool get isHalfway => completionPercentage >= 50;

  String get formattedCompletion => '${completionPercentage.toStringAsFixed(0)}%';
  String get milestonesText => '$completedMilestones/$totalMilestones étapes';
}
