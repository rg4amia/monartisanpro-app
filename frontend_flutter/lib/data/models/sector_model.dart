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
        id: json['id'] as int,
        name: json['name'] as String,
        icon: json['icon'] as String?,
        color: json['color'] as String?,
        trades: (json['trades'] as List<dynamic>?)
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
        id: json['id'] as int,
        sectorId: json['sectorId'] as int,
        name: json['name'] as String,
      );
}
