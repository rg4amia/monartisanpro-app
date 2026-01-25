import 'package:prosartisan_mobile/features/marketplace/domain/entities/trade.dart';

class TradeModel extends Trade {
  const TradeModel({
    required super.id,
    required super.name,
    required super.sectorId,
  });

  factory TradeModel.fromJson(Map<String, dynamic> json) {
    return TradeModel(
      id: json['id'] as int,
      name: json['name'] as String,
      sectorId: (json['sector_id'] ?? json['pivot']?['sector_id'] ?? 0) as int,
    );
  }

  Map<String, dynamic> toJson() {
    return {'id': id, 'name': name, 'sector_id': sectorId};
  }
}
