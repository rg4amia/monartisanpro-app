import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../core/theme/app_colors.dart';
import '../../../core/utils/formatters.dart';
import '../controllers/notifications_controller.dart';

class NotificationsScreen extends GetView<NotificationsController> {
  const NotificationsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: _buildAppBar(),
      body: Column(
        children: [
          _buildTabs(),
          const Divider(height: 1, color: Color(0xFFE5E7EB)),
          Expanded(child: _buildContent()),
        ],
      ),
    );
  }

  PreferredSizeWidget _buildAppBar() {
    return AppBar(
      backgroundColor: Colors.white,
      elevation: 0,
      leading: IconButton(
        icon: const Icon(Icons.arrow_back, color: Colors.black87),
        onPressed: () => Get.back(),
      ),
      title: const Text(
        'Notifications',
        style: TextStyle(
          color: Colors.black87,
          fontSize: 20,
          fontWeight: FontWeight.w600,
        ),
      ),
      actions: [
        Obx(() {
          final unreadCount = controller.unreadCount;
          if (unreadCount == 0) return const SizedBox.shrink();

          return TextButton.icon(
            onPressed: controller.markAllRead,
            icon: const Icon(Icons.done_all, size: 18),
            label: const Text('Tout marquer'),
            style: TextButton.styleFrom(
              foregroundColor: AppColors.primary,
              textStyle: const TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w500,
              ),
            ),
          );
        }),
        const SizedBox(width: 8),
      ],
    );
  }

  Widget _buildTabs() {
    return Container(
      color: Colors.white,
      child: Obx(() {
        final allCount = controller.notifications.where((n) => !n.isRead).length;
        final missionsCount = controller.notifications
            .where((n) => !n.isRead && (n.type == 'mission' || n.type == 'mission_update' || n.type == 'jalon' || n.type == 'jcode'))
            .length;
        final financesCount = controller.notifications
            .where((n) => !n.isRead && (n.type == 'payment' || n.type == 'payment_alert' || n.type == 'wallet'))
            .length;

        return Row(
          children: [
            _TabItem(
              label: 'Tout',
              badgeCount: allCount,
              isSelected: controller.selectedTab.value == 'all',
              onTap: () => controller.selectTab('all'),
            ),
            _TabItem(
              label: 'Missions',
              badgeCount: missionsCount,
              isSelected: controller.selectedTab.value == 'missions',
              onTap: () => controller.selectTab('missions'),
            ),
            _TabItem(
              label: 'Finances',
              badgeCount: financesCount,
              isSelected: controller.selectedTab.value == 'finances',
              onTap: () => controller.selectTab('finances'),
            ),
          ],
        );
      }),
    );
  }

  Widget _buildContent() {
    return Obx(() {
      if (controller.isLoading.value) {
        return const Center(
          child: CircularProgressIndicator(
            valueColor: AlwaysStoppedAnimation<Color>(AppColors.primary),
          ),
        );
      }

      final filteredNotifications = controller.filteredNotifications;

      if (filteredNotifications.isEmpty) {
        return _buildEmptyState();
      }

      // Grouper les notifications par date
      final groupedNotifications = _groupNotificationsByDate(filteredNotifications);

      return RefreshIndicator(
        onRefresh: controller.load,
        color: AppColors.primary,
        child: ListView.builder(
          padding: const EdgeInsets.only(bottom: 16),
          itemCount: groupedNotifications.length,
          itemBuilder: (context, index) {
            final group = groupedNotifications[index];
            return _NotificationGroup(
              dateLabel: group['label'] as String,
              notifications: group['notifications'] as List,
              controller: controller,
            );
          },
        ),
      );
    });
  }

  Widget _buildEmptyState() {
    return Obx(() {
      final tab = controller.selectedTab.value;
      final (icon, title, subtitle) = _getEmptyStateContent(tab);

      return Center(
        child: Padding(
          padding: const EdgeInsets.all(32),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                padding: const EdgeInsets.all(24),
                decoration: BoxDecoration(
                  color: Colors.grey[100],
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  icon,
                  size: 64,
                  color: Colors.grey[400],
                ),
              ),
              const SizedBox(height: 24),
              Text(
                title,
                style: TextStyle(
                  color: Colors.grey[800],
                  fontSize: 18,
                  fontWeight: FontWeight.w600,
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 8),
              Text(
                subtitle,
                style: TextStyle(
                  color: Colors.grey[600],
                  fontSize: 14,
                  fontWeight: FontWeight.w400,
                  height: 1.5,
                ),
                textAlign: TextAlign.center,
              ),
            ],
          ),
        ),
      );
    });
  }

  (IconData, String, String) _getEmptyStateContent(String tab) {
    switch (tab) {
      case 'missions':
        return (
          Icons.build_outlined,
          'Aucune notification mission',
          'Vous serez notifié ici des mises à jour\nsur vos missions en cours'
        );
      case 'finances':
        return (
          Icons.account_balance_wallet_outlined,
          'Aucune notification financière',
          'Vos transactions et paiements\napparaîtront ici'
        );
      default:
        return (
          Icons.notifications_none_outlined,
          'Aucune notification',
          'Vous n\'avez aucune notification\npour le moment'
        );
    }
  }

  List<Map<String, dynamic>> _groupNotificationsByDate(List notifications) {
    final now = DateTime.now();
    final today = DateTime(now.year, now.month, now.day);
    final yesterday = today.subtract(const Duration(days: 1));
    final lastWeek = today.subtract(const Duration(days: 7));

    final todayList = <dynamic>[];
    final yesterdayList = <dynamic>[];
    final thisWeekList = <dynamic>[];
    final olderList = <dynamic>[];

    for (final notification in notifications) {
      final createdAt = DateTime.parse(notification.createdAt);
      final date = DateTime(createdAt.year, createdAt.month, createdAt.day);

      if (date.isAtSameMomentAs(today)) {
        todayList.add(notification);
      } else if (date.isAtSameMomentAs(yesterday)) {
        yesterdayList.add(notification);
      } else if (date.isAfter(lastWeek)) {
        thisWeekList.add(notification);
      } else {
        olderList.add(notification);
      }
    }

    final groups = <Map<String, dynamic>>[];

    if (todayList.isNotEmpty) {
      groups.add({'label': 'Aujourd\'hui', 'notifications': todayList});
    }
    if (yesterdayList.isNotEmpty) {
      groups.add({'label': 'Hier', 'notifications': yesterdayList});
    }
    if (thisWeekList.isNotEmpty) {
      groups.add({'label': 'Cette semaine', 'notifications': thisWeekList});
    }
    if (olderList.isNotEmpty) {
      groups.add({'label': 'Plus ancien', 'notifications': olderList});
    }

    return groups;
  }
}

