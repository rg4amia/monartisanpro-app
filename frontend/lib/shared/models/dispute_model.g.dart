// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'dispute_model.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Dispute _$DisputeFromJson(Map<String, dynamic> json) => Dispute(
      id: (json['id'] as num).toInt(),
      projectId: (json['project_id'] as num).toInt(),
      initiatorId: (json['initiator_id'] as num).toInt(),
      respondentId: (json['respondent_id'] as num).toInt(),
      disputeType: json['dispute_type'] as String,
      reason: json['reason'] as String,
      description: json['description'] as String,
      status: json['status'] as String,
      priority: json['priority'] as String,
      evidence: (json['evidence'] as List<dynamic>?)
          ?.map((e) => e as String)
          .toList(),
      resolution: json['resolution'] as String?,
      resolutionType: json['resolution_type'] as String?,
      resolvedBy: (json['resolved_by'] as num?)?.toInt(),
      resolvedAt: json['resolved_at'] == null
          ? null
          : DateTime.parse(json['resolved_at'] as String),
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
      project: json['project'] == null
          ? null
          : DisputeProject.fromJson(json['project'] as Map<String, dynamic>),
      initiator: json['initiator'] == null
          ? null
          : User.fromJson(json['initiator'] as Map<String, dynamic>),
      respondent: json['respondent'] == null
          ? null
          : User.fromJson(json['respondent'] as Map<String, dynamic>),
      messages: (json['messages'] as List<dynamic>?)
          ?.map((e) => DisputeMessage.fromJson(e as Map<String, dynamic>))
          .toList(),
    );

Map<String, dynamic> _$DisputeToJson(Dispute instance) => <String, dynamic>{
      'id': instance.id,
      'project_id': instance.projectId,
      'initiator_id': instance.initiatorId,
      'respondent_id': instance.respondentId,
      'dispute_type': instance.disputeType,
      'reason': instance.reason,
      'description': instance.description,
      'status': instance.status,
      'priority': instance.priority,
      'evidence': instance.evidence,
      'resolution': instance.resolution,
      'resolution_type': instance.resolutionType,
      'resolved_by': instance.resolvedBy,
      'resolved_at': instance.resolvedAt?.toIso8601String(),
      'created_at': instance.createdAt.toIso8601String(),
      'updated_at': instance.updatedAt.toIso8601String(),
      'project': instance.project,
      'initiator': instance.initiator,
      'respondent': instance.respondent,
      'messages': instance.messages,
    };

DisputeMessage _$DisputeMessageFromJson(Map<String, dynamic> json) =>
    DisputeMessage(
      id: (json['id'] as num).toInt(),
      disputeId: (json['dispute_id'] as num).toInt(),
      userId: (json['user_id'] as num).toInt(),
      message: json['message'] as String,
      attachments: (json['attachments'] as List<dynamic>?)
          ?.map((e) => e as String)
          .toList(),
      isAdminMessage: json['is_admin_message'] as bool,
      readAt: json['read_at'] == null
          ? null
          : DateTime.parse(json['read_at'] as String),
      createdAt: DateTime.parse(json['created_at'] as String),
      user: json['user'] == null
          ? null
          : User.fromJson(json['user'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$DisputeMessageToJson(DisputeMessage instance) =>
    <String, dynamic>{
      'id': instance.id,
      'dispute_id': instance.disputeId,
      'user_id': instance.userId,
      'message': instance.message,
      'attachments': instance.attachments,
      'is_admin_message': instance.isAdminMessage,
      'read_at': instance.readAt?.toIso8601String(),
      'created_at': instance.createdAt.toIso8601String(),
      'user': instance.user,
    };

CreateDisputeRequest _$CreateDisputeRequestFromJson(
        Map<String, dynamic> json) =>
    CreateDisputeRequest(
      projectId: (json['project_id'] as num).toInt(),
      disputeType: json['dispute_type'] as String,
      reason: json['reason'] as String,
      description: json['description'] as String,
      priority: json['priority'] as String,
      evidence: (json['evidence'] as List<dynamic>?)
          ?.map((e) => e as String)
          .toList(),
    );

Map<String, dynamic> _$CreateDisputeRequestToJson(
        CreateDisputeRequest instance) =>
    <String, dynamic>{
      'project_id': instance.projectId,
      'dispute_type': instance.disputeType,
      'reason': instance.reason,
      'description': instance.description,
      'priority': instance.priority,
      'evidence': instance.evidence,
    };

DisputeProject _$DisputeProjectFromJson(Map<String, dynamic> json) =>
    DisputeProject(
      id: (json['id'] as num).toInt(),
      title: json['title'] as String,
    );

Map<String, dynamic> _$DisputeProjectToJson(DisputeProject instance) =>
    <String, dynamic>{
      'id': instance.id,
      'title': instance.title,
    };
