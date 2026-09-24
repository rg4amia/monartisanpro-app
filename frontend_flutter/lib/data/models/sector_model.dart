import 'package:frontend_flutter/core/utils/json_readers.dart';

class SectorModel {
  final int id;
  final String name;
  final String? icon;
  final String? color;
  final List<TradeModel> trades;

  const SectorModel({
    required this.id,
    required this.name,
    this.icon,
    this.color,
    this.trades = const [],
  });

  factory SectorModel.fromJson(Map<String, dynamic> json) => SectorModel(
        id: readInt(json['id']) ?? 0,
        name: readString(json['name']) ?? '',
        icon: readString(json['icon']),
        color: readString(json['color']),
        trades: readList(json['trades'])
                ?.map((e) => TradeModel.fromJson(e as Map<String, dynamic>))
                .toList() ??
            [],
      );
}

class TradeModel {
  final int id;
  final int sectorId;
  final String name;

  const TradeModel({
    required this.id,
    required this.sectorId,
    required this.name,
  });

  factory TradeModel.fromJson(Map<String, dynamic> json) => TradeModel(
        id: readInt(json['id']) ?? 0,
        sectorId: readInt(json['sectorId']) ?? 0,
        name: readString(json['name']) ?? '',
      );
}
