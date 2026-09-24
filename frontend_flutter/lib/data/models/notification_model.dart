import 'package:frontend_flutter/core/utils/json_readers.dart';

class NotificationModel {
  final int id;
  final String type; // payment | validation | alert | litige
  final String title;
  final String message;
  final bool isRead;
  final String createdAt;
  final Map<String, dynamic>? data;

  const NotificationModel({
    required this.id,
    required this.type,
    required this.title,
    required this.message,
    required this.isRead,
    required this.createdAt,
    this.data,
  });

  factory NotificationModel.fromJson(Map<String, dynamic> json) =>
      NotificationModel(
        id: readInt(json['id']) ?? 0,
        type: readString(json['type']) ?? 'alert',
        title: readString(json['title']) ?? '',
        message: readString(json['message']) ?? '',
        isRead: readBool(json['isRead']) ?? readBool(json['read']) ?? false,
        createdAt: readString(json['createdAt']) ??
            readString(json['created_at']) ??
            DateTime.now().toIso8601String(),
        data: readMap(json['data']),
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'type': type,
        'title': title,
        'message': message,
        'isRead': isRead,
        'createdAt': createdAt,
        'data': data,
      };
}
