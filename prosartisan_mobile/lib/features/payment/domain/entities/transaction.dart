/// Transaction entity for financial transactions
///
/// Requirements: 4.6, 13.6
class Transaction {
  final String id;
  final String fromUserId;
  final String toUserId;
  final int amountCentimes;
  final String type;
  final String status;
  final String? mobileMoneyReference;
  final String createdAt;
  final String? completedAt;

  const Transaction({
    required this.id,
    required this.fromUserId,
    required this.toUserId,
    required this.amountCentimes,
    required this.type,
    required this.status,
    this.mobileMoneyReference,
    required this.createdAt,
    this.completedAt,
  });

  /// Get formatted amount
  String get amountFormatted => _formatAmount(amountCentimes);

  /// Check if transaction is completed
  bool get isCompleted => status == 'COMPLETED';

  /// Check if transaction is pending
  bool get isPending => status == 'PENDING';

  /// Check if transaction failed
  bool get isFailed => status == 'FAILED';

  /// Format amount from centimes to display string
  String _formatAmount(int centimes) {
    final francs = centimes / 100;
    return '${francs.toStringAsFixed(0).replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (Match m) => '${m[1]} ')} FCFA';
  }

  /// Create from JSON
  factory Transaction.fromJson(Map<String, dynamic> json) {
    return Transaction(
      id: json['id'],
      fromUserId: json['from_user_id'],
      toUserId: json['to_user_id'],
      amountCentimes: json['amount']['centimes'],
      type: json['type'],
      status: json['status'],
      mobileMoneyReference: json['mobile_money_reference'],
      createdAt: json['created_at'],
      completedAt: json['completed_at'],
    );
  }

  /// Convert to JSON
  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'from_user_id': fromUserId,
      'to_user_id': toUserId,
      'amount': {'centimes': amountCentimes},
      'type': type,
      'status': status,
      'mobile_money_reference': mobileMoneyReference,
      'created_at': createdAt,
      'completed_at': completedAt,
    };
  }
}
