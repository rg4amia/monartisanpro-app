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
        id: json['id'] as int,
        type: json['type'] as String? ?? 'alert',
        title: json['title'] as String,
        message: json['message'] as String,
        isRead: json['isRead'] as bool? ?? false,
        createdAt: json['createdAt'] as String,
        data: json['data'] as Map<String, dynamic>?,
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
