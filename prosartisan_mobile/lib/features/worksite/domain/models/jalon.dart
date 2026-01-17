/// Domain model for Jalon (Milestone)
///
/// Represents a project milestone with proof submission and validation
/// Requirements: 6.2, 6.3
class Jalon {
  final String id;
  final String chantierId;
  final String description;
  final int sequenceNumber;
  final String status;
  final String statusLabel;
  final MoneyAmount laborAmount;
  final ProofOfDelivery? proof;
  final bool isCompleted;
  final bool canBeValidated;
  final bool isAutoValidationDue;
  final DateTime? autoValidationDeadline;
  final double? hoursUntilAutoValidation;
  final String? contestReason;
  final DateTime createdAt;
  final DateTime? submittedAt;
  final DateTime? validatedAt;

  const Jalon({
    required this.id,
    required this.chantierId,
    required this.description,
    required this.sequenceNumber,
    required this.status,
    required this.statusLabel,
    required this.laborAmount,
    this.proof,
    required this.isCompleted,
    required this.canBeValidated,
    required this.isAutoValidationDue,
    this.autoValidationDeadline,
    this.hoursUntilAutoValidation,
    this.contestReason,
    required this.createdAt,
    this.submittedAt,
    this.validatedAt,
  });

  factory Jalon.fromJson(Map<String, dynamic> json) {
    return Jalon(
      id: json['id'] as String,
      chantierId: json['chantier_id'] as String,
      description: json['description'] as String,
      sequenceNumber: json['sequence_number'] as int,
      status: json['status'] as String,
      statusLabel: json['status_label'] as String,
      laborAmount: MoneyAmount.fromJson(
        json['labor_amount'] as Map<String, dynamic>,
      ),
      proof: json['proof'] != null
          ? ProofOfDelivery.fromJson(json['proof'] as Map<String, dynamic>)
          : null,
      isCompleted: json['is_completed'] as bool,
      canBeValidated: json['can_be_validated'] as bool,
      isAutoValidationDue: json['is_auto_validation_due'] as bool,
      autoValidationDeadline: json['auto_validation_deadline'] != null
          ? DateTime.parse(json['auto_validation_deadline'] as String)
          : null,
      hoursUntilAutoValidation: json['hours_until_auto_validation'] as double?,
      contestReason: json['contest_reason'] as String?,
      createdAt: DateTime.parse(json['created_at'] as String),
      submittedAt: json['submitted_at'] != null
          ? DateTime.parse(json['submitted_at'] as String)
          : null,
      validatedAt: json['validated_at'] != null
          ? DateTime.parse(json['validated_at'] as String)
          : null,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'id': id,
      'chantier_id': chantierId,
      'description': description,
      'sequence_number': sequenceNumber,
      'status': status,
      'status_label': statusLabel,
      'labor_amount': laborAmount.toJson(),
      'proof': proof?.toJson(),
      'is_completed': isCompleted,
      'can_be_validated': canBeValidated,
      'is_auto_validation_due': isAutoValidationDue,
      'auto_validation_deadline': autoValidationDeadline?.toIso8601String(),
      'hours_until_auto_validation': hoursUntilAutoValidation,
      'contest_reason': contestReason,
      'created_at': createdAt.toIso8601String(),
      'submitted_at': submittedAt?.toIso8601String(),
      'validated_at': validatedAt?.toIso8601String(),
    };
  }

  bool get isPending => status == 'PENDING';
  bool get isSubmitted => status == 'SUBMITTED';
  bool get isValidated => status == 'VALIDATED';
  bool get isContested => status == 'CONTESTED';

  bool get hasProof => proof != null;
  bool get needsProof => isPending && !hasProof;
  bool get awaitingValidation => isSubmitted && !isAutoValidationDue;
  bool get needsUrgentAction => isAutoValidationDue && !isCompleted;
}

/// Proof of delivery for a milestone
class ProofOfDelivery {
  final String photoUrl;
  final GPSLocation location;
  final DateTime capturedAt;
  final Map<String, dynamic> exifData;
  final bool integrityVerified;

  const ProofOfDelivery({
    required this.photoUrl,
    required this.location,
    required this.capturedAt,
    required this.exifData,
    required this.integrityVerified,
  });

  factory ProofOfDelivery.fromJson(Map<String, dynamic> json) {
    return ProofOfDelivery(
      photoUrl: json['photo_url'] as String,
      location: GPSLocation.fromJson(json['location'] as Map<String, dynamic>),
      capturedAt: DateTime.parse(json['captured_at'] as String),
      exifData: Map<String, dynamic>.from(json['exif_data'] as Map),
      integrityVerified: json['integrity_verified'] as bool,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      'photo_url': photoUrl,
      'location': location.toJson(),
      'captured_at': capturedAt.toIso8601String(),
      'exif_data': exifData,
      'integrity_verified': integrityVerified,
    };
  }
}

/// GPS location information
class GPSLocation {
  final double latitude;
  final double longitude;
  final double accuracy;

  const GPSLocation({
    required this.latitude,
    required this.longitude,
    required this.accuracy,
  });

  factory GPSLocation.fromJson(Map<String, dynamic> json) {
    return GPSLocation(
      latitude: (json['latitude'] as num).toDouble(),
      longitude: (json['longitude'] as num).toDouble(),
      accuracy: (json['accuracy'] as num).toDouble(),
    );
  }

  Map<String, dynamic> toJson() {
    return {'latitude': latitude, 'longitude': longitude, 'accuracy': accuracy};
  }
}

/// Money amount representation
class MoneyAmount {
  final int centimes;
  final String formatted;
  final String currency;

  const MoneyAmount({
    required this.centimes,
    required this.formatted,
    required this.currency,
  });

  factory MoneyAmount.fromJson(Map<String, dynamic> json) {
    return MoneyAmount(
      centimes: json['centimes'] as int,
      formatted: json['formatted'] as String,
      currency: json['currency'] as String,
    );
  }

  Map<String, dynamic> toJson() {
    return {'centimes': centimes, 'formatted': formatted, 'currency': currency};
  }

  double get amount => centimes / 100.0;
}
