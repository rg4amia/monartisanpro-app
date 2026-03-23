class JcodeItemModel {
  final int? id;
  final int? supplierProductId;
  final String source;
  final String name;
  final String? sku;
  final int quantity;
  final int unitPrice;
  final int subtotal;
  final String? status;

  const JcodeItemModel({
    required this.source,
    required this.name,
    required this.quantity,
    required this.unitPrice,
    required this.subtotal,
    this.id,
    this.supplierProductId,
    this.sku,
    this.status,
  });

  bool get isCatalog => source == 'catalog';
  bool get isCustom => source == 'custom';

  factory JcodeItemModel.fromJson(Map<String, dynamic> json) {
    final quantity = _parseInt(json['quantity']);
    final unitPrice = _parseInt(json['unitPrice'] ?? json['unit_price']);

    return JcodeItemModel(
      id: _parseNullableInt(json['id']),
      supplierProductId: _parseNullableInt(
        json['supplierProductId'] ?? json['supplier_product_id'],
      ),
      source: (json['source'] ?? 'custom').toString(),
      name: (json['name'] ?? json['item_name'] ?? '').toString(),
      sku: (json['sku'] ?? json['item_sku'])?.toString(),
      quantity: quantity,
      unitPrice: unitPrice,
      subtotal: _parseInt(json['subtotal']) == 0
          ? quantity * unitPrice
          : _parseInt(json['subtotal']),
      status: json['status']?.toString(),
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'supplierProductId': supplierProductId,
        'source': source,
        'name': name,
        'sku': sku,
        'quantity': quantity,
        'unitPrice': unitPrice,
        'subtotal': subtotal,
        'status': status,
      };

  Map<String, dynamic> toRequestJson() => {
        'supplier_product_id': supplierProductId,
        'name': name,
        'sku': sku,
        'quantity': quantity,
        if (isCustom) 'unit_price': unitPrice,
      };

  JcodeItemModel copyWith({
    int? id,
    int? supplierProductId,
    String? source,
    String? name,
    String? sku,
    int? quantity,
    int? unitPrice,
    int? subtotal,
    String? status,
  }) {
    final nextQuantity = quantity ?? this.quantity;
    final nextUnitPrice = unitPrice ?? this.unitPrice;

    return JcodeItemModel(
      id: id ?? this.id,
      supplierProductId: supplierProductId ?? this.supplierProductId,
      source: source ?? this.source,
      name: name ?? this.name,
      sku: sku ?? this.sku,
      quantity: nextQuantity,
      unitPrice: nextUnitPrice,
      subtotal: subtotal ?? (nextQuantity * nextUnitPrice),
      status: status ?? this.status,
    );
  }

  static int _parseInt(dynamic value) {
    if (value == null) return 0;
    if (value is int) return value;
    if (value is double) return value.toInt();
    return int.tryParse(value.toString()) ?? 0;
  }

  static int? _parseNullableInt(dynamic value) {
    if (value == null) return null;
    if (value is int) return value;
    if (value is double) return value.toInt();
    return int.tryParse(value.toString());
  }
}
