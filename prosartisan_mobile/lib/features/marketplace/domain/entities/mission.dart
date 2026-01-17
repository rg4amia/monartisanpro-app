import 'package:prosartisan_mobile/core/domain/value_objects/gps_coordinates.dart';

enum TradeCategory {
  plumber('PLUMBER', 'Plombier'),
  electrician('ELECTRICIAN', 'Électricien'),
  mason('MASON', 'Maçon');

  const TradeCategory(this.value, this.displayName);
  final String value;
  final String displayName;

  static TradeCategory fromString(String value) {
    return TradeCategory.values.firstWhere(
      (category) => category.value == value,
      orElse: () => throw ArgumentError('Invalid trade category: $value'),
    );
  }
}

enum MissionStatus {
  open('OPEN', 'Ouvert'),
  quoted('QUOTED', 'Devis reçus'),
  accepted('ACCEPTED', 'Accepté'),
  cancelled('CANCELLED', 'Annulé');

  const MissionStatus(this.value, this.displayName);
  final String value;
  final String displayName;

  static MissionStatus fromString(String value) {
    return MissionStatus.values.firstWhere(
      (status) => status.value == value,
      orElse: () => throw ArgumentError('Invalid mission status: $value'),
    );
  }
}

class Mission {
  final String id;
  final String clientId;
  final String description;
  final TradeCategory category;
  final GPSCoordinates location;
  final double budgetMin;
  final double budgetMax;
  final MissionStatus status;
  final List<String> quoteIds;
  final DateTime createdAt;
  final DateTime? updatedAt;

  const Mission({
    required this.id,
    required this.clientId,
    required this.description,
    required this.category,
    required this.location,
    required this.budgetMin,
    required this.budgetMax,
    required this.status,
    required this.quoteIds,
    required this.createdAt,
    this.updatedAt,
  });

  bool get canReceiveMoreQuotes =>
      quoteIds.length < 3 && status == MissionStatus.open;

  Mission copyWith({
    String? id,
    String? clientId,
    String? description,
    TradeCategory? category,
    GPSCoordinates? location,
    double? budgetMin,
    double? budgetMax,
    MissionStatus? status,
    List<String>? quoteIds,
    DateTime? createdAt,
    DateTime? updatedAt,
  }) {
    return Mission(
      id: id ?? this.id,
      clientId: clientId ?? this.clientId,
      description: description ?? this.description,
      category: category ?? this.category,
      location: location ?? this.location,
      budgetMin: budgetMin ?? this.budgetMin,
      budgetMax: budgetMax ?? this.budgetMax,
      status: status ?? this.status,
      quoteIds: quoteIds ?? this.quoteIds,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }

  @override
  bool operator ==(Object other) {
    if (identical(this, other)) return true;
    return other is Mission && other.id == id;
  }

  @override
  int get hashCode => id.hashCode;
}
