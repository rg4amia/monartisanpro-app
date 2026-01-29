import 'package:json_annotation/json_annotation.dart';

part 'trade.g.dart';

@JsonSerializable()
class Trade {
  final int id;
  final String code;
  final String name;
  @JsonKey(name: 'sector_id')
  final int sectorId;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;

  const Trade({
    required this.id,
    required this.code,
    required this.name,
    required this.sectorId,
    this.createdAt,
    this.updatedAt,
  });

  factory Trade.fromJson(Map<String, dynamic> json) => _$TradeFromJson(json);
  Map<String, dynamic> toJson() => _$TradeToJson(this);

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is Trade && runtimeType == other.runtimeType && id == other.id;

  @override
  int get hashCode => id.hashCode;

  @override
  String toString() => 'Trade(id: $id, code: $code, name: $name)';
}
