class MissionModel {
  final int id;
  final int clientId;
  final int artisanId;
  final String status;
  final int montantTotal;
  final int montantMateriaux;
  final int montantMo;
  final double ratioMateriaux;
  final String? clientName;
  final String? artisanName;
  final String? description;
  final String? category;
  final String? urgency;
  final String? location;
  final String createdAt;
  final String? updatedAt;

  const MissionModel({
    required this.id,
    required this.clientId,
    required this.artisanId,
    required this.status,
    required this.montantTotal,
    required this.montantMateriaux,
    required this.montantMo,
    required this.ratioMateriaux,
    required this.createdAt,
    this.clientName,
    this.artisanName,
    this.description,
    this.category,
    this.urgency,
    this.location,
    this.updatedAt,
  });

  /// Indique si cette mission nécessite la validation d'un Référent de zone
  /// (missions > 2 000 000 FCFA selon les règles métier)
  bool get needsReferent => montantTotal > 2000000;

  /// Statut formaté en français
  String get statusLabel {
    switch (status) {
      case 'en_attente':
        return 'Devis en attente';
      case 'financee':
        return 'Financée';
      case 'en_cours':
        return 'En cours';
      case 'terminee':
        return 'Terminée';
      case 'litige':
        return 'Litige en cours';
      default:
        return status;
    }
  }

  /// Urgence formatée en français
  String get urgencyLabel {
    switch (urgency) {
      case 'faible':
        return 'Faible';
      case 'moyen':
        return 'Moyen';
      case 'urgent':
        return 'Urgent';
      default:
        return urgency ?? 'Non spécifié';
    }
  }

  factory MissionModel.fromJson(Map<String, dynamic> json) {
    // Support pour snake_case (Laravel) et camelCase (frontend)
    return MissionModel(
      id: json['id'] as int,
      clientId: (json['client_id'] ?? json['clientId']) as int,
      artisanId: (json['artisan_id'] ?? json['artisanId']) as int,
      status: json['status'] as String,
      montantTotal: _parseAmount(json['montant_total'] ?? json['montantTotal']),
      montantMateriaux: _parseAmount(json['montant_materiaux'] ?? json['montantMateriaux']),
      montantMo: _parseAmount(json['montant_mo'] ?? json['montantMo']),
      ratioMateriaux: _parseRatio(json['ratio_materiaux'] ?? json['ratioMateriaux']),
      createdAt: (json['created_at'] ?? json['createdAt']) as String,
      updatedAt: (json['updated_at'] ?? json['updatedAt']) as String?,
      clientName: (json['client_name'] ?? json['clientName']) as String?,
      artisanName: (json['artisan_name'] ?? json['artisanName']) as String?,
      description: json['description'] as String?,
      category: json['category'] as String?,
      urgency: json['urgency'] as String?,
      location: json['location'] as String?,
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

  /// Parse un ratio (0.0 à 1.0)
  static double _parseRatio(dynamic value) {
    if (value == null) return 0.0;
    if (value is double) return value;
    if (value is int) return value.toDouble();
    if (value is String) return double.tryParse(value) ?? 0.0;
    return 0.0;
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'client_id': clientId,
        'artisan_id': artisanId,
        'status': status,
        'montant_total': montantTotal,
        'montant_materiaux': montantMateriaux,
        'montant_mo': montantMo,
        'ratio_materiaux': ratioMateriaux,
        'created_at': createdAt,
        'updated_at': updatedAt,
        'client_name': clientName,
        'artisan_name': artisanName,
        'description': description,
        'category': category,
        'urgency': urgency,
        'location': location,
      };

  /// Copie l'objet avec des modifications
  MissionModel copyWith({
    int? id,
    int? clientId,
    int? artisanId,
    String? status,
    int? montantTotal,
    int? montantMateriaux,
    int? montantMo,
    double? ratioMateriaux,
    String? clientName,
    String? artisanName,
    String? description,
    String? category,
    String? urgency,
    String? location,
    String? createdAt,
    String? updatedAt,
  }) {
    return MissionModel(
      id: id ?? this.id,
      clientId: clientId ?? this.clientId,
      artisanId: artisanId ?? this.artisanId,
      status: status ?? this.status,
      montantTotal: montantTotal ?? this.montantTotal,
      montantMateriaux: montantMateriaux ?? this.montantMateriaux,
      montantMo: montantMo ?? this.montantMo,
      ratioMateriaux: ratioMateriaux ?? this.ratioMateriaux,
      createdAt: createdAt ?? this.createdAt,
      updatedAt: updatedAt ?? this.updatedAt,
      clientName: clientName ?? this.clientName,
      artisanName: artisanName ?? this.artisanName,
      description: description ?? this.description,
      category: category ?? this.category,
      urgency: urgency ?? this.urgency,
      location: location ?? this.location,
    );
  }
}
