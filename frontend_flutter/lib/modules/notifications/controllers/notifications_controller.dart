import 'package:get/get.dart';
import '../../../core/services/notification_service.dart';
import '../../../data/models/notification_model.dart';
import '../../../data/repositories/notification_repository.dart';

class NotificationsController extends GetxController {
  final NotificationRepository _repo = NotificationRepository();

  final notifications = <NotificationModel>[].obs;
  final isLoading = false.obs;
  final selectedTab = 'all'.obs;

  int get unreadCount => notifications.where((n) => !n.isRead).length;

  List<NotificationModel> get filteredNotifications {
    if (selectedTab.value == 'all') {
      return notifications;
    } else if (selectedTab.value == 'missions') {
      return notifications
          .where(
            (n) =>
                n.type == 'mission' ||
                n.type == 'mission_update' ||
                n.type == 'jalon' ||
                n.type == 'jcode',
          )
          .toList();
    } else if (selectedTab.value == 'finances') {
      return notifications
          .where(
            (n) =>
                n.type == 'payment' ||
                n.type == 'payment_alert' ||
                n.type == 'wallet',
          )
          .toList();
    }
    return notifications;
  }

  void selectTab(String tab) {
    selectedTab.value = tab;
  }

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
        .map(
          (n) => NotificationModel(
            id: n.id,
            title: n.title,
            message: n.message,
            type: n.type,
            isRead: true,
            createdAt: n.createdAt,
            data: n.data,
          ),
        )
        .toList();
  }

  Future<void> onNotificationTap(NotificationModel notification) async {
    // Marquer comme lu sur le serveur et localement
    await markRead(notification.id);

    // Combiner le type principal et les données additionnelles
    final Map<String, dynamic> routingData = {
      'type': notification.type,
      ...?notification.data,
    };
    Get.find<NotificationService>().routeToTarget(routingData);
  }
}
