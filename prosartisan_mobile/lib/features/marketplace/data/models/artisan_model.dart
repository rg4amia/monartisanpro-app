import 'package:prosartisan_mobile/core/domain/value_objects/gps_coordinates.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/artisan.dart';
import 'package:prosartisan_mobile/features/marketplace/domain/entities/mission.dart';

class ArtisanModel extends Artisan {
  const ArtisanModel({
    required super.id,
    required super.email,
    super.phoneNumber,
    required super.category,
    required super.location,
    required super.isKYCVerified,
    required super.nzassaScore,
    required super.averageRating,
    required super.completedProjects,
    super.profileImageUrl,
    super.businessName,
    required super.createdAt,
  });

  factory ArtisanModel.fromJson(Map<String, dynamic> json) {
    return ArtisanModel(
      id: json['id'] as String,
      email: json['email'] as String,
      phoneNumber: json['phone_number'] as String?,
      category: TradeCategory.fromString(json['trade_category'] as String),
      location: GPSCoordinates.fromJson(
        json['location'] as Map<String, dynamic>,
      ),
      isKYCVerified: json['is_kyc_verified'] as bool,
      nzassaScore: (json['nzassa_score'] as num).toDouble(),
      averageRating: (json['average_rating'] as num).toDouble(),
      completedProjects: json['completed_projects'] as int,
      profileImageUrl: json['profile_image_url'] as String?,
      businessName: json['business_name'] as String?,
      createdAt: DateTime.parse(json['created_at'] as String),
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'email': email,
      'phone_number': phoneNumber,
      'trade_category': category.value,
      'location': location.toJson(),
      'is_kyc_verified': isKYCVerified,
      'nzassa_score': nzassaScore,
      'average_rating': averageRating,
      'completed_projects': completedProjects,
      'profile_image_url': profileImageUrl,
      'business_name': businessName,
      'created_at': createdAt.toIso8601String(),
    };
  }

  factory ArtisanModel.fromEntity(Artisan artisan) {
    return ArtisanModel(
      id: artisan.id,
      email: artisan.email,
      phoneNumber: artisan.phoneNumber,
      category: artisan.category,
      location: artisan.location,
      isKYCVerified: artisan.isKYCVerified,
      nzassaScore: artisan.nzassaScore,
      averageRating: artisan.averageRating,
      completedProjects: artisan.completedProjects,
      profileImageUrl: artisan.profileImageUrl,
      businessName: artisan.businessName,
      createdAt: artisan.createdAt,
    );
  }

  Artisan toEntity() {
    return Artisan(
      id: id,
      email: email,
      phoneNumber: phoneNumber,
      category: category,
      location: location,
      isKYCVerified: isKYCVerified,
      nzassaScore: nzassaScore,
      averageRating: averageRating,
      completedProjects: completedProjects,
      profileImageUrl: profileImageUrl,
      businessName: businessName,
      createdAt: createdAt,
    );
  }
}
