import 'trade.dart';

/// Sector model representing a professional sector
class Sector {
  final int id;
  final String code;
  final String name;
  final DateTime? createdAt;
  final DateTime? updatedAt;
  final List<Trade>? trades;

  Sector({
    required this.id,
    required this.code,
    required this.name,
    this.createdAt,
    this.updatedAt,
    this.trades,
  });

  factory Sector.fromJson(Map<String, dynamic> json) {
    return Sector(
      id: json['id'] as int,
      code: json['code'] as String,
      name: json['name'] as String,
      createdAt: json['created_at'] != null
          ? DateTime.parse(json['created_at'] as String)
          : null,
      updatedAt: json['updated_at'] != null
          ? DateTime.parse(json['updated_at'] as String)
          : null,
      trades: (json['trades'] as List<dynamic>?)
          ?.map((trade) => Trade.fromJson(trade as Map<String, dynamic>))
          .toList(),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'code': code,
      'name': name,
      'created_at': createdAt?.toIso8601String(),
      'updated_at': updatedAt?.toIso8601String(),
      'trades': trades?.map((trade) => trade.toJson()).toList(),
    };
  }
}
