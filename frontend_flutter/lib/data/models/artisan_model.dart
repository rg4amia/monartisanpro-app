class ArtisanModel {
  final int id;
  final String phone;
  final String? name;
  final String? photo;
  final String? bio;
  final String? trade;
  final String? sector;
  final int experienceYears;
  final int scoreProsArtisan;
  final double rating;
  final int completedMissions;
  final String? distance;
  final double? distanceMetres;
  final bool isGoldenMarker;
  final bool nightInterventionAvailable;
  final String? kycStatus;
  final String? cnmciNumber;
  final String cnmciStatus;
  final Map<String, double>? location;
  final String? locationLabel;
  final String? commune;
  final bool isAvailable;
  final DateTime? joinedDate;
  final String? role;
  final String? price;

  const ArtisanModel({
    required this.id,
    required this.phone,
    required this.scoreProsArtisan,
    required this.isGoldenMarker,
    this.nightInterventionAvailable = false,
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
    this.locationLabel,
    this.commune,
    this.isAvailable = true,
    this.joinedDate,
    this.role,
    this.price,
    this.cnmciNumber,
    this.cnmciStatus = 'non_renseigne',
  });

  factory ArtisanModel.fromJson(Map<String, dynamic> json) {
    final parsedLocation = _parseLocation(json);
    final scoreProsArtisan = _parseInt(json['scoreProsArtisan'] ?? json['score_prosartisan'] ?? json['scoreNzassa'] ?? json['score_nzassa']);
    final distanceMetres = _parseDouble(
      json['distanceMetres'] ?? json['distance_metres'],
    );

    return ArtisanModel(
      id: _parseInt(json['id']),
      phone: (json['phone'] ?? json['telephone'] ?? json['contactMobile'] ?? '')
          .toString(),
      name: _parseName(json),
      photo: (json['photo'] ?? json['image']) as String?,
      bio: json['bio'] as String?,
      trade:
          (json['trade'] ?? json['category'] ?? json['artisanCategory'])
              as String?,
      sector: (json['sector'] ?? json['secteur']) as String?,
      experienceYears: _parseInt(
        json['experienceYears'] ?? json['experience_years'],
      ),
      scoreProsArtisan: scoreProsArtisan,
      rating: _parseDouble(json['rating']) ?? 0.0,
      completedMissions: _parseInt(
        json['completedMissions'] ?? json['completed_missions'],
      ),
      distance:
          (json['distance'] as String?) ??
          (distanceMetres != null ? _formatDistance(distanceMetres) : null),
      distanceMetres: distanceMetres,
      isGoldenMarker: (json['isGoldenMarker'] as bool?) ?? scoreProsArtisan >= 70,
      nightInterventionAvailable: _parseBool(
        json['nightInterventionAvailable'] ??
            json['intervention_nuit'] ??
            json['intervientLaNuit'],
      ),
      kycStatus: (json['kycStatus'] ?? json['kyc_status']) as String?,
      location: parsedLocation,
      locationLabel: _parseLocationLabel(json),
      commune: (json['commune'] ?? _parseLocationLabel(json)) as String?,
      isAvailable: (json['isAvailable'] ?? json['active']) as bool? ?? true,
      joinedDate: _parseDate(
        json['joinedDate'] ?? json['createdAt'] ?? json['created_at'],
      ),
      role: json['role'] as String?,
      price: json['price'] as String?,
      cnmciNumber: json['cnmciNumber'] as String?,
      cnmciStatus: (json['cnmciStatus'] ?? json['cnmci_status']) as String? ?? 'non_renseigne',
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
    'scoreProsArtisan': scoreProsArtisan,
    'rating': rating,
    'completedMissions': completedMissions,
    'distance': distance,
    'distanceMetres': distanceMetres,
    'isGoldenMarker': isGoldenMarker,
    'nightInterventionAvailable': nightInterventionAvailable,
    'kycStatus': kycStatus,
    'commune': commune,
    'location': location,
    'locationLabel': locationLabel,
    'isAvailable': isAvailable,
    'joinedDate': joinedDate?.toIso8601String(),
    'role': role,
    'price': price,
    'cnmciNumber': cnmciNumber,
    'cnmciStatus': cnmciStatus,
  };

  bool get isCnmciVerified => cnmciStatus == 'valide';

  static Map<String, double>? _parseLocation(Map<String, dynamic> json) {
    final value = json['location'];
    if (value is Map<String, dynamic>) {
      final lat = _parseDouble(
        value['lat'] ?? value['latitude'] ?? json['lat'] ?? json['latitude'],
      );
      final lng = _parseDouble(
        value['lng'] ?? value['longitude'] ?? json['lng'] ?? json['longitude'],
      );
      if (lat == null || lng == null) return null;
      return {'lat': lat, 'lng': lng};
    }

    final lat = _parseDouble(json['lat'] ?? json['latitude']);
    final lng = _parseDouble(json['lng'] ?? json['longitude']);
    if (lat == null || lng == null) return null;
    return {'lat': lat, 'lng': lng};
  }

  static String? _parseLocationLabel(Map<String, dynamic> json) {
    final location = json['location'];
    if (location is String && location.trim().isNotEmpty) {
      return location;
    }

    return (json['locationLabel'] ?? json['commune'] ?? json['adresse'])
        as String?;
  }

  static String? _parseName(Map<String, dynamic> json) {
    final name = json['name'] as String?;
    if (name != null && name.trim().isNotEmpty) {
      return name;
    }

    final nom = (json['nom'] ?? '').toString().trim();
    final prenoms = (json['prenoms'] ?? '').toString().trim();
    final combined = [nom, prenoms].where((part) => part.isNotEmpty).join(' ');
    return combined.isEmpty ? null : combined;
  }

  static int _parseInt(dynamic value) {
    if (value == null) return 0;
    if (value is int) return value;
    if (value is double) return value.toInt();
    return int.tryParse(value.toString()) ?? 0;
  }

  static double? _parseDouble(dynamic value) {
    if (value == null) return null;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    return double.tryParse(value.toString());
  }

  static DateTime? _parseDate(dynamic value) {
    if (value == null) return null;
    return DateTime.tryParse(value.toString());
  }

  static bool _parseBool(dynamic value) {
    if (value is bool) return value;
    if (value is int) return value == 1;
    if (value is String) {
      final normalized = value.trim().toLowerCase();
      return normalized == '1' || normalized == 'true' || normalized == 'oui';
    }
    return false;
  }

  static String _formatDistance(double metres) {
    if (metres < 1000) return '${metres.round()} m';
    return '${(metres / 1000).toStringAsFixed(1)} km';
  }
}
