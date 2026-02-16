import 'package:json_annotation/json_annotation.dart';
import 'trade_model.dart';

part 'escrow_model.g.dart';

@JsonSerializable()
class EscrowWallet {
  final int id;
  @JsonKey(name: 'project_id')
  final int projectId;
  @JsonKey(name: 'total_amount')
  final double totalAmount;
  @JsonKey(name: 'material_wallet')
  final double materialWallet;
  @JsonKey(name: 'labor_wallet')
  final double laborWallet;
  @JsonKey(name: 'material_spent')
  final double materialSpent;
  @JsonKey(name: 'labor_released')
  final double laborReleased;
  final String status; // active, completed, refunded
  @JsonKey(name: 'created_at')
  final DateTime createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime updatedAt;

  EscrowWallet({
    required this.id,
    required this.projectId,
    required this.totalAmount,
    required this.materialWallet,
    required this.laborWallet,
    required this.materialSpent,
    required this.laborReleased,
    required this.status,
    required this.createdAt,
    required this.updatedAt,
  });

  factory EscrowWallet.fromJson(Map<String, dynamic> json) =>
      _$EscrowWalletFromJson(json);
  Map<String, dynamic> toJson() => _$EscrowWalletToJson(this);

  // Helper getters
  double get materialRemaining => materialWallet - materialSpent;
  double get laborRemaining => laborWallet - laborReleased;
  double get totalSpent => materialSpent + laborReleased;
  double get totalRemaining => materialRemaining + laborRemaining;

  double get materialPercentageSpent =>
      materialWallet > 0 ? (materialSpent / materialWallet) * 100 : 0;
  double get laborPercentageReleased =>
      laborWallet > 0 ? (laborReleased / laborWallet) * 100 : 0;

  bool get isActive => status == 'active';
  bool get isCompleted => status == 'completed';
  bool get isRefunded => status == 'refunded';
}

@JsonSerializable()
class MaterialToken {
  final int id;
  final String code;
  @JsonKey(name: 'project_id')
  final int projectId;
  @JsonKey(name: 'escrow_wallet_id')
  final int escrowWalletId;
  @JsonKey(name: 'vendor_id')
  final int? vendorId;
  @JsonKey(name: 'total_value')
  final double totalValue;
  @JsonKey(name: 'remaining_value')
  final double remainingValue;
  @JsonKey(name: 'qr_code_path')
  final String? qrCodePath;
  @JsonKey(name: 'expires_at')
  final DateTime expiresAt;
  final String status; // active, partially_used, fully_used, expired
  @JsonKey(name: 'created_at')
  final DateTime createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime updatedAt;

  MaterialToken({
    required this.id,
    required this.code,
    required this.projectId,
    required this.escrowWalletId,
    this.vendorId,
    required this.totalValue,
    required this.remainingValue,
    this.qrCodePath,
    required this.expiresAt,
    required this.status,
    required this.createdAt,
    required this.updatedAt,
  });

  factory MaterialToken.fromJson(Map<String, dynamic> json) =>
      _$MaterialTokenFromJson(json);
  Map<String, dynamic> toJson() => _$MaterialTokenToJson(this);

  // Helper getters
  double get usedValue => totalValue - remainingValue;
  double get percentageUsed =>
      totalValue > 0 ? (usedValue / totalValue) * 100 : 0;

  bool get isActive => status == 'active';
  bool get isPartiallyUsed => status == 'partially_used';
  bool get isFullyUsed => status == 'fully_used';
  bool get isExpired => status == 'expired';

  bool get isValid => !isExpired && !isFullyUsed && expiresAt.isAfter(DateTime.now());

  Duration get timeUntilExpiry => expiresAt.difference(DateTime.now());

  String get formattedCode => code.replaceAllMapped(
        RegExp(r'.{4}'),
        (match) => '${match.group(0)} ',
      ).trim();

  String get formattedTotal => '${totalValue.toStringAsFixed(0)} FCFA';
  String get formattedRemaining => '${remainingValue.toStringAsFixed(0)} FCFA';
}

