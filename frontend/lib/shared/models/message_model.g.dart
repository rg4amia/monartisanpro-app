// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'message_model.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ProjectMessage _$ProjectMessageFromJson(Map<String, dynamic> json) =>
    ProjectMessage(
      id: (json['id'] as num).toInt(),
      content: json['message'] as String,
      sender: MessageSender.fromJson(json['sender'] as Map<String, dynamic>),
      isOwnMessage: json['is_own_message'] as bool,
      attachments: (json['attachments'] as List<dynamic>?)
          ?.map((e) => e as String)
          .toList(),
      readAt: json['read_at'] == null
          ? null
          : DateTime.parse(json['read_at'] as String),
      createdAt: DateTime.parse(json['created_at'] as String),
    );

Map<String, dynamic> _$ProjectMessageToJson(ProjectMessage instance) =>
    <String, dynamic>{
      'id': instance.id,
      'message': instance.content,
      'sender': instance.sender,
      'is_own_message': instance.isOwnMessage,
      'attachments': instance.attachments,
      'read_at': instance.readAt?.toIso8601String(),
      'created_at': instance.createdAt.toIso8601String(),
    };

MessageSender _$MessageSenderFromJson(Map<String, dynamic> json) =>
    MessageSender(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
      avatar: json['avatar'] as String?,
    );

Map<String, dynamic> _$MessageSenderToJson(MessageSender instance) =>
    <String, dynamic>{
      'id': instance.id,
      'name': instance.name,
      'avatar': instance.avatar,
    };

Conversation _$ConversationFromJson(Map<String, dynamic> json) => Conversation(
      id: (json['id'] as num).toInt(),
      title: json['title'] as String,
      tradeName: json['trade_name'] as String?,
      status: json['status'] as String,
      otherUser: json['other_user'] == null
          ? null
          : ConversationUser.fromJson(
              json['other_user'] as Map<String, dynamic>),
      lastMessage: json['last_message'] == null
          ? null
          : LastMessage.fromJson(json['last_message'] as Map<String, dynamic>),
      unreadCount: (json['unread_count'] as num).toInt(),
      updatedAt: DateTime.parse(json['updated_at'] as String),
    );

Map<String, dynamic> _$ConversationToJson(Conversation instance) =>
    <String, dynamic>{
      'id': instance.id,
      'title': instance.title,
      'trade_name': instance.tradeName,
      'status': instance.status,
      'other_user': instance.otherUser,
      'last_message': instance.lastMessage,
      'unread_count': instance.unreadCount,
      'updated_at': instance.updatedAt.toIso8601String(),
    };

ConversationUser _$ConversationUserFromJson(Map<String, dynamic> json) =>
    ConversationUser(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
      avatar: json['avatar'] as String?,
    );

Map<String, dynamic> _$ConversationUserToJson(ConversationUser instance) =>
    <String, dynamic>{
      'id': instance.id,
      'name': instance.name,
      'avatar': instance.avatar,
    };

LastMessage _$LastMessageFromJson(Map<String, dynamic> json) => LastMessage(
      message: json['message'] as String,
      createdAt: DateTime.parse(json['created_at'] as String),
      senderId: (json['sender_id'] as num).toInt(),
    );

Map<String, dynamic> _$LastMessageToJson(LastMessage instance) =>
    <String, dynamic>{
      'message': instance.message,
      'created_at': instance.createdAt.toIso8601String(),
      'sender_id': instance.senderId,
    };

SendMessageRequest _$SendMessageRequestFromJson(Map<String, dynamic> json) =>
    SendMessageRequest(
      message: json['message'] as String,
      attachments: (json['attachments'] as List<dynamic>?)
          ?.map((e) => e as String)
          .toList(),
    );

Map<String, dynamic> _$SendMessageRequestToJson(SendMessageRequest instance) =>
    <String, dynamic>{
      'message': instance.message,
      'attachments': instance.attachments,
    };
