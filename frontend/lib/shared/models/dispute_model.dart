import 'package:json_annotation/json_annotation.dart';
import 'user_model.dart';

part 'dispute_model.g.dart';

@JsonSerializable()
class Dispute {
  final int id;
  @JsonKey(name: 'project_id')
  final int projectId;
  @JsonKey(name: 'initiator_id')
  final int initiatorId;
  @JsonKey(name: 'respondent_id')
  final int respondentId;
  @JsonKey(name: 'dispute_type')
  final String disputeType;
  final String reason;
  final String description;
  final String status;
  final String priority;
  final List<String>? evidence;
  final String? resolution;
  @JsonKey(name: 'resolution_type')
  final String? resolutionType;
  @JsonKey(name: 'resolved_by')
  final int? resolvedBy;
  @JsonKey(name: 'resolved_at')
  final DateTime? resolvedAt;
  @JsonKey(name: 'created_at')
  final DateTime createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime updatedAt;

  // Navigation properties
  final DisputeProject? project;
  final User? initiator;
  final User? respondent;
  final List<DisputeMessage>? messages;

  Dispute({
    required this.id,
    required this.projectId,
    required this.initiatorId,
    required this.respondentId,
    required this.disputeType,
    required this.reason,
    required this.description,
    required this.status,
    required this.priority,
    this.evidence,
    this.resolution,
    this.resolutionType,
    this.resolvedBy,
    this.resolvedAt,
    required this.createdAt,
    required this.updatedAt,
    this.project,
    this.initiator,
    this.respondent,
    this.messages,
  });

  factory Dispute.fromJson(Map<String, dynamic> json) =>
      _$DisputeFromJson(json);

  Map<String, dynamic> toJson() => _$DisputeToJson(this);
}

@JsonSerializable()
class DisputeMessage {
  final int id;
  @JsonKey(name: 'dispute_id')
  final int disputeId;
  @JsonKey(name: 'user_id')
  final int userId;
  final String message;
  final List<String>? attachments;
  @JsonKey(name: 'is_admin_message')
  final bool isAdminMessage;
  @JsonKey(name: 'read_at')
  final DateTime? readAt;
  @JsonKey(name: 'created_at')
  final DateTime createdAt;

  // Navigation
  final User? user;

  DisputeMessage({
    required this.id,
    required this.disputeId,
    required this.userId,
    required this.message,
    this.attachments,
    required this.isAdminMessage,
    this.readAt,
    required this.createdAt,
    this.user,
  });

  factory DisputeMessage.fromJson(Map<String, dynamic> json) =>
      _$DisputeMessageFromJson(json);

  Map<String, dynamic> toJson() => _$DisputeMessageToJson(this);
}

@JsonSerializable()
class CreateDisputeRequest {
  @JsonKey(name: 'project_id')
  final int projectId;
  @JsonKey(name: 'dispute_type')
  final String disputeType;
  final String reason;
  final String description;
  final String priority;
  final List<String>? evidence;

  CreateDisputeRequest({
    required this.projectId,
    required this.disputeType,
    required this.reason,
    required this.description,
    required this.priority,
    this.evidence,
  });

  factory CreateDisputeRequest.fromJson(Map<String, dynamic> json) =>
      _$CreateDisputeRequestFromJson(json);

  Map<String, dynamic> toJson() => _$CreateDisputeRequestToJson(this);
}

// Placeholder classes for navigation properties
class Project {
  final int id;
  final String title;

  Project({required this.id, required this.title});

  factory Project.fromJson(Map<String, dynamic> json) =>
      Project(id: json['id'] as int, title: json['title'] as String);
}

class User {
  final int id;
  final String name;
  final String? avatar;

  User({required this.id, required this.name, this.avatar});

  factory User.fromJson(Map<String, dynamic> json) => User(
    id: json['id'] as int,
    name: json['name'] as String,
    avatar: json['avatar'] as String?,
  );
}
