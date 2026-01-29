/// Trade model representing a professional trade/métier
class Trade {
  final int id;
  final String code;
  final String name;
  final int sectorId;
  final String? sectorName;

  Trade({
    required this.id,
    required this.code,
    required this.name,
    required this.sectorId,
    this.sectorName,
  });

  factory Trade.fromJson(Map<String, dynamic> json) {
    return Trade(
      id: json['id'] as int,
      code: json['code'] as String,
      name: json['name'] as String,
      sectorId: json['sector_id'] as int,
      sectorName: json['sector_name'] as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'code': code,
      'name': name,
      'sector_id': sectorId,
      'sector_name': sectorName,
    };
  }
}
