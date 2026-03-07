class SectorModel {
  final int id;
  final String name;
  final String? icon;
  final String? color;

  SectorModel({
    required this.id,
    required this.name,
    this.icon,
    this.color,
  });

  factory SectorModel.fromJson(Map<String, dynamic> json) {
    return SectorModel(
      id: json['id'] as int,
      name: json['name'] as String,
      icon: json['icon'] as String?,
      color: json['color'] as String?,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'name': name,
      'icon': icon,
      'color': color,
    };
  }
}