// ============================================================================
// NOTIFICATION GROUP - Groupe de notifications par date
// ============================================================================

class _NotificationGroup extends StatelessWidget {
  final String dateLabel;
  final List notifications;
  final NotificationsController controller;

  const _NotificationGroup({
    required this.dateLabel,
    required this.notifications,
    required this.controller,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        // En-tête de section
        Container(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 12),
          color: AppColors.background,
          child: Text(
            dateLabel.toUpperCase(),
            style: TextStyle(
              color: Colors.grey[600],
              fontSize: 12,
              fontWeight: FontWeight.w700,
              letterSpacing: 0.8,
            ),
          ),
        ),

        // Liste des notifications
        Container(
          color: Colors.white,
          child: ListView.separated(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: notifications.length,
            separatorBuilder: (context, index) => Divider(
              height: 1,
              thickness: 1,
              color: Colors.grey[100],
              indent: 72,
            ),
            itemBuilder: (context, index) {
              final notification = notifications[index];
              return _NotificationTile(
                notification: notification,
                onTap: () => controller.onNotificationTap(notification),
                onDismiss: () => controller.markRead(notification.id),
              );
            },
          ),
        ),
      ],
    );
  }
}

// ============================================================================
// TAB ITEM - Onglet avec badge
// ============================================================================

class _TabItem extends StatelessWidget {
  final String label;
  final int badgeCount;
  final bool isSelected;
  final VoidCallback onTap;

