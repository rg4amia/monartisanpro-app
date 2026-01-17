import 'package:flutter/material.dart';
import '../../domain/models/dispute.dart';

/// Widget for displaying dispute status as a colored chip
class DisputeStatusChip extends StatelessWidget {
  final DisputeStatus status;

  const DisputeStatusChip({Key? key, required this.status}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    return Chip(
      label: Text(
        status.label,
        style: TextStyle(
          color: _getTextColor(),
          fontWeight: FontWeight.w500,
          fontSize: 12,
        ),
      ),
      backgroundColor: _getBackgroundColor(),
      side: BorderSide(color: _getBorderColor(), width: 1),
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
    );
  }

  Color _getBackgroundColor() {
    switch (status.value) {
      case 'OPEN':
        return Colors.orange[50]!;
      case 'IN_MEDIATION':
        return Colors.blue[50]!;
      case 'IN_ARBITRATION':
        return Colors.purple[50]!;
      case 'RESOLVED':
        return Colors.green[50]!;
      case 'CLOSED':
        return Colors.grey[100]!;
      default:
        return Colors.grey[100]!;
    }
  }

  Color _getBorderColor() {
    switch (status.value) {
      case 'OPEN':
        return Colors.orange[300]!;
      case 'IN_MEDIATION':
        return Colors.blue[300]!;
      case 'IN_ARBITRATION':
        return Colors.purple[300]!;
      case 'RESOLVED':
        return Colors.green[300]!;
      case 'CLOSED':
        return Colors.grey[400]!;
      default:
        return Colors.grey[400]!;
    }
  }

  Color _getTextColor() {
    switch (status.value) {
      case 'OPEN':
        return Colors.orange[800]!;
      case 'IN_MEDIATION':
        return Colors.blue[800]!;
      case 'IN_ARBITRATION':
        return Colors.purple[800]!;
      case 'RESOLVED':
        return Colors.green[800]!;
      case 'CLOSED':
        return Colors.grey[700]!;
      default:
        return Colors.grey[700]!;
    }
  }
}
