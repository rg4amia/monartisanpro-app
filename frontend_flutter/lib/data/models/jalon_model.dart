class JalonModel {
  final int id;
  final int missionId;
  final int ordre;
  final String description;
  final int montant;
  final String statut;
  final String? otpCode;
  final String? otpExpiresAt;
  final List<Map<String, dynamic>>? photosJson;
  final String? valideAt;
  final String? payeAt;

  const JalonModel({
    required this.id,
    required this.missionId,
    required this.ordre,
    required this.description,
    required this.montant,
    required this.statut,
    this.otpCode,
    this.otpExpiresAt,
    this.photosJson,
    this.valideAt,
    this.payeAt,
  });

  /// États du jalon
  bool get isPending => statut == 'en_attente';
  bool get isSubmitted => statut == 'soumis';
  bool get isValidated => statut == 'valide';
  bool get isPaid => statut == 'paye';

  /// Statut formaté en français
  String get statutLabel {
    switch (statut) {
      case 'en_attente':
        return 'En attente';
      case 'soumis':
        return 'Soumis';
      case 'valide':
        return 'Validé';
      case 'paye':
        return 'Payé';
      default:
        return statut;
    }
  }

  /// Vérifie si l'OTP est encore valide
  bool get isOtpValid {
    if (otpExpiresAt == null) return false;
    try {
      final expiryDate = DateTime.parse(otpExpiresAt!);
      return DateTime.now().isBefore(expiryDate);
    } catch (_) {
      return false;
    }
  }

  factory JalonModel.fromJson(Map<String, dynamic> json) {
    // Support pour snake_case (Laravel) et camelCase (frontend)
    return JalonModel(
      id: json['id'] as int,
      missionId: (json['mission_id'] ?? json['missionId']) as int,
      ordre: json['ordre'] as int,
      description: json['description'] as String,
      montant: _parseAmount(json['montant']),
      statut: json['statut'] as String,
      otpCode: (json['otp_code'] ?? json['otpCode']) as String?,
      otpExpiresAt: (json['otp_expires_at'] ?? json['otpExpiresAt']) as String?,
      photosJson: _parsePhotos(json['photos_json'] ?? json['photosJson']),
      valideAt: (json['valide_at'] ?? json['valideAt']) as String?,
      payeAt: (json['paye_at'] ?? json['payeAt']) as String?,
    );
  }

  /// Parse un montant en FCFA (entier)
  static int _parseAmount(dynamic value) {
    if (value == null) return 0;
    if (value is int) return value;
    if (value is double) return value.toInt();
    if (value is String) return int.tryParse(value) ?? 0;
    return 0;
  }

  /// Parse le JSON des photos
  static List<Map<String, dynamic>>? _parsePhotos(dynamic value) {
    if (value == null) return null;
    if (value is List) {
      return value.map((e) => e as Map<String, dynamic>).toList();
    }
    return null;
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'mission_id': missionId,
        'ordre': ordre,
        'description': description,
        'montant': montant,
        'statut': statut,
        'otp_code': otpCode,
        'otp_expires_at': otpExpiresAt,
        'photos_json': photosJson,
        'valide_at': valideAt,
        'paye_at': payeAt,
      };

  /// Copie l'objet avec des modifications
  JalonModel copyWith({
    int? id,
    int? missionId,
    int? ordre,
    String? description,
    int? montant,
    String? statut,
    String? otpCode,
    String? otpExpiresAt,
    List<Map<String, dynamic>>? photosJson,
    String? valideAt,
    String? payeAt,
  }) {
    return JalonModel(
      id: id ?? this.id,
      missionId: missionId ?? this.missionId,
      ordre: ordre ?? this.ordre,
      description: description ?? this.description,
      montant: montant ?? this.montant,
      statut: statut ?? this.statut,
      otpCode: otpCode ?? this.otpCode,
      otpExpiresAt: otpExpiresAt ?? this.otpExpiresAt,
      photosJson: photosJson ?? this.photosJson,
      valideAt: valideAt ?? this.valideAt,
      payeAt: payeAt ?? this.payeAt,
    );
  }
}
