import 'package:prosartisan_mobile/core/domain/value_objects/gps_coordinates.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/mission.dart';

class Artisan {
  final String id;
  final String email;
  final String? phoneNumber;
  final TradeCategory category;
  final GPSCoordinates location;
  final bool isKYCVerified;
  final double nzassaScore;
  final double averageRating;
  final int completedProjects;
  final String? profileImageUrl;
  final String? businessName;
  final DateTime createdAt;

  const Artisan({
    required this.id,
    required this.email,
    this.phoneNumber,
    required this.category,
    required this.location,
    required this.isKYCVerified,
    required this.nzassaScore,
    required this.averageRating,
    required this.completedProjects,
    this.profileImageUrl,
    this.businessName,
    required this.createdAt,
  });

  bool get canAcceptMissions => isKYCVerified;

  /// Calculate distance from this artisan to a given location
  double distanceToLocation(GPSCoordinates targetLocation) {
    return location.distanceTo(targetLocation);
  }

  /// Check if artisan is within 1km (golden marker range)
  bool isWithinGoldenRange(GPSCoordinates targetLocation) {
    return distanceToLocation(targetLocation) <= 1000; // 1km in meters
  }

  Artisan copyWith({
    String? id,
    String? email,
    String? phoneNumber,
    TradeCategory? category,
    GPSCoordinates? location,
    bool? isKYCVerified,
    double? nzassaScore,
    double? averageRating,
    int? completedProjects,
    String? profileImageUrl,
    String? businessName,
    DateTime? createdAt,
  }) {
    return Artisan(
      id: id ?? this.id,
      email: email ?? this.email,
      phoneNumber: phoneNumber ?? this.phoneNumber,
      category: category ?? this.category,
      location: location ?? this.location,
      isKYCVerified: isKYCVerified ?? this.isKYCVerified,
      nzassaScore: nzassaScore ?? this.nzassaScore,
      averageRating: averageRating ?? this.averageRating,
      completedProjects: completedProjects ?? this.completedProjects,
      profileImageUrl: profileImageUrl ?? this.profileImageUrl,
      businessName: businessName ?? this.businessName,
      createdAt: createdAt ?? this.createdAt,
    );
  }

  @override
  bool operator ==(Object other) {
    if (identical(this, other)) return true;
    return other is Artisan && other.id == id;
  }

  @override
  int get hashCode => id.hashCode;
}
