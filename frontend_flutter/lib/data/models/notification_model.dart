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
        id: json['id'] as int? ?? 0,
        type: json['type'] as String? ?? 'alert',
        title: json['title'] as String? ?? '',
        message: json['message'] as String? ?? '',
        isRead: json['isRead'] as bool? ?? json['read'] as bool? ?? false,
        createdAt: json['createdAt'] as String? ?? json['created_at'] as String? ?? DateTime.now().toIso8601String(),
        data: json['data'] is Map ? json['data'] as Map<String, dynamic> : null,
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
