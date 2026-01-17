/// Jeton entity for material tokens
///
/// Requirements: 5.1, 5.2
class Jeton {
  final String id;
  final String code;
  final String sequestreId;
  final String artisanId;
  final int totalAmountCentimes;
  final int usedAmountCentimes;
  final List<String> authorizedSuppliers;
  final String status;
  final DateTime createdAt;
  final DateTime expiresAt;
  final JetonLocation? artisanLocation;

  const Jeton({
    required this.id,
    required this.code,
    required this.sequestreId,
    required this.artisanId,
    required this.totalAmountCentimes,
    required this.usedAmountCentimes,
    required this.authorizedSuppliers,
    required this.status,
    required this.createdAt,
    required this.expiresAt,
    this.artisanLocation,
  });

  /// Get remaining amount in centimes
  int get remainingAmountCentimes => totalAmountCentimes - usedAmountCentimes;

  /// Check if jeton is expired
  bool get isExpired => DateTime.now().isAfter(expiresAt);

  /// Get formatted total amount
  String get totalAmountFormatted => _formatAmount(totalAmountCentimes);

  /// Get formatted used amount
  String get usedAmountFormatted => _formatAmount(usedAmountCentimes);

  /// Get formatted remaining amount
  String get remainingAmountFormatted => _formatAmount(remainingAmountCentimes);

  /// Get QR code data
  String get qrCodeData => code;

  /// Format amount from centimes to display string
  String _formatAmount(int centimes) {
    final francs = centimes / 100;
    return '${francs.toStringAsFixed(0).replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'), (Match m) => '${m[1]} ')} FCFA';
  }

  /// Create from JSON
  factory Jeton.fromJson(Map<String, dynamic> json) {
    return Jeton(
      id: json['id'],
      code: json['code'],
      sequestreId: json['sequestre_id'],
      artisanId: json['artisan_id'],
      totalAmountCentimes: json['total_amount']['centimes'],
      usedAmountCentimes: json['used_amount']['centimes'],
      authorizedSuppliers: List<String>.from(
        json['authorized_suppliers'] ?? [],
      ),
      status: json['status'],
      createdAt: DateTime.parse(json['created_at']),
      expiresAt: DateTime.parse(json['expires_at']),
      artisanLocation: json['artisan_location'] != null
          ? JetonLocation.fromJson(json['artisan_location'])
          : null,
    );
  }

  /// Convert to JSON
  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'code': code,
      'sequestre_id': sequestreId,
      'artisan_id': artisanId,
      'total_amount': {'centimes': totalAmountCentimes},
      'used_amount': {'centimes': usedAmountCentimes},
      'authorized_suppliers': authorizedSuppliers,
      'status': status,
      'created_at': createdAt.toIso8601String(),
      'expires_at': expiresAt.toIso8601String(),
      'artisan_location': artisanLocation?.toJson(),
    };
  }
}

/// Location information for jeton
class JetonLocation {
  final double latitude;
  final double longitude;

  const JetonLocation({required this.latitude, required this.longitude});

  factory JetonLocation.fromJson(Map<String, dynamic> json) {
    return JetonLocation(
      latitude: json['latitude'].toDouble(),
      longitude: json['longitude'].toDouble(),
    );
  }

  Map<String, dynamic> toJson() {
    return {'latitude': latitude, 'longitude': longitude};
  }
}