  const _TabItem({
    required this.label,
    this.badgeCount = 0,
    required this.isSelected,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: InkWell(
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: 14),
          decoration: BoxDecoration(
            border: Border(
              bottom: BorderSide(
                color: isSelected ? AppColors.primary : Colors.transparent,
                width: 2.5,
              ),
            ),
          ),
          child: Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(
                label,
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: isSelected ? AppColors.primary : Colors.grey[600],
                  fontSize: 15,
                  fontWeight: isSelected ? FontWeight.w600 : FontWeight.w500,
                ),
              ),
              if (badgeCount > 0) ...[
                const SizedBox(width: 6),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 6,
                    vertical: 2,
                  ),
                  decoration: BoxDecoration(
                    color: isSelected ? AppColors.primary : Colors.red,
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Text(
                    badgeCount > 99 ? '99+' : '$badgeCount',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

// ============================================================================
// NOTIFICATION TILE - Tuile de notification avec swipe actions
// ============================================================================

class _NotificationTile extends StatelessWidget {
  final dynamic notification;
  final VoidCallback onTap;
  final VoidCallback onDismiss;

  const _NotificationTile({
    required this.notification,
    required this.onTap,
    required this.onDismiss,
  });

  @override
  Widget build(BuildContext context) {
    final isUnread = !notification.isRead;
    final typeConfig = _getTypeConfig(notification.type);

    return Dismissible(
      key: Key('notification_${notification.id}'),
      direction: DismissDirection.endToStart,
      confirmDismiss: (direction) async {
        onDismiss();
        return false; // Ne pas retirer visuellement, juste marquer comme lu
      },
      background: Container(
        color: AppColors.primary,
        alignment: Alignment.centerRight,
        padding: const EdgeInsets.only(right: 20),
        child: const Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.done, color: Colors.white, size: 24),
            SizedBox(height: 4),
            Text(
              'Marquer lu',
              style: TextStyle(
                color: Colors.white,
                fontSize: 12,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
      ),
      child: InkWell(
        onTap: onTap,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 300),
          color: isUnread ? const Color(0xFFF9FAFB) : Colors.white,
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Icône avec badge non lu
              Stack(
                clipBehavior: Clip.none,
                children: [
                  Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      color: typeConfig['bgColor'],
                      borderRadius: BorderRadius.circular(12),
                    ),
                    child: Icon(
                      typeConfig['icon'],
                      color: typeConfig['iconColor'],
                      size: 24,
                    ),
                  ),
                  if (isUnread)
                    Positioned(
                      top: -2,
                      right: -2,
                      child: Container(
                        width: 12,
                        height: 12,
                        decoration: BoxDecoration(
                          color: AppColors.primary,
                          shape: BoxShape.circle,
                          border: Border.all(color: Colors.white, width: 2),
                        ),
                      ),
                    ),
                ],
              ),
              const SizedBox(width: 14),

              // Contenu
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Text(
                            notification.title,
                            style: TextStyle(
                              color: Colors.black87,
                              fontSize: 15,
                              fontWeight: isUnread ? FontWeight.w700 : FontWeight.w600,
                              height: 1.3,
                            ),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Text(
                          Formatters.timeAgo(notification.createdAt),
                          style: TextStyle(
                            color: Colors.grey[500],
                            fontSize: 12,
                            fontWeight: FontWeight.w400,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 6),
                    Text(
                      notification.message,
                      style: TextStyle(
                        color: Colors.grey[700],
                        fontSize: 14,
                        fontWeight: FontWeight.w400,
                        height: 1.5,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Map<String, dynamic> _getTypeConfig(String type) {
    switch (type) {
      case 'mission':
      case 'mission_update':
        return {
          'icon': Icons.build_outlined,
          'iconColor': const Color(0xFF6366F1),
          'bgColor': const Color(0xFFEEF2FF),
        };
      case 'jalon':
        return {
          'icon': Icons.check_circle_outline,
          'iconColor': const Color(0xFF8B5CF6),
          'bgColor': const Color(0xFFF5F3FF),
        };
      case 'payment':
      case 'payment_alert':
      case 'wallet':
        return {
          'icon': Icons.account_balance_wallet_outlined,
          'iconColor': const Color(0xFF10B981),
          'bgColor': const Color(0xFFD1FAE5),
        };
      case 'verification':
      case 'validation':
        return {
          'icon': Icons.verified_outlined,
          'iconColor': const Color(0xFF3B82F6),
          'bgColor': const Color(0xFFDBEAFE),
        };
      case 'jcode':
      case 'jcode_alert':
        return {
          'icon': Icons.qr_code_2_outlined,
          'iconColor': const Color(0xFFF59E0B),
          'bgColor': const Color(0xFFFEF3C7),
        };
      case 'alert':
      case 'litige':
        return {
          'icon': Icons.warning_amber_outlined,
          'iconColor': const Color(0xFFEF4444),
          'bgColor': const Color(0xFFFEE2E2),
        };
      default:
        return {
          'icon': Icons.notifications_outlined,
          'iconColor': const Color(0xFF6366F1),
          'bgColor': const Color(0xFFEEF2FF),
        };
    }
  }
}
