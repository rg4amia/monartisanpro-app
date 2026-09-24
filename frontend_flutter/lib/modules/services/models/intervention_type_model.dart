import 'package:frontend_flutter/core/utils/json_readers.dart';

class InterventionTypeModel {
  final int id;
  final String name;
  final bool requiresLabor;

  const InterventionTypeModel({
    required this.id,
    required this.name,
    this.requiresLabor = true,
  });

  factory InterventionTypeModel.fromJson(Map<String, dynamic> json) {
    return InterventionTypeModel(
      id: readInt(json['id']) ?? 0,
      name: readString(json['name']) ?? '',
      requiresLabor:
          (json['requiresLabor'] ?? json['requires_labor'] ?? true) == true,
    );
  }
}
