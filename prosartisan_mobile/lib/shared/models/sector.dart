import 'trade.dart';

/// Sector model representing a professional sector
class Sector {
  final int id;
  final String code;
  final String name;
  final List<Trade> trades;

  Sector({
    required this.id,
    required this.code,
    required this.name,
    required this.trades,
  });

  factory Sector.fromJson(Map<String, dynamic> json) {
    return Sector(
      id: json['id'] as int,
      code: json['code'] as String,
      name: json['name'] as String,
      trades:
          (json['trades'] as List<dynamic>?)
              ?.map((trade) => Trade.fromJson(trade as Map<String, dynamic>))
              .toList() ??
          [],
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'code': code,
      'name': name,
      'trades': trades.map((trade) => trade.toJson()).toList(),
    };
  }
}