@JsonSerializable()
class TokenRedemption {
  final int id;
  @JsonKey(name: 'token_id')
  final int tokenId;
  @JsonKey(name: 'vendor_id')
  final int vendorId;
  final double amount;
  @JsonKey(name: 'vendor_location')
  final Location? vendorLocation;
  @JsonKey(name: 'artisan_location')
  final Location? artisanLocation;
  @JsonKey(name: 'distance_meters')
  final double? distanceMeters;
  @JsonKey(name: 'validation_method')
  final String validationMethod; // gps, otp_sms, admin_override
  @JsonKey(name: 'receipt_photo')
  final String? receiptPhoto;
  @JsonKey(name: 'redeemed_at')
  final DateTime redeemedAt;
  @JsonKey(name: 'created_at')
  final DateTime? createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime? updatedAt;

  TokenRedemption({
    required this.id,
    required this.tokenId,
    required this.vendorId,
    required this.amount,
    this.vendorLocation,
    this.artisanLocation,
    this.distanceMeters,
    required this.validationMethod,
    this.receiptPhoto,
    required this.redeemedAt,
    this.createdAt,
    this.updatedAt,
  });

  factory TokenRedemption.fromJson(Map<String, dynamic> json) =>
      _$TokenRedemptionFromJson(json);
  Map<String, dynamic> toJson() => _$TokenRedemptionToJson(this);

  // Helper getters
  bool get isGpsValidated => validationMethod == 'gps';
  bool get isOtpValidated => validationMethod == 'otp_sms';
  bool get isAdminOverride => validationMethod == 'admin_override';

  String get formattedAmount => '${amount.toStringAsFixed(0)} FCFA';
  String get distanceText {
    if (distanceMeters == null) return 'N/A';
    if (distanceMeters! < 1000) {
      return '${distanceMeters!.round()}m';
    }
    return '${(distanceMeters! / 1000).toStringAsFixed(1)}km';
  }
}

@JsonSerializable()
class Transaction {
  final int id;
  @JsonKey(name: 'project_id')
  final int projectId;
  @JsonKey(name: 'escrow_wallet_id')
  final int? escrowWalletId;
  @JsonKey(name: 'transaction_id')
  final String transactionId;
  final String type; // payment, token_redemption, labor_release, refund
  final double amount;
  final String description;
  final String status; // pending, completed, failed, cancelled
  @JsonKey(name: 'payment_method')
  final String? paymentMethod; // mobile_money, card, bank_transfer
  @JsonKey(name: 'provider_reference')
  final String? providerReference;
  @JsonKey(name: 'created_at')
  final DateTime createdAt;
  @JsonKey(name: 'updated_at')
  final DateTime updatedAt;

  Transaction({
    required this.id,
    required this.projectId,
    this.escrowWalletId,
    required this.transactionId,
    required this.type,
    required this.amount,
    required this.description,
    required this.status,
    this.paymentMethod,
    this.providerReference,
    required this.createdAt,
    required this.updatedAt,
  });

  factory Transaction.fromJson(Map<String, dynamic> json) =>
      _$TransactionFromJson(json);
  Map<String, dynamic> toJson() => _$TransactionToJson(this);

  // Helper getters
  bool get isPending => status == 'pending';
  bool get isCompleted => status == 'completed';
  bool get isFailed => status == 'failed';
  bool get isCancelled => status == 'cancelled';

  bool get isPayment => type == 'payment';
  bool get isTokenRedemption => type == 'token_redemption';
  bool get isLaborRelease => type == 'labor_release';
  bool get isRefund => type == 'refund';

  String get statusLabel {
    switch (status) {
      case 'pending':
        return 'En attente';
      case 'completed':
        return 'Complété';
      case 'failed':
        return 'Échoué';
      case 'cancelled':
        return 'Annulé';
      default:
        return status;
    }
  }

  String get formattedAmount => '${amount.toStringAsFixed(0)} FCFA';
}

@JsonSerializable()
class RedeemTokenRequest {
  @JsonKey(name: 'token_code')
  final String tokenCode;
  final double amount;
  @JsonKey(name: 'vendor_latitude')
  final double vendorLatitude;
  @JsonKey(name: 'vendor_longitude')
  final double vendorLongitude;
  @JsonKey(name: 'artisan_latitude')
  final double artisanLatitude;
  @JsonKey(name: 'artisan_longitude')
  final double artisanLongitude;
  @JsonKey(name: 'receipt_photo')
  final String? receiptPhoto;

  RedeemTokenRequest({
    required this.tokenCode,
    required this.amount,
    required this.vendorLatitude,
    required this.vendorLongitude,
    required this.artisanLatitude,
    required this.artisanLongitude,
    this.receiptPhoto,
  });

  Map<String, dynamic> toJson() => _$RedeemTokenRequestToJson(this);
}
