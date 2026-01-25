import 'package:prosartisan_mobile/features/marketplace/data/models/trade_model.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/sector.dart';

class SectorModel extends Sector {
  const SectorModel({required super.id, required super.name, super.trades});

  factory SectorModel.fromJson(Map<String, dynamic> json) {
    return SectorModel(
      id: json['id'] as int,
      name: json['name'] as String,
      trades: json['trades'] != null
          ? (json['trades'] as List)
                .map((e) => TradeModel.fromJson(e as Map<String, dynamic>))
                .toList()
          : [],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'trades': trades.map((e) => (e as TradeModel).toJson()).toList(),
    };
  }
}
