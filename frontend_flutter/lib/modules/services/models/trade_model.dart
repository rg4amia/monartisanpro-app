import 'package:frontend_flutter/core/utils/json_readers.dart';

class TradeModel {
  final int id;
  final String name;
  final int sectorId;

  TradeModel({
    required this.id,
    required this.name,
    required this.sectorId,
  });

  factory TradeModel.fromJson(Map<String, dynamic> json) {
    return TradeModel(
      id: readInt(json['id']) ?? 0,
      name: readString(json['name']) ?? '',
      sectorId: readInt(json['sectorId']) ?? 0,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'sectorId': sectorId,
    };
  }
}
