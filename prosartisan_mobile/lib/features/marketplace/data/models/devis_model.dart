import 'package:prosartisan_mobile/features/marketplace/domain/entities/devis.dart';

class DevisModel extends Devis {
  const DevisModel({
    required super.id,
    required super.missionId,
    required super.artisanId,
    required super.totalAmount,
    required super.materialsAmount,
    required super.laborAmount,
    required super.lineItems,
    required super.status,
    required super.createdAt,
    super.expiresAt,
    super.updatedAt,
  });

  factory DevisModel.fromJson(Map<String, dynamic> json) {
    return DevisModel(
      id: json['id'] as String,
      missionId: json['mission_id'] as String,
      artisanId: json['artisan_id'] as String,
      totalAmount: (json['total_amount'] as num).toDouble(),
      materialsAmount: (json['materials_amount'] as num).toDouble(),
      laborAmount: (json['labor_amount'] as num).toDouble(),
      lineItems: (json['line_items'] as List<dynamic>)
          .map((item) => DevisLine.fromJson(item as Map<String, dynamic>))
          .toList(),
      status: DevisStatus.fromString(json['status'] as String),
      createdAt: DateTime.parse(json['created_at'] as String),
      expiresAt: json['expires_at'] != null
          ? DateTime.parse(json['expires_at'] as String)
          : null,
      updatedAt: json['updated_at'] != null
          ? DateTime.parse(json['updated_at'] as String)
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'mission_id': missionId,
      'artisan_id': artisanId,
      'total_amount': totalAmount,
      'materials_amount': materialsAmount,
      'labor_amount': laborAmount,
      'line_items': lineItems.map((item) => item.toJson()).toList(),
      'status': status.value,
      'created_at': createdAt.toIso8601String(),
      'expires_at': expiresAt?.toIso8601String(),
      'updated_at': updatedAt?.toIso8601String(),
    };
  }

  factory DevisModel.fromEntity(Devis devis) {
    return DevisModel(
      id: devis.id,
      missionId: devis.missionId,
      artisanId: devis.artisanId,
      totalAmount: devis.totalAmount,
      materialsAmount: devis.materialsAmount,
      laborAmount: devis.laborAmount,
      lineItems: devis.lineItems,
      status: devis.status,
      createdAt: devis.createdAt,
      expiresAt: devis.expiresAt,
      updatedAt: devis.updatedAt,
    );
  }

  Devis toEntity() {
    return Devis(
      id: id,
      missionId: missionId,
      artisanId: artisanId,
      totalAmount: totalAmount,
      materialsAmount: materialsAmount,
      laborAmount: laborAmount,
      lineItems: lineItems,
      status: status,
      createdAt: createdAt,
      expiresAt: expiresAt,
      updatedAt: updatedAt,
    );
  }
}
