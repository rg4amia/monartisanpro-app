enum DevisStatus {
  pending('PENDING', 'En attente'),
  accepted('ACCEPTED', 'Accepté'),
  rejected('REJECTED', 'Rejeté');

  const DevisStatus(this.value, this.displayName);
  final String value;
  final String displayName;

  static DevisStatus fromString(String value) {
    return DevisStatus.values.firstWhere(
      (status) => status.value == value,
      orElse: () => throw ArgumentError('Invalid devis status: $value'),
    );
  }
}

enum DevisLineType {
  material('MATERIAL', 'Matériel'),
  labor('LABOR', 'Main d\'œuvre');

  const DevisLineType(this.value, this.displayName);
  final String value;
  final String displayName;

  static DevisLineType fromString(String value) {
    return DevisLineType.values.firstWhere(
      (type) => type.value == value,
      orElse: () => throw ArgumentError('Invalid devis line type: $value'),
    );
  }
}

class DevisLine {
  final String description;
  final int quantity;
  final double unitPrice;
  final DevisLineType type;

  const DevisLine({
    required this.description,
    required this.quantity,
    required this.unitPrice,
    required this.type,
  });

  double get total => quantity * unitPrice;

  Map<String, dynamic> toJson() {
    return {
      'description': description,
      'quantity': quantity,
      'unit_price': unitPrice,
      'type': type.value,
    };
  }

  factory DevisLine.fromJson(Map<String, dynamic> json) {
    return DevisLine(
      description: json['description'] as String,
      quantity: json['quantity'] as int,
      unitPrice: (json['unit_price'] as num).toDouble(),
      type: DevisLineType.fromString(json['type'] as String),
    );
  }
}

class Devis {
  final String id;
  final String missionId;
  final String artisanId;
  final double totalAmount;
  final double materialsAmount;
  final double laborAmount;
  final List<DevisLine> lineItems;
  final DevisStatus status;
  final DateTime createdAt;
  final DateTime? expiresAt;
  final DateTime? updatedAt;

  const Devis({
    required this.id,
    required this.missionId,
    required this.artisanId,
    required this.totalAmount,
    required this.materialsAmount,
    required this.laborAmount,
    required this.lineItems,
    required this.status,
    required this.createdAt,
    this.expiresAt,
    this.updatedAt,
  });

  bool get isExpired {
    if (expiresAt == null) return false;
    return DateTime.now().isAfter(expiresAt!);
  }

  Devis copyWith({
    String? id,
    String? missionId,
    String? artisanId,
    double? totalAmount,
    double? materialsAmount,
    double? laborAmount,
    List<DevisLine>? lineItems,
    DevisStatus? status,
    DateTime? createdAt,
    DateTime? expiresAt,
    DateTime? updatedAt,
  }) {
    return Devis(
      id: id ?? this.id,
      missionId: missionId ?? this.missionId,
      artisanId: artisanId ?? this.artisanId,
      totalAmount: totalAmount ?? this.totalAmount,
      materialsAmount: materialsAmount ?? this.materialsAmount,
      laborAmount: laborAmount ?? this.laborAmount,
      lineItems: lineItems ?? this.lineItems,
      status: status ?? this.status,
      createdAt: createdAt ?? this.createdAt,
      expiresAt: expiresAt ?? this.expiresAt,
      updatedAt: updatedAt ?? this.updatedAt,
    );
  }

  @override
  bool operator ==(Object other) {
    if (identical(this, other)) return true;
    return other is Devis && other.id == id;
  }

  @override
  int get hashCode => id.hashCode;
}
