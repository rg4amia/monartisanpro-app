// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'milestone_model.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Milestone _$MilestoneFromJson(Map<String, dynamic> json) => Milestone(
      id: (json['id'] as num).toInt(),
      projectId: (json['project_id'] as num).toInt(),
      title: json['title'] as String,
      description: json['description'] as String,
      laborPercentage: (json['labor_percentage'] as num).toDouble(),
      laborAmount: (json['labor_amount'] as num).toDouble(),
      sequenceOrder: (json['sequence_order'] as num).toInt(),
      status: json['status'] as String,
      requiresPhoto: json['requires_photo'] as bool,
      photoUrl: json['photo_url'] as String?,
      photoUploadedAt: json['photo_uploaded_at'] == null
          ? null
          : DateTime.parse(json['photo_uploaded_at'] as String),
      requiresOtp: json['requires_otp'] as bool,
      otpVerifiedAt: json['otp_verified_at'] == null
          ? null
          : DateTime.parse(json['otp_verified_at'] as String),
      completedAt: json['completed_at'] == null
          ? null
          : DateTime.parse(json['completed_at'] as String),
      validatedAt: json['validated_at'] == null
          ? null
          : DateTime.parse(json['validated_at'] as String),
      paidAt: json['paid_at'] == null
          ? null
          : DateTime.parse(json['paid_at'] as String),
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
    );

Map<String, dynamic> _$MilestoneToJson(Milestone instance) => <String, dynamic>{
      'id': instance.id,
      'project_id': instance.projectId,
      'title': instance.title,
      'description': instance.description,
      'labor_percentage': instance.laborPercentage,
      'labor_amount': instance.laborAmount,
      'sequence_order': instance.sequenceOrder,
      'status': instance.status,
      'requires_photo': instance.requiresPhoto,
      'photo_url': instance.photoUrl,
      'photo_uploaded_at': instance.photoUploadedAt?.toIso8601String(),
      'requires_otp': instance.requiresOtp,
      'otp_verified_at': instance.otpVerifiedAt?.toIso8601String(),
      'completed_at': instance.completedAt?.toIso8601String(),
      'validated_at': instance.validatedAt?.toIso8601String(),
      'paid_at': instance.paidAt?.toIso8601String(),
      'created_at': instance.createdAt.toIso8601String(),
      'updated_at': instance.updatedAt.toIso8601String(),
    };

CreateMilestoneRequest _$CreateMilestoneRequestFromJson(
        Map<String, dynamic> json) =>
    CreateMilestoneRequest(
      projectId: (json['project_id'] as num).toInt(),
      title: json['title'] as String,
      description: json['description'] as String,
      laborPercentage: (json['labor_percentage'] as num).toDouble(),
      sequenceOrder: (json['sequence_order'] as num).toInt(),
      requiresPhoto: json['requires_photo'] as bool? ?? true,
      requiresOtp: json['requires_otp'] as bool? ?? true,
    );

Map<String, dynamic> _$CreateMilestoneRequestToJson(
        CreateMilestoneRequest instance) =>
    <String, dynamic>{
      'project_id': instance.projectId,
      'title': instance.title,
      'description': instance.description,
      'labor_percentage': instance.laborPercentage,
      'sequence_order': instance.sequenceOrder,
      'requires_photo': instance.requiresPhoto,
      'requires_otp': instance.requiresOtp,
    };

CompleteMilestoneRequest _$CompleteMilestoneRequestFromJson(
        Map<String, dynamic> json) =>
    CompleteMilestoneRequest(
      milestoneId: (json['milestone_id'] as num).toInt(),
      photoPath: json['photo_path'] as String?,
      notes: json['notes'] as String?,
    );

Map<String, dynamic> _$CompleteMilestoneRequestToJson(
        CompleteMilestoneRequest instance) =>
    <String, dynamic>{
      'milestone_id': instance.milestoneId,
      'photo_path': instance.photoPath,
      'notes': instance.notes,
    };

ValidateMilestoneRequest _$ValidateMilestoneRequestFromJson(
        Map<String, dynamic> json) =>
    ValidateMilestoneRequest(
      milestoneId: (json['milestone_id'] as num).toInt(),
      otpCode: json['otp_code'] as String?,
      approved: json['approved'] as bool,
      rejectionReason: json['rejection_reason'] as String?,
    );

Map<String, dynamic> _$ValidateMilestoneRequestToJson(
        ValidateMilestoneRequest instance) =>
    <String, dynamic>{
      'milestone_id': instance.milestoneId,
      'otp_code': instance.otpCode,
      'approved': instance.approved,
      'rejection_reason': instance.rejectionReason,
    };

LaborPayment _$LaborPaymentFromJson(Map<String, dynamic> json) => LaborPayment(
      id: (json['id'] as num).toInt(),
      projectId: (json['project_id'] as num).toInt(),
      milestoneId: (json['milestone_id'] as num).toInt(),
      artisanId: (json['artisan_id'] as num).toInt(),
      amount: (json['amount'] as num).toDouble(),
      status: json['status'] as String,
      paymentMethod: json['payment_method'] as String?,
      transactionReference: json['transaction_reference'] as String?,
      scheduledAt: DateTime.parse(json['scheduled_at'] as String),
      processedAt: json['processed_at'] == null
          ? null
          : DateTime.parse(json['processed_at'] as String),
      completedAt: json['completed_at'] == null
          ? null
          : DateTime.parse(json['completed_at'] as String),
      failureReason: json['failure_reason'] as String?,
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
    );

Map<String, dynamic> _$LaborPaymentToJson(LaborPayment instance) =>
    <String, dynamic>{
      'id': instance.id,
      'project_id': instance.projectId,
      'milestone_id': instance.milestoneId,
      'artisan_id': instance.artisanId,
      'amount': instance.amount,
      'status': instance.status,
      'payment_method': instance.paymentMethod,
      'transaction_reference': instance.transactionReference,
      'scheduled_at': instance.scheduledAt.toIso8601String(),
      'processed_at': instance.processedAt?.toIso8601String(),
      'completed_at': instance.completedAt?.toIso8601String(),
      'failure_reason': instance.failureReason,
      'created_at': instance.createdAt.toIso8601String(),
      'updated_at': instance.updatedAt.toIso8601String(),
    };

ProjectProgress _$ProjectProgressFromJson(Map<String, dynamic> json) =>
    ProjectProgress(
      projectId: (json['project_id'] as num).toInt(),
      totalMilestones: (json['total_milestones'] as num).toInt(),
      completedMilestones: (json['completed_milestones'] as num).toInt(),
      validatedMilestones: (json['validated_milestones'] as num).toInt(),
      totalLaborAmount: (json['total_labor_amount'] as num).toDouble(),
      releasedLaborAmount: (json['released_labor_amount'] as num).toDouble(),
      pendingLaborAmount: (json['pending_labor_amount'] as num).toDouble(),
      completionPercentage: (json['completion_percentage'] as num).toDouble(),
    );

Map<String, dynamic> _$ProjectProgressToJson(ProjectProgress instance) =>
    <String, dynamic>{
      'project_id': instance.projectId,
      'total_milestones': instance.totalMilestones,
      'completed_milestones': instance.completedMilestones,
      'validated_milestones': instance.validatedMilestones,
      'total_labor_amount': instance.totalLaborAmount,
      'released_labor_amount': instance.releasedLaborAmount,
      'pending_labor_amount': instance.pendingLaborAmount,
      'completion_percentage': instance.completionPercentage,
    };
