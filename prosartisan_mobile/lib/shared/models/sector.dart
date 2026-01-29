import 'package:json_annotation/json_annotation.dart';
import 'trade.dart';

part 'sector.g.dart';

@JsonSerializable()
class Sector {
  final int id;
  final String code;
  final String name;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;
  final List<Trade>? trades;

  const Sector({
    required this.id,
    required this.code,
    required this.name,
    this.createdAt,
    this.updatedAt,
    this.trades,
  });

  factory Sector.fromJson(Map<String, dynamic> json) => _$SectorFromJson(json);
  Map<String, dynamic> toJson() => _$SectorToJson(this);

  @override
  bool operator ==(Object other) =>
      identical(this, other) ||
      other is Sector && runtimeType == other.runtimeType && id == other.id;

  @override
  int get hashCode => id.hashCode;

  @override
  String toString() => 'Sector(id: $id, code: $code, name: $name)';
}
