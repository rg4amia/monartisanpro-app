import '../../domain/entities/user.dart';

/// User model for JSON serialization
class UserModel extends User {
  const UserModel({
    required super.id,
    required super.email,
    required super.userType,
    super.phoneNumber,
    required super.accountStatus,
    required super.createdAt,
    super.tradeCategory,
    super.tradeName,
    super.sectorId,
    super.sectorName,
    super.isKycVerified,
    super.businessName,
  });

  /// Create UserModel from JSON
  factory UserModel.fromJson(Map<String, dynamic> json) {
    return UserModel(
      id: json['id'] as String,
      email: json['email'] as String,
      userType: json['user_type'] as String,
      phoneNumber: json['phone_number'] as String?,
      accountStatus: json['account_status'] as String,
      createdAt: DateTime.parse(json['created_at'] as String),
      tradeCategory: json['trade_category'] as String?,
      tradeName: json['trade_name'] as String?,
      sectorId: json['sector_id'] as int?,
      sectorName: json['sector_name'] as String?,
      isKycVerified: json['is_kyc_verified'] as bool?,
      businessName: json['business_name'] as String?,
    );
  }

  /// Convert UserModel to JSON
  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'email': email,
      'user_type': userType,
      'phone_number': phoneNumber,
      'account_status': accountStatus,
      'created_at': createdAt.toIso8601String(),
      'trade_category': tradeCategory,
      'trade_name': tradeName,
      'sector_id': sectorId,
      'sector_name': sectorName,
      'is_kyc_verified': isKycVerified,
      'business_name': businessName,
    };
  }

  /// Convert to User entity
  User toEntity() {
    return User(
      id: id,
      email: email,
      userType: userType,
      phoneNumber: phoneNumber,
      accountStatus: accountStatus,
      createdAt: createdAt,
      tradeCategory: tradeCategory,
      tradeName: tradeName,
      sectorId: sectorId,
      sectorName: sectorName,
      isKycVerified: isKycVerified,
      businessName: businessName,
    );
  }
}
