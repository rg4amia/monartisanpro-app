// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'project_model.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Project _$ProjectFromJson(Map<String, dynamic> json) => Project(
      id: (json['id'] as num).toInt(),
      clientId: (json['client_id'] as num).toInt(),
      artisanId: (json['artisan_id'] as num?)?.toInt(),
      title: json['title'] as String,
      description: json['description'] as String,
      location: Location.fromJson(json['location'] as Map<String, dynamic>),
      address: json['address'] as String,
      expectedCompletionDate: json['expected_completion_date'] == null
          ? null
          : DateTime.parse(json['expected_completion_date'] as String),
      status: json['status'] as String,
      client: json['client'] == null
          ? null
          : User.fromJson(json['client'] as Map<String, dynamic>),
      artisan: json['artisan'] == null
          ? null
          : User.fromJson(json['artisan'] as Map<String, dynamic>),
      quotes: (json['quotes'] as List<dynamic>?)
          ?.map((e) => Quote.fromJson(e as Map<String, dynamic>))
          .toList(),
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
    );

Map<String, dynamic> _$ProjectToJson(Project instance) => <String, dynamic>{
      'id': instance.id,
      'client_id': instance.clientId,
      'artisan_id': instance.artisanId,
      'title': instance.title,
      'description': instance.description,
      'location': instance.location,
      'address': instance.address,
      'expected_completion_date':
          instance.expectedCompletionDate?.toIso8601String(),
      'status': instance.status,
      'client': instance.client,
      'artisan': instance.artisan,
      'quotes': instance.quotes,
      'created_at': instance.createdAt.toIso8601String(),
      'updated_at': instance.updatedAt.toIso8601String(),
    };

Quote _$QuoteFromJson(Map<String, dynamic> json) => Quote(
      id: (json['id'] as num).toInt(),
      projectId: (json['project_id'] as num).toInt(),
      artisanId: (json['artisan_id'] as num).toInt(),
      totalAmount: (json['total_amount'] as num).toDouble(),
      materialAmount: (json['material_amount'] as num).toDouble(),
      laborAmount: (json['labor_amount'] as num).toDouble(),
      materialPercentage: (json['material_percentage'] as num).toDouble(),
      laborPercentage: (json['labor_percentage'] as num).toDouble(),
      validUntil: DateTime.parse(json['valid_until'] as String),
      status: json['status'] as String,
      notes: json['notes'] as String?,
      artisan: json['artisan'] == null
          ? null
          : User.fromJson(json['artisan'] as Map<String, dynamic>),
      items: (json['items'] as List<dynamic>?)
          ?.map((e) => QuoteItem.fromJson(e as Map<String, dynamic>))
          .toList(),
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
    );

Map<String, dynamic> _$QuoteToJson(Quote instance) => <String, dynamic>{
      'id': instance.id,
      'project_id': instance.projectId,
      'artisan_id': instance.artisanId,
      'total_amount': instance.totalAmount,
      'material_amount': instance.materialAmount,
      'labor_amount': instance.laborAmount,
      'material_percentage': instance.materialPercentage,
      'labor_percentage': instance.laborPercentage,
      'valid_until': instance.validUntil.toIso8601String(),
      'status': instance.status,
      'notes': instance.notes,
      'artisan': instance.artisan,
      'items': instance.items,
      'created_at': instance.createdAt.toIso8601String(),
      'updated_at': instance.updatedAt.toIso8601String(),
    };

QuoteItem _$QuoteItemFromJson(Map<String, dynamic> json) => QuoteItem(
      id: (json['id'] as num).toInt(),
      quoteId: (json['quote_id'] as num).toInt(),
      itemType: json['item_type'] as String,
      description: json['description'] as String,
      quantity: (json['quantity'] as num).toDouble(),
      unit: json['unit'] as String?,
      unitPrice: (json['unit_price'] as num).toDouble(),
      total: (json['total'] as num).toDouble(),
      createdAt: json['created_at'] == null
          ? null
          : DateTime.parse(json['created_at'] as String),
      updatedAt: json['updated_at'] == null
          ? null
          : DateTime.parse(json['updated_at'] as String),
    );

Map<String, dynamic> _$QuoteItemToJson(QuoteItem instance) => <String, dynamic>{
      'id': instance.id,
      'quote_id': instance.quoteId,
      'item_type': instance.itemType,
      'description': instance.description,
      'quantity': instance.quantity,
      'unit': instance.unit,
      'unit_price': instance.unitPrice,
      'total': instance.total,
      'created_at': instance.createdAt?.toIso8601String(),
      'updated_at': instance.updatedAt?.toIso8601String(),
    };

CreateProjectRequest _$CreateProjectRequestFromJson(
        Map<String, dynamic> json) =>
    CreateProjectRequest(
      title: json['title'] as String,
      description: json['description'] as String,
      latitude: (json['latitude'] as num).toDouble(),
      longitude: (json['longitude'] as num).toDouble(),
      address: json['address'] as String,
      tradeId: (json['trade_id'] as num).toInt(),
      expectedCompletionDate: json['expected_completion_date'] as String?,
    );

Map<String, dynamic> _$CreateProjectRequestToJson(
        CreateProjectRequest instance) =>
    <String, dynamic>{
      'title': instance.title,
      'description': instance.description,
      'latitude': instance.latitude,
      'longitude': instance.longitude,
      'address': instance.address,
      'trade_id': instance.tradeId,
      'expected_completion_date': instance.expectedCompletionDate,
    };

CreateQuoteRequest _$CreateQuoteRequestFromJson(Map<String, dynamic> json) =>
    CreateQuoteRequest(
      projectId: (json['project_id'] as num).toInt(),
      materials: (json['materials'] as List<dynamic>)
          .map((e) => QuoteItemRequest.fromJson(e as Map<String, dynamic>))
          .toList(),
      labor: (json['labor'] as List<dynamic>)
          .map((e) => QuoteItemRequest.fromJson(e as Map<String, dynamic>))
          .toList(),
      validDays: (json['valid_days'] as num?)?.toInt() ?? 7,
      notes: json['notes'] as String?,
    );

Map<String, dynamic> _$CreateQuoteRequestToJson(CreateQuoteRequest instance) =>
    <String, dynamic>{
      'project_id': instance.projectId,
      'materials': instance.materials,
      'labor': instance.labor,
      'valid_days': instance.validDays,
      'notes': instance.notes,
    };

QuoteItemRequest _$QuoteItemRequestFromJson(Map<String, dynamic> json) =>
    QuoteItemRequest(
      description: json['description'] as String,
      quantity: (json['quantity'] as num).toDouble(),
      unit: json['unit'] as String?,
      unitPrice: (json['unit_price'] as num).toDouble(),
    );

Map<String, dynamic> _$QuoteItemRequestToJson(QuoteItemRequest instance) =>
    <String, dynamic>{
      'description': instance.description,
      'quantity': instance.quantity,
      'unit': instance.unit,
      'unit_price': instance.unitPrice,
    };
