import 'package:json_annotation/json_annotation.dart';

part 'message_model.g.dart';

@JsonSerializable()
class ProjectMessage {
  final int id;
  @JsonKey(name: 'message')
  final String content;
  final MessageSender sender;
  @JsonKey(name: 'is_own_message')
  final bool isOwnMessage;
  final List<String>? attachments;
  @JsonKey(name: 'read_at')
  final DateTime? readAt;
  @JsonKey(name: 'created_at')
  final DateTime createdAt;

  ProjectMessage({
    required this.id,
    required this.content,
    required this.sender,
    required this.isOwnMessage,
    this.attachments,
    this.readAt,
    required this.createdAt,
  });

  factory ProjectMessage.fromJson(Map<String, dynamic> json) =>
      _$ProjectMessageFromJson(json);

  Map<String, dynamic> toJson() => _$ProjectMessageToJson(this);
}

@JsonSerializable()
class MessageSender {
  final int id;
  final String name;
  final String? avatar;

  MessageSender({
    required this.id,
    required this.name,
    this.avatar,
  });

  factory MessageSender.fromJson(Map<String, dynamic> json) =>
      _$MessageSenderFromJson(json);

  Map<String, dynamic> toJson() => _$MessageSenderToJson(this);
}

@JsonSerializable()
class Conversation {
  final int id;
  final String title;
  @JsonKey(name: 'trade_name')
  final String? tradeName;
  final String status;
  @JsonKey(name: 'other_user')
  final ConversationUser? otherUser;
  @JsonKey(name: 'last_message')
  final LastMessage? lastMessage;
  @JsonKey(name: 'unread_count')
  final int unreadCount;
  @JsonKey(name: 'updated_at')
  final DateTime updatedAt;

  Conversation({
    required this.id,
    required this.title,
    this.tradeName,
    required this.status,
    this.otherUser,
    this.lastMessage,
    required this.unreadCount,
    required this.updatedAt,
  });

  factory Conversation.fromJson(Map<String, dynamic> json) =>
      _$ConversationFromJson(json);

  Map<String, dynamic> toJson() => _$ConversationToJson(this);
}

@JsonSerializable()
class ConversationUser {
  final int id;
  final String name;
  final String? avatar;

  ConversationUser({
    required this.id,
    required this.name,
    this.avatar,
  });

  factory ConversationUser.fromJson(Map<String, dynamic> json) =>
      _$ConversationUserFromJson(json);

  Map<String, dynamic> toJson() => _$ConversationUserToJson(this);
}

@JsonSerializable()
class LastMessage {
  final String message;
  @JsonKey(name: 'created_at')
  final DateTime createdAt;
  @JsonKey(name: 'sender_id')
  final int senderId;

  LastMessage({
    required this.message,
    required this.createdAt,
    required this.senderId,
  });

  factory LastMessage.fromJson(Map<String, dynamic> json) =>
      _$LastMessageFromJson(json);

  Map<String, dynamic> toJson() => _$LastMessageToJson(this);
}

@JsonSerializable()
class SendMessageRequest {
  final String message;
  final List<String>? attachments;

  SendMessageRequest({
    required this.message,
    this.attachments,
  });

  Map<String, dynamic> toJson() => _$SendMessageRequestToJson(this);
}
