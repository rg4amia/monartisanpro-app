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
      id: json['id'] as int,
      name: json['name'] as String,
      requiresLabor:
          (json['requiresLabor'] ?? json['requires_labor'] ?? true) == true,
    );
  }
}
