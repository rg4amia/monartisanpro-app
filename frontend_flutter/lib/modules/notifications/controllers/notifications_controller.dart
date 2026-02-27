import 'package:get/get.dart';
import '../../../data/models/notification_model.dart';
import '../../../data/repositories/notification_repository.dart';

class NotificationsController extends GetxController {
  final NotificationRepository _repo = NotificationRepository();

  final notifications = <NotificationModel>[].obs;
  final isLoading = false.obs;

  int get unreadCount => notifications.where((n) => !n.isRead).length;

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load() async {
    isLoading.value = true;
    try {
      notifications.value = await _repo.getNotifications();
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> markRead(int id) async {
    await _repo.markRead(id);
    final idx = notifications.indexWhere((n) => n.id == id);
    if (idx != -1) {
      final n = notifications[idx];
      notifications[idx] = NotificationModel(
        id: n.id,
        title: n.title,
        message: n.message,
        type: n.type,
        isRead: true,
        createdAt: n.createdAt,
        data: n.data,
      );
    }
  }

  Future<void> markAllRead() async {
    await _repo.markAllRead();
    notifications.value = notifications
        .map((n) => NotificationModel(
              id: n.id,
              title: n.title,
              message: n.message,
              type: n.type,
              isRead: true,
              createdAt: n.createdAt,
              data: n.data,
            ))
        .toList();
  }
}
