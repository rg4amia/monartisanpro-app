import 'package:json_annotation/json_annotation.dart';

part 'project_model.g.dart';

@JsonSerializable()
class Project {
  final int id;
  final String title;
  final String description;
  final String status;
  final String address;
  final double? latitude;
  final double? longitude;
  @JsonKey(name: 'budget_min')
  final double? budgetMin;
  @JsonKey(name: 'budget_max')
  final double? budgetMax;
  @JsonKey(name: 'final_amount')
  final double? finalAmount;
  @JsonKey(name: 'expected_completion_date')
  final String? expectedCompletionDate;
  @JsonKey(name: 'quote_count')
  final int quoteCount;
  @JsonKey(name: 'created_at')
  final String createdAt;
  final ProjectUser client;
  final ProjectUser? artisan;
  final ProjectTrade trade;

  Project({
    required this.id,
    required this.title,
    required this.description,
    required this.status,
    required this.address,
    this.latitude,
    this.longitude,
    this.budgetMin,
    this.budgetMax,
    this.finalAmount,
    this.expectedCompletionDate,
    required this.quoteCount,
    required this.createdAt,
    required this.client,
    this.artisan,
    required this.trade,
  });

  factory Project.fromJson(Map<String, dynamic> json) =>
      _$ProjectFromJson(json);
  Map<String, dynamic> toJson() => _$ProjectToJson(this);

  String get statusLabel {
    switch (status) {
      case 'pending':
        return 'En attente';
      case 'awaiting_quotes':
        return 'En attente de devis';
      case 'quoted':
        return 'Devis reçu';
      case 'payment_pending':
        return 'Paiement en attente';
      case 'in_progress':
        return 'En cours';
      case 'completed':
        return 'Terminé';
      case 'cancelled':
        return 'Annulé';
      default:
        return status;
    }
  }

  // Status helpers
  bool get isPending => status == 'pending' || status == 'awaiting_quotes';
  bool get isQuoted => status == 'quoted';
  bool get isPaymentPending => status == 'payment_pending';
  bool get isInProgress => status == 'in_progress';
  bool get isCompleted => status == 'completed';
  bool get isCancelled => status == 'cancelled';

  // Placeholder for quotes (will be populated from API)
  List<dynamic>? quotes;
}

@JsonSerializable()
class ProjectUser {
  final int id;
  final String name;
  final String? phone;
  final String? avatar;
  @JsonKey(name: 'trade_name')
  final String? tradeName;

  ProjectUser({
    required this.id,
    required this.name,
    this.phone,
    this.avatar,
    this.tradeName,
  });

  factory ProjectUser.fromJson(Map<String, dynamic> json) =>
      _$ProjectUserFromJson(json);
  Map<String, dynamic> toJson() => _$ProjectUserToJson(this);
}

@JsonSerializable()
class ProjectTrade {
  final int id;
  final String name;

  ProjectTrade({required this.id, required this.name});

  factory ProjectTrade.fromJson(Map<String, dynamic> json) =>
      _$ProjectTradeFromJson(json);
  Map<String, dynamic> toJson() => _$ProjectTradeToJson(this);
}
