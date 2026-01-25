import 'package:prosartisan_mobile/core/domain/value_objects/gps_coordinates.dart';
import 'package:prosartisan_mobile/features/marketplace/data/models/trade_model.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/mission.dart';

class MissionModel extends Mission {
  const MissionModel({
    required super.id,
    required super.clientId,
    required super.description,
    required super.category,
    super.trade,
    super.tradeId,
    required super.location,
    required super.budgetMin,
    required super.budgetMax,
    required super.status,
    required super.quoteIds,
    required super.createdAt,
    super.updatedAt,
  });

  factory MissionModel.fromJson(Map<String, dynamic> json) {
    return MissionModel(
      id: json['id'] as String,
      clientId: json['client_id'] as String,
      description: json['description'] as String,
      category: TradeCategory.fromString(json['trade_category'] as String),
      trade: json['trade'] != null
          ? TradeModel.fromJson(json['trade'] as Map<String, dynamic>)
          : null,
      tradeId: json['trade_id'] as int?,
      location: GPSCoordinates.fromJson(
        json['location'] as Map<String, dynamic>,
      ),
      budgetMin: (json['budget_min'] as num).toDouble(),
      budgetMax: (json['budget_max'] as num).toDouble(),
      status: MissionStatus.fromString(json['status'] as String),
      quoteIds: List<String>.from(json['quote_ids'] ?? []),
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: json['updated_at'] != null
          ? DateTime.parse(json['updated_at'] as String)
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'client_id': clientId,
      'description': description,
      'trade_category': category.value,
      'trade_id': tradeId,
      'location': location.toJson(),
      'budget_min': budgetMin,
      'budget_max': budgetMax,
      'status': status.value,
      'quote_ids': quoteIds,
      'created_at': createdAt.toIso8601String(),
      'updated_at': updatedAt?.toIso8601String(),
    };
  }

  factory MissionModel.fromEntity(Mission mission) {
    return MissionModel(
      id: mission.id,
      clientId: mission.clientId,
      description: mission.description,
      category: mission.category,
      location: mission.location,
      budgetMin: mission.budgetMin,
      budgetMax: mission.budgetMax,
      status: mission.status,
      quoteIds: mission.quoteIds,
      createdAt: mission.createdAt,
      updatedAt: mission.updatedAt,
    );
  }

  Mission toEntity() {
    return Mission(
      id: id,
      clientId: clientId,
      description: description,
      category: category,
      location: location,
      budgetMin: budgetMin,
      budgetMax: budgetMax,
      status: status,
      quoteIds: quoteIds,
      createdAt: createdAt,
      updatedAt: updatedAt,
    );
  }
}
