/// User entity representing authenticated user
class User {
  final String id;
  final String email;
  final String userType;
  final String? phoneNumber;
  final String accountStatus;
  final DateTime createdAt;

  // Artisan-specific fields
  final String? tradeCategory;
  final String? tradeName; // Human-readable trade name
  final int? sectorId; // Sector ID for the trade
  final String? sectorName; // Human-readable sector name
  final bool? isKycVerified;

  // Fournisseur-specific fields
  final String? businessName;

  const User({
    required this.id,
    required this.email,
    required this.userType,
    this.phoneNumber,
    required this.accountStatus,
    required this.createdAt,
    this.tradeCategory,
    this.tradeName,
    this.sectorId,
    this.sectorName,
    this.isKycVerified,
    this.businessName,
  });

  /// Check if user is a client
  bool get isClient => userType == 'CLIENT';

  /// Check if user is an artisan
  bool get isArtisan => userType == 'ARTISAN';

  /// Check if user is a fournisseur
  bool get isFournisseur => userType == 'FOURNISSEUR';

  /// Check if account is active
  bool get isActive => accountStatus == 'ACTIVE';

  /// Check if account is suspended
  bool get isSuspended => accountStatus == 'SUSPENDED';

  /// Check if account is pending
  bool get isPending => accountStatus == 'PENDING';

  /// Copy with method for immutability
  User copyWith({
    String? id,
    String? email,
    String? userType,
    String? phoneNumber,
    String? accountStatus,
    DateTime? createdAt,
    String? tradeCategory,
    String? tradeName,
    int? sectorId,
    String? sectorName,
    bool? isKycVerified,
    String? businessName,
  }) {
    return User(
      id: id ?? this.id,
      email: email ?? this.email,
      userType: userType ?? this.userType,
      phoneNumber: phoneNumber ?? this.phoneNumber,
      accountStatus: accountStatus ?? this.accountStatus,
      createdAt: createdAt ?? this.createdAt,
      tradeCategory: tradeCategory ?? this.tradeCategory,
      tradeName: tradeName ?? this.tradeName,
      sectorId: sectorId ?? this.sectorId,
      sectorName: sectorName ?? this.sectorName,
      isKycVerified: isKycVerified ?? this.isKycVerified,
      businessName: businessName ?? this.businessName,
    );
  }
}
