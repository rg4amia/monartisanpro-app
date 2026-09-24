import '../../core/utils/json_readers.dart';

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
    final sender = readMap(json['sender']);

    return ChatMessageModel(
      id: _asInt(json['id']) ?? 0,
      missionId: _asInt(json['mission_id']) ?? 0,
      senderId: _asInt(json['sender_id']) ?? 0,
      senderName: _asString(sender?['name']),
      senderRole: _asString(sender?['role']),
      type: _asString(json['type']) ?? 'text',
      content: _asString(json['content']),
      mediaUrl: _asString(json['media_url']),
      mediaMetadata: readMap(json['media_metadata']),
      isRedacted: json['is_redacted'] == true,
      flaggedForReview: json['flagged_for_review'] == true,
      readAt: _asDateTime(json['read_at']),
      createdAt: _asDateTime(json['created_at']) ?? DateTime.now(),
    );
  }

  /// Conversion défensive vers `int?` : tolère un identifiant reçu en chaîne
  /// (fréquent avec les BIGINT sérialisés côté Laravel) sans jamais lever.
  static int? _asInt(dynamic value) {
    if (value == null) return null;
    if (value is int) return value;
    if (value is double) return value.toInt();
    return int.tryParse(value.toString());
  }

  static String? _asString(dynamic value) {
    if (value == null) return null;
    if (value is String) return value;
    return value.toString();
  }

  static DateTime? _asDateTime(dynamic value) {
    if (value == null) return null;
    return DateTime.tryParse(value.toString());
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
