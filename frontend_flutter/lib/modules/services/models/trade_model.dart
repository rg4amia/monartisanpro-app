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
      id: json['id'] as int,
      name: json['name'] as String,
      sectorId: json['sectorId'] as int,
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
