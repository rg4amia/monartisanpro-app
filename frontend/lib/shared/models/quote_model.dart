import 'package:json_annotation/json_annotation.dart';

part 'quote_model.g.dart';

@JsonSerializable()
class Quote {
  final int id;
  @JsonKey(name: 'project_id')
  final int projectId;
  @JsonKey(name: 'artisan_id')
  final int artisanId;
  @JsonKey(name: 'total_amount')
  final double totalAmount;
  @JsonKey(name: 'material_amount')
  final double materialAmount;
  @JsonKey(name: 'labor_amount')
  final double laborAmount;
  @JsonKey(name: 'material_percentage')
  final double materialPercentage;
  @JsonKey(name: 'labor_percentage')
  final double laborPercentage;
  @JsonKey(name: 'valid_days')
  final int validDays;
  @JsonKey(name: 'valid_until')
  final String? validUntil;
  final String status;
  final String? notes;
  @JsonKey(name: 'rejection_reason')
  final String? rejectionReason;
  @JsonKey(name: 'sent_at')
  final String? sentAt;
  @JsonKey(name: 'accepted_at')
  final String? acceptedAt;
  @JsonKey(name: 'rejected_at')
  final String? rejectedAt;
  @JsonKey(name: 'created_at')
  final String createdAt;
  final List<QuoteItem>? items;

  Quote({
    required this.id,
    required this.projectId,
    required this.artisanId,
    required this.totalAmount,
    required this.materialAmount,
    required this.laborAmount,
    required this.materialPercentage,
    required this.laborPercentage,
    required this.validDays,
    this.validUntil,
    required this.status,
    this.notes,
    this.rejectionReason,
    this.sentAt,
    this.acceptedAt,
    this.rejectedAt,
    required this.createdAt,
    this.items,
  });

  factory Quote.fromJson(Map<String, dynamic> json) => _$QuoteFromJson(json);
  Map<String, dynamic> toJson() => _$QuoteToJson(this);

  String get statusLabel {
    switch (status) {
      case 'draft':
        return 'Brouillon';
      case 'sent':
        return 'Envoyé';
      case 'accepted':
        return 'Accepté';
      case 'rejected':
        return 'Rejeté';
      case 'expired':
        return 'Expiré';
      default:
        return status;
    }
  }

  bool get isExpired => status == 'expired';
  bool get isAccepted => status == 'accepted';
  bool get isRejected => status == 'rejected';
  bool get isPending => status == 'sent';
  bool get isSent => status == 'sent';

  // Formatted values
  String get formattedTotal => '${totalAmount.toStringAsFixed(0)} FCFA';
  String get formattedMaterial => '${materialAmount.toStringAsFixed(0)} FCFA';
  String get formattedLabor => '${laborAmount.toStringAsFixed(0)} FCFA';
}

@JsonSerializable()
class QuoteItem {
  final int id;
  @JsonKey(name: 'quote_id')
  final int quoteId;
  final String type;
  final String description;
  final double quantity;
  final String unit;
  @JsonKey(name: 'unit_price')
  final double unitPrice;
  final double total;

  QuoteItem({
    required this.id,
    required this.quoteId,
    required this.type,
    required this.description,
    required this.quantity,
    required this.unit,
    required this.unitPrice,
    required this.total,
  });

  factory QuoteItem.fromJson(Map<String, dynamic> json) =>
      _$QuoteItemFromJson(json);
  Map<String, dynamic> toJson() => _$QuoteItemToJson(this);

  bool get isMaterial => type == 'material';
  bool get isLabor => type == 'labor';
}
