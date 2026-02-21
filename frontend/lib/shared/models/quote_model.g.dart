// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'quote_model.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Quote _$QuoteFromJson(Map<String, dynamic> json) => Quote(
      id: (json['id'] as num).toInt(),
      projectId: (json['project_id'] as num).toInt(),
      artisanId: (json['artisan_id'] as num).toInt(),
      totalAmount: (json['total_amount'] as num).toDouble(),
      materialAmount: (json['material_amount'] as num).toDouble(),
      laborAmount: (json['labor_amount'] as num).toDouble(),
      materialPercentage: (json['material_percentage'] as num).toDouble(),
      laborPercentage: (json['labor_percentage'] as num).toDouble(),
      validDays: (json['valid_days'] as num).toInt(),
      validUntil: json['valid_until'] as String?,
      status: json['status'] as String,
      notes: json['notes'] as String?,
      rejectionReason: json['rejection_reason'] as String?,
      sentAt: json['sent_at'] as String?,
      acceptedAt: json['accepted_at'] as String?,
      rejectedAt: json['rejected_at'] as String?,
      createdAt: json['created_at'] as String,
      items: (json['items'] as List<dynamic>?)
          ?.map((e) => QuoteItem.fromJson(e as Map<String, dynamic>))
          .toList(),
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
      'valid_days': instance.validDays,
      'valid_until': instance.validUntil,
      'status': instance.status,
      'notes': instance.notes,
      'rejection_reason': instance.rejectionReason,
      'sent_at': instance.sentAt,
      'accepted_at': instance.acceptedAt,
      'rejected_at': instance.rejectedAt,
      'created_at': instance.createdAt,
      'items': instance.items,
    };

QuoteItem _$QuoteItemFromJson(Map<String, dynamic> json) => QuoteItem(
      id: (json['id'] as num).toInt(),
      quoteId: (json['quote_id'] as num).toInt(),
      type: json['type'] as String,
      description: json['description'] as String,
      quantity: (json['quantity'] as num).toDouble(),
      unit: json['unit'] as String,
      unitPrice: (json['unit_price'] as num).toDouble(),
      total: (json['total'] as num).toDouble(),
    );

Map<String, dynamic> _$QuoteItemToJson(QuoteItem instance) => <String, dynamic>{
      'id': instance.id,
      'quote_id': instance.quoteId,
      'type': instance.type,
      'description': instance.description,
      'quantity': instance.quantity,
      'unit': instance.unit,
      'unit_price': instance.unitPrice,
      'total': instance.total,
    };
