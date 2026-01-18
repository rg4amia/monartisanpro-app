import 'package:flutter/material.dart';

/// Jeton status badge widget
class JetonStatusBadge extends StatelessWidget {
  final String status;

  const JetonStatusBadge({super.key, required this.status});

  @override
  Widget build(BuildContext context) {
    final statusInfo = _getStatusInfo(status);

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
      decoration: BoxDecoration(
        color: statusInfo['color'].withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: statusInfo['color'].withValues(alpha: 0.3),
          width: 1,
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(statusInfo['icon'], size: 16, color: statusInfo['color']),
          const SizedBox(width: 8),
          Text(
            statusInfo['label'],
            style: TextStyle(
              color: statusInfo['color'],
              fontWeight: FontWeight.w500,
              fontSize: 14,
            ),
          ),
        ],
      ),
    );
  }

  Map<String, dynamic> _getStatusInfo(String status) {
    switch (status.toUpperCase()) {
      case 'ACTIVE':
        return {
          'label': 'Actif',
          'color': Colors.green,
          'icon': Icons.check_circle,
        };
      case 'PARTIALLY_USED':
        return {
          'label': 'Partiellement utilisé',
          'color': Colors.orange,
          'icon': Icons.pie_chart,
        };
      case 'FULLY_USED':
        return {
          'label': 'Entièrement utilisé',
          'color': Colors.blue,
          'icon': Icons.done_all,
        };
      case 'EXPIRED':
        return {
          'label': 'Expiré',
          'color': Colors.red,
          'icon': Icons.access_time,
        };
      default:
        return {'label': status, 'color': Colors.grey, 'icon': Icons.help};
    }
  }
}
