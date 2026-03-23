class SupplierProductModel {
  final int id;
  final int supplierId;
  final String name;
  final String? sku;
  final String? description;
  final int unitPrice;
  final int stockQuantity;
  final String? imageUrl;
  final bool isActive;
  final String? createdAt;
  final String? updatedAt;

  const SupplierProductModel({
    required this.id,
    required this.supplierId,
    required this.name,
    required this.unitPrice,
    required this.stockQuantity,
    required this.isActive,
    this.sku,
    this.description,
    this.imageUrl,
    this.createdAt,
    this.updatedAt,
  });

  factory SupplierProductModel.fromJson(Map<String, dynamic> json) {
    return SupplierProductModel(
      id: _parseInt(json['id']),
      supplierId: _parseInt(json['supplierId'] ?? json['supplier_id']),
      name: (json['name'] ?? '').toString(),
      sku: json['sku']?.toString(),
      description: json['description']?.toString(),
      unitPrice: _parseInt(json['unitPrice'] ?? json['unit_price']),
      stockQuantity: _parseInt(
        json['stockQuantity'] ?? json['stock_quantity'],
      ),
      imageUrl: json['imageUrl']?.toString(),
      isActive: (json['isActive'] ?? json['is_active']) as bool? ?? true,
      createdAt: json['createdAt']?.toString(),
      updatedAt: json['updatedAt']?.toString(),
    );
  }

  Map<String, dynamic> toRequestJson() => {
        'name': name,
        'sku': sku,
        'description': description,
        'unit_price': unitPrice,
        'stock_quantity': stockQuantity,
        'image_url': imageUrl,
        'is_active': isActive,
      };

  static int _parseInt(dynamic value) {
    if (value == null) return 0;
    if (value is int) return value;
    if (value is double) return value.toInt();
    return int.tryParse(value.toString()) ?? 0;
  }
}
