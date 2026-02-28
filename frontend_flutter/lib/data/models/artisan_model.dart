class ArtisanModel {
  final int id;
  final String phone;
  final String? name;
  final String? photo;
  final String? bio;
  final String? trade;
  final String? sector;
  final int experienceYears;
  final int scoreNzassa;
  final double rating;
  final int completedMissions;
  final String? distance;
  final double? distanceMetres;
  final bool isGoldenMarker;
  final String? kycStatus;
  final Map<String, double>? location;

  const ArtisanModel({
    required this.id,
    required this.phone,
    required this.scoreNzassa,
    required this.isGoldenMarker,
    required this.experienceYears,
    required this.rating,
    required this.completedMissions,
    this.name,
    this.photo,
    this.bio,
    this.trade,
    this.sector,
    this.distance,
    this.distanceMetres,
    this.kycStatus,
    this.location,
  });

  factory ArtisanModel.fromJson(Map<String, dynamic> json) {
    final locationData = json['location'] as Map<String, dynamic>?;
    return ArtisanModel(
      id: json['id'] as int,
      phone: json['phone'] as String,
      name: json['name'] as String?,
      photo: json['photo'] as String?,
      bio: json['bio'] as String?,
      trade: json['trade'] as String?,
      sector: json['sector'] as String?,
      experienceYears: json['experienceYears'] as int? ?? 0,
      scoreNzassa: json['scoreNzassa'] as int? ?? 0,
      rating: (json['rating'] as num?)?.toDouble() ?? 0.0,
      completedMissions: json['completedMissions'] as int? ?? 0,
      distance: json['distance'] as String?,
      distanceMetres: (json['distanceMetres'] as num?)?.toDouble(),
      isGoldenMarker: json['isGoldenMarker'] as bool? ?? false,
      kycStatus: json['kycStatus'] as String?,
      location: locationData != null
          ? {
              'lat': (locationData['lat'] as num).toDouble(),
              'lng': (locationData['lng'] as num).toDouble(),
            }
          : null,
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'phone': phone,
        'name': name,
        'photo': photo,
        'bio': bio,
        'trade': trade,
        'sector': sector,
        'experienceYears': experienceYears,
        'scoreNzassa': scoreNzassa,
        'rating': rating,
        'completedMissions': completedMissions,
        'distance': distance,
        'distanceMetres': distanceMetres,
        'isGoldenMarker': isGoldenMarker,
        'kycStatus': kycStatus,
        'location': location,
      };
}
