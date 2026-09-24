import 'package:frontend_flutter/core/utils/json_readers.dart';

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
      id: readInt(json['id']) ?? 0,
      name: readString(json['name']) ?? '',
      icon: readString(json['icon']),
      color: readString(json['color']),
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
