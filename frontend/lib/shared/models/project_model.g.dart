// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'project_model.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Project _$ProjectFromJson(Map<String, dynamic> json) => Project(
      id: (json['id'] as num).toInt(),
      title: json['title'] as String,
      description: json['description'] as String,
      status: json['status'] as String,
      address: json['address'] as String,
      latitude: (json['latitude'] as num?)?.toDouble(),
      longitude: (json['longitude'] as num?)?.toDouble(),
      budgetMin: (json['budget_min'] as num?)?.toDouble(),
      budgetMax: (json['budget_max'] as num?)?.toDouble(),
      finalAmount: (json['final_amount'] as num?)?.toDouble(),
      expectedCompletionDate: json['expected_completion_date'] as String?,
      quoteCount: (json['quote_count'] as num).toInt(),
      createdAt: json['created_at'] as String,
      client: ProjectUser.fromJson(json['client'] as Map<String, dynamic>),
      artisan: json['artisan'] == null
          ? null
          : ProjectUser.fromJson(json['artisan'] as Map<String, dynamic>),
      trade: ProjectTrade.fromJson(json['trade'] as Map<String, dynamic>),
    );

Map<String, dynamic> _$ProjectToJson(Project instance) => <String, dynamic>{
      'id': instance.id,
      'title': instance.title,
      'description': instance.description,
      'status': instance.status,
      'address': instance.address,
      'latitude': instance.latitude,
      'longitude': instance.longitude,
      'budget_min': instance.budgetMin,
      'budget_max': instance.budgetMax,
      'final_amount': instance.finalAmount,
      'expected_completion_date': instance.expectedCompletionDate,
      'quote_count': instance.quoteCount,
      'created_at': instance.createdAt,
      'client': instance.client,
      'artisan': instance.artisan,
      'trade': instance.trade,
    };

ProjectUser _$ProjectUserFromJson(Map<String, dynamic> json) => ProjectUser(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
      phone: json['phone'] as String?,
      avatar: json['avatar'] as String?,
      tradeName: json['trade_name'] as String?,
    );

Map<String, dynamic> _$ProjectUserToJson(ProjectUser instance) =>
    <String, dynamic>{
      'id': instance.id,
      'name': instance.name,
      'phone': instance.phone,
      'avatar': instance.avatar,
      'trade_name': instance.tradeName,
    };

ProjectTrade _$ProjectTradeFromJson(Map<String, dynamic> json) => ProjectTrade(
      id: (json['id'] as num).toInt(),
      name: json['name'] as String,
    );

Map<String, dynamic> _$ProjectTradeToJson(ProjectTrade instance) =>
    <String, dynamic>{
      'id': instance.id,
      'name': instance.name,
    };
