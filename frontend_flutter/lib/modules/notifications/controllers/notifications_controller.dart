import 'package:get/get.dart';

import '../../../core/services/notification_service.dart';
import '../../../core/utils/error_handler.dart';
import '../../../data/models/notification_model.dart';
import '../../../data/repositories/notification_repository.dart';

/// Liste des notifications, chargée page par page.
///
/// Le nombre de non lues et les onglets viennent du serveur : ils portent sur
/// toutes les notifications, pas sur la page affichée. Un échec est annoncé
/// par [errorMsg] ; la liste déjà affichée est conservée, jamais vidée.
class NotificationsController extends GetxController {
  NotificationsController({NotificationRepository? repository})
      : _repo = repository ?? NotificationRepository();

  static const allTab = 'all';

  final NotificationRepository _repo;

  final notifications = <NotificationModel>[].obs;
  final domains = <NotificationDomain>[].obs;
  final unread = 0.obs;
  final isLoading = false.obs;
  final isLoadingMore = false.obs;
  final hasMore = false.obs;
  final errorMsg = RxnString();
  final selectedTab = allTab.obs;

  int _page = 1;

  /// Numéro de la demande en cours : la réponse d'un onglet quitté est ignorée.
  int _request = 0;

  int get unreadCount => unread.value;

  String? get _domain => selectedTab.value == allTab ? null : selectedTab.value;

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load() async {
    final request = ++_request;
    isLoading.value = true;
    errorMsg.value = null;

    try {
      final page = await _repo.fetchPage(domain: _domain);
      if (request != _request) return;

      _page = 1;
      notifications.assignAll(page.items);
      _applyMeta(page);
    } catch (e) {
      if (request != _request) return;
      errorMsg.value = ErrorHandler.getErrorMessage(e);
    } finally {
      if (request == _request) isLoading.value = false;
    }
  }

  Future<void> loadMore() async {
    if (!hasMore.value || isLoadingMore.value || isLoading.value) return;

    final request = _request;
    isLoadingMore.value = true;
    errorMsg.value = null;

    try {
      final page = await _repo.fetchPage(page: _page + 1, domain: _domain);
      if (request != _request) return;

      _page += 1;
      notifications.addAll(page.items);
      _applyMeta(page);
    } catch (e) {
      if (request != _request) return;
      errorMsg.value = ErrorHandler.getErrorMessage(e);
    } finally {
      isLoadingMore.value = false;
    }
  }

  void _applyMeta(NotificationPage page) {
    hasMore.value = page.hasMore;
    unread.value = page.unread;
    domains.assignAll(page.domains);
  }

  Future<void> selectTab(String tab) async {
    if (selectedTab.value == tab) return;

    selectedTab.value = tab;
    notifications.clear();
    hasMore.value = false;
    await load();
  }

  Future<void> markRead(int id) async {
    await _repo.markRead(id);

    final index = notifications.indexWhere((n) => n.id == id);
    if (index == -1) {
      // Notification hors de la page affichée (touchée depuis un push).
      if (unread.value > 0) unread.value -= 1;
      return;
    }

    final notification = notifications[index];
    if (notification.isRead) return;

    notifications[index] = notification.asRead();
    if (unread.value > 0) unread.value -= 1;

    final domainIndex = domains.indexWhere((d) => d.key == notification.domain);
    if (domainIndex != -1) {
      final domain = domains[domainIndex];
      domains[domainIndex] = domain.withUnread(domain.unread - 1);
    }
  }

  Future<void> markAllRead() async {
    await _repo.markAllRead();
    notifications.assignAll(notifications.map((n) => n.asRead()).toList());
    domains.assignAll(domains.map((d) => d.withUnread(0)).toList());
    unread.value = 0;
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
