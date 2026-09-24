/// Article du stock personnel de l'artisan (matériaux/outils déjà en sa
/// possession) — distinct du catalogue d'un fournisseur.
class ArtisanStockModel {
  final int id;
  final int artisanId;
  final String description;
  final int quantity;
  final int unitCost;
  final String condition; // 'neuf' ou 'occasion'
  final String? createdAt;
  final String? updatedAt;

  const ArtisanStockModel({
    required this.id,
    required this.artisanId,
    required this.description,
    required this.quantity,
    required this.unitCost,
    required this.condition,
    this.createdAt,
    this.updatedAt,
  });

  factory ArtisanStockModel.fromJson(Map<String, dynamic> json) {
    return ArtisanStockModel(
      id: _parseInt(json['id']),
      artisanId: _parseInt(json['artisan_id'] ?? json['artisanId']),
      description: (json['description'] ?? '').toString(),
      quantity: _parseInt(json['quantity']),
      unitCost: _parseInt(json['unit_cost'] ?? json['unitCost']),
      condition: (json['condition'] ?? 'neuf').toString(),
      createdAt:
          json['created_at']?.toString() ?? json['createdAt']?.toString(),
      updatedAt:
          json['updated_at']?.toString() ?? json['updatedAt']?.toString(),
    );
  }

  Map<String, dynamic> toRequestJson() => {
        'description': description,
        'quantity': quantity,
        'unit_cost': unitCost,
        'condition': condition,
      };

  static int _parseInt(dynamic value) {
    if (value == null) return 0;
    if (value is int) return value;
    if (value is double) return value.toInt();
    return int.tryParse(value.toString()) ?? 0;
  }
}
