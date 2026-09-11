class ChatMessageModel {
  final int id;
  final int missionId;
  final int senderId;
  final String? senderName;
  final String? senderRole;
  final String type; // 'text', 'image', 'audio'
  final String? content;
  final String? mediaUrl;
  final Map<String, dynamic>? mediaMetadata;
  final bool isRedacted;
  final bool flaggedForReview;
  final DateTime? readAt;
  final DateTime createdAt;

  const ChatMessageModel({
    required this.id,
    required this.missionId,
    required this.senderId,
    this.senderName,
    this.senderRole,
    required this.type,
    this.content,
    this.mediaUrl,
    this.mediaMetadata,
    this.isRedacted = false,
    this.flaggedForReview = false,
    this.readAt,
    required this.createdAt,
  });

  bool get isText => type == 'text';
  bool get isImage => type == 'image';
  bool get isAudio => type == 'audio';
  bool get isRead => readAt != null;

  factory ChatMessageModel.fromJson(Map<String, dynamic> json) {
    final sender = json['sender'] as Map<String, dynamic>?;

    return ChatMessageModel(
      id: json['id'] as int? ?? 0,
      missionId: json['mission_id'] as int? ?? 0,
      senderId: json['sender_id'] as int? ?? 0,
      senderName: sender?['name'] as String?,
      senderRole: sender?['role'] as String?,
      type: json['type'] as String? ?? 'text',
      content: json['content'] as String?,
      mediaUrl: json['media_url'] as String?,
      mediaMetadata: json['media_metadata'] as Map<String, dynamic>?,
      isRedacted: json['is_redacted'] as bool? ?? false,
      flaggedForReview: json['flagged_for_review'] as bool? ?? false,
      readAt: json['read_at'] != null
          ? DateTime.tryParse(json['read_at'] as String)
          : null,
      createdAt: json['created_at'] != null
          ? DateTime.tryParse(json['created_at'] as String) ?? DateTime.now()
          : DateTime.now(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'mission_id': missionId,
      'sender_id': senderId,
      'type': type,
      'content': content,
      'media_url': mediaUrl,
      'media_metadata': mediaMetadata,
      'is_redacted': isRedacted,
      'flagged_for_review': flaggedForReview,
      'read_at': readAt?.toIso8601String(),
      'created_at': createdAt.toIso8601String(),
    };
  }
}
