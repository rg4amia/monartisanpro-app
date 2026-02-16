// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'escrow_model.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

EscrowWallet _$EscrowWalletFromJson(Map<String, dynamic> json) => EscrowWallet(
      id: (json['id'] as num).toInt(),
      projectId: (json['project_id'] as num).toInt(),
      totalAmount: (json['total_amount'] as num).toDouble(),
      materialWallet: (json['material_wallet'] as num).toDouble(),
      laborWallet: (json['labor_wallet'] as num).toDouble(),
      materialSpent: (json['material_spent'] as num).toDouble(),
      laborReleased: (json['labor_released'] as num).toDouble(),
      status: json['status'] as String,
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
    );

Map<String, dynamic> _$EscrowWalletToJson(EscrowWallet instance) =>
    <String, dynamic>{
      'id': instance.id,
      'project_id': instance.projectId,
      'total_amount': instance.totalAmount,
      'material_wallet': instance.materialWallet,
      'labor_wallet': instance.laborWallet,
      'material_spent': instance.materialSpent,
      'labor_released': instance.laborReleased,
      'status': instance.status,
      'created_at': instance.createdAt.toIso8601String(),
      'updated_at': instance.updatedAt.toIso8601String(),
    };

MaterialToken _$MaterialTokenFromJson(Map<String, dynamic> json) =>
    MaterialToken(
      id: (json['id'] as num).toInt(),
      code: json['code'] as String,
      projectId: (json['project_id'] as num).toInt(),
      escrowWalletId: (json['escrow_wallet_id'] as num).toInt(),
      vendorId: (json['vendor_id'] as num?)?.toInt(),
      totalValue: (json['total_value'] as num).toDouble(),
      remainingValue: (json['remaining_value'] as num).toDouble(),
      qrCodePath: json['qr_code_path'] as String?,
      expiresAt: DateTime.parse(json['expires_at'] as String),
      status: json['status'] as String,
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
    );

Map<String, dynamic> _$MaterialTokenToJson(MaterialToken instance) =>
    <String, dynamic>{
      'id': instance.id,
      'code': instance.code,
      'project_id': instance.projectId,
      'escrow_wallet_id': instance.escrowWalletId,
      'vendor_id': instance.vendorId,
      'total_value': instance.totalValue,
      'remaining_value': instance.remainingValue,
      'qr_code_path': instance.qrCodePath,
      'expires_at': instance.expiresAt.toIso8601String(),
      'status': instance.status,
      'created_at': instance.createdAt.toIso8601String(),
      'updated_at': instance.updatedAt.toIso8601String(),
    };

TokenRedemption _$TokenRedemptionFromJson(Map<String, dynamic> json) =>
    TokenRedemption(
      id: (json['id'] as num).toInt(),
      tokenId: (json['token_id'] as num).toInt(),
      vendorId: (json['vendor_id'] as num).toInt(),
      amount: (json['amount'] as num).toDouble(),
      vendorLocation: json['vendor_location'] == null
          ? null
          : Location.fromJson(json['vendor_location'] as Map<String, dynamic>),
      artisanLocation: json['artisan_location'] == null
          ? null
          : Location.fromJson(json['artisan_location'] as Map<String, dynamic>),
      distanceMeters: (json['distance_meters'] as num?)?.toDouble(),
      validationMethod: json['validation_method'] as String,
      receiptPhoto: json['receipt_photo'] as String?,
      redeemedAt: DateTime.parse(json['redeemed_at'] as String),
      createdAt: json['created_at'] == null
          ? null
          : DateTime.parse(json['created_at'] as String),
      updatedAt: json['updated_at'] == null
          ? null
          : DateTime.parse(json['updated_at'] as String),
    );

Map<String, dynamic> _$TokenRedemptionToJson(TokenRedemption instance) =>
    <String, dynamic>{
      'id': instance.id,
      'token_id': instance.tokenId,
      'vendor_id': instance.vendorId,
      'amount': instance.amount,
      'vendor_location': instance.vendorLocation,
      'artisan_location': instance.artisanLocation,
      'distance_meters': instance.distanceMeters,
      'validation_method': instance.validationMethod,
      'receipt_photo': instance.receiptPhoto,
      'redeemed_at': instance.redeemedAt.toIso8601String(),
      'created_at': instance.createdAt?.toIso8601String(),
      'updated_at': instance.updatedAt?.toIso8601String(),
    };

Transaction _$TransactionFromJson(Map<String, dynamic> json) => Transaction(
      id: (json['id'] as num).toInt(),
      projectId: (json['project_id'] as num).toInt(),
      escrowWalletId: (json['escrow_wallet_id'] as num?)?.toInt(),
      transactionId: json['transaction_id'] as String,
      type: json['type'] as String,
      amount: (json['amount'] as num).toDouble(),
      description: json['description'] as String,
      status: json['status'] as String,
      paymentMethod: json['payment_method'] as String?,
      providerReference: json['provider_reference'] as String?,
      createdAt: DateTime.parse(json['created_at'] as String),
      updatedAt: DateTime.parse(json['updated_at'] as String),
    );

Map<String, dynamic> _$TransactionToJson(Transaction instance) =>
    <String, dynamic>{
      'id': instance.id,
      'project_id': instance.projectId,
      'escrow_wallet_id': instance.escrowWalletId,
      'transaction_id': instance.transactionId,
      'type': instance.type,
      'amount': instance.amount,
      'description': instance.description,
      'status': instance.status,
      'payment_method': instance.paymentMethod,
      'provider_reference': instance.providerReference,
      'created_at': instance.createdAt.toIso8601String(),
      'updated_at': instance.updatedAt.toIso8601String(),
    };

RedeemTokenRequest _$RedeemTokenRequestFromJson(Map<String, dynamic> json) =>
    RedeemTokenRequest(
      tokenCode: json['token_code'] as String,
      amount: (json['amount'] as num).toDouble(),
      vendorLatitude: (json['vendor_latitude'] as num).toDouble(),
      vendorLongitude: (json['vendor_longitude'] as num).toDouble(),
      artisanLatitude: (json['artisan_latitude'] as num).toDouble(),
      artisanLongitude: (json['artisan_longitude'] as num).toDouble(),
      receiptPhoto: json['receipt_photo'] as String?,
    );

Map<String, dynamic> _$RedeemTokenRequestToJson(RedeemTokenRequest instance) =>
    <String, dynamic>{
      'token_code': instance.tokenCode,
      'amount': instance.amount,
      'vendor_latitude': instance.vendorLatitude,
      'vendor_longitude': instance.vendorLongitude,
      'artisan_latitude': instance.artisanLatitude,
      'artisan_longitude': instance.artisanLongitude,
      'receipt_photo': instance.receiptPhoto,
    };
