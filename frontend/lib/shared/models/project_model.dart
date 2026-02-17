import 'package:json_annotation/json_annotation.dart';
import 'user_model.dart';
import 'trade_model.dart';

part 'project_model.g.dart';

@JsonSerializable()
class Project {
  final int id;
  @JsonKey(name: 'client_id')
  final int clientId;
  @JsonKey(name: 'artisan_id')
  final int? artisanId;
  final String title;
  final String description;
  final Location location;
  final String address;
  final String
  status; // pending, quoted, accepted, funded, in_progress, completed, cancelled, disputed
  final User? client;
  final User? artisan;
  final List<Quote>? quotes;
  @JsonKey(name: 'created_at')
  final DateTime createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime updatedAt;

  Project({
    required this.id,
    required this.clientId,
    this.artisanId,
    required this.title,
    required this.description,
    required this.location,
    required this.address,
    required this.status,
    this.client,
    this.artisan,
    this.quotes,
    required this.createdAt,
    required this.updatedAt,
  });

  factory Project.fromJson(Map<String, dynamic> json) =>
      _$ProjectFromJson(json);
  Map<String, dynamic> toJson() => _$ProjectToJson(this);

  // Helper getters
  bool get isPending => status == 'pending';
  bool get isQuoted => status == 'quoted';
  bool get isAccepted => status == 'accepted';
  bool get isFunded => status == 'funded';
  bool get isInProgress => status == 'in_progress';
  bool get isCompleted => status == 'completed';
  bool get isCancelled => status == 'cancelled';
  bool get isDisputed => status == 'disputed';

  String get statusLabel {
    switch (status) {
      case 'pending':
        return 'En attente';
      case 'quoted':
        return 'Devis reçus';
      case 'accepted':
        return 'Devis accepté';
      case 'funded':
        return 'Financé';
      case 'in_progress':
        return 'En cours';
      case 'completed':
        return 'Terminé';
      case 'cancelled':
        return 'Annulé';
      case 'disputed':
        return 'En litige';
      default:
        return status;
    }
  }
}

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
  @JsonKey(name: 'valid_until')
  final DateTime validUntil;
  final String status; // draft, sent, accepted, rejected, expired
  final String? notes;
  final User? artisan;
  final List<QuoteItem>? items;
  @JsonKey(name: 'created_at')
  final DateTime createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime updatedAt;

  Quote({
    required this.id,
    required this.projectId,
    required this.artisanId,
    required this.totalAmount,
    required this.materialAmount,
    required this.laborAmount,
    required this.materialPercentage,
    required this.laborPercentage,
    required this.validUntil,
    required this.status,
    this.notes,
    this.artisan,
    this.items,
    required this.createdAt,
    required this.updatedAt,
  });

  factory Quote.fromJson(Map<String, dynamic> json) => _$QuoteFromJson(json);
  Map<String, dynamic> toJson() => _$QuoteToJson(this);

  // Helper getters
  bool get isDraft => status == 'draft';
  bool get isSent => status == 'sent';
  bool get isAccepted => status == 'accepted';
  bool get isRejected => status == 'rejected';
  bool get isExpired => status == 'expired';

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

  String get formattedTotal => '${totalAmount.toStringAsFixed(0)} FCFA';
  String get formattedMaterial => '${materialAmount.toStringAsFixed(0)} FCFA';
  String get formattedLabor => '${laborAmount.toStringAsFixed(0)} FCFA';
}

@JsonSerializable()
class QuoteItem {
  final int id;
  @JsonKey(name: 'quote_id')
  final int quoteId;
  @JsonKey(name: 'item_type')
  final String itemType; // material, labor
  final String description;
  final double quantity;
  final String? unit;
  @JsonKey(name: 'unit_price')
  final double unitPrice;
  final double total;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;

  QuoteItem({
    required this.id,
    required this.quoteId,
    required this.itemType,
    required this.description,
    required this.quantity,
    this.unit,
    required this.unitPrice,
    required this.total,
    this.createdAt,
    this.updatedAt,
  });

  factory QuoteItem.fromJson(Map<String, dynamic> json) =>
      _$QuoteItemFromJson(json);
  Map<String, dynamic> toJson() => _$QuoteItemToJson(this);

  bool get isMaterial => itemType == 'material';
  bool get isLabor => itemType == 'labor';
}

@JsonSerializable()
class CreateProjectRequest {
  final String title;
  final String description;
  final double latitude;
  final double longitude;
  final String address;
  @JsonKey(name: 'trade_id')
  final int? tradeId;

  CreateProjectRequest({
    required this.title,
    required this.description,
    required this.latitude,
    required this.longitude,
    required this.address,
    this.tradeId,
  });

  Map<String, dynamic> toJson() => _$CreateProjectRequestToJson(this);
}

@JsonSerializable()
class CreateQuoteRequest {
  @JsonKey(name: 'project_id')
  final int projectId;
  final List<QuoteItemRequest> materials;
  final List<QuoteItemRequest> labor;
  @JsonKey(name: 'valid_days')
  final int validDays;
  final String? notes;

  CreateQuoteRequest({
    required this.projectId,
    required this.materials,
    required this.labor,
    this.validDays = 7,
    this.notes,
  });

  Map<String, dynamic> toJson() => _$CreateQuoteRequestToJson(this);
}

@JsonSerializable()
class QuoteItemRequest {
  final String description;
  final double quantity;
  final String? unit;
  @JsonKey(name: 'unit_price')
  final double unitPrice;

  QuoteItemRequest({
    required this.description,
    required this.quantity,
    this.unit,
    required this.unitPrice,
  });

  factory QuoteItemRequest.fromJson(Map<String, dynamic> json) =>
      _$QuoteItemRequestFromJson(json);
  Map<String, dynamic> toJson() => _$QuoteItemRequestToJson(this);

  double get total => quantity * unitPrice;
}
