import 'package:flutter/material.dart';

/// GPS status indicator widget
class GPSStatusIndicator extends StatelessWidget {
  final bool hasPermission;
  final double accuracy;

  const GPSStatusIndicator({
    Key? key,
    required this.hasPermission,
    required this.accuracy,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    if (!hasPermission) {
      return Row(
        children: [
          Icon(Icons.location_off, color: Colors.red.shade600, size: 20),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              'Permission GPS requise pour la validation',
              style: TextStyle(
                color: Colors.red.shade700,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ],
      );
    }

    final isAccurate = accuracy <= 10.0;
    final color = isAccurate ? Colors.green : Colors.orange;

    return Row(
      children: [
        Icon(
          isAccurate ? Icons.gps_fixed : Icons.gps_not_fixed,
          color: color.shade600,
          size: 20,
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Text(
            isAccurate
                ? 'GPS précis (${accuracy.toStringAsFixed(1)}m)'
                : 'GPS imprécis (${accuracy.toStringAsFixed(1)}m)',
            style: TextStyle(
              color: color.shade700,
              fontWeight: FontWeight.w500,
            ),
          ),
        ),
      ],
    );
  }
}
