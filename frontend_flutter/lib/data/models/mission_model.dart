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
  final String? paymentStatus;
  final String paymentType;
  final String? statusGemini;
  final bool hasDevis;
  final List<String> photos;
  final String? clientPhone;
  final double? clientLatitude;
  final double? clientLongitude;

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
    this.paymentStatus,
    this.paymentType = 'total',
    this.statusGemini,
    this.hasDevis = false,
    this.photos = const [],
    this.clientPhone,
    this.clientLatitude,
    this.clientLongitude,
  });

  bool get needsReferent => montantTotal > 2000000;

  bool get isPaid {
    return paymentStatus == 'funded' ||
        status == 'financee' ||
        status == 'en_cours' ||
        status == 'terminee' ||
        status == 'litige';
  }

  String get rawStatus => statusGemini ?? status;

  String get statusLabel {
    switch (status) {
      case 'pending_artisan_acceptance':
        return 'En attente acceptation artisan';
      case 'en_attente':
        return 'Devis en attente';
      case 'financee':
        return 'Financee';
      case 'en_cours':
        return 'En cours';
      case 'terminee':
        return 'Terminee';
      case 'litige':
        return 'Litige en cours';
      default:
        return status;
    }
  }

  String get urgencyLabel {
    switch (urgency) {
      case 'faible':
        return 'Faible';
      case 'moyen':
        return 'Moyen';
      case 'urgent':
        return 'Urgent';
      default:
        return urgency ?? 'Non specifie';
    }
  }

  factory MissionModel.fromJson(Map<String, dynamic> json) {
    final client = json['client'] is Map<String, dynamic>
        ? json['client'] as Map<String, dynamic>
        : null;
    final artisan = json['artisan'] is Map<String, dynamic>
        ? json['artisan'] as Map<String, dynamic>
        : null;
    final financials = json['financials'] as Map<String, dynamic>?;
    final montantMateriaux = _parseAmount(
      json['montant_materiaux'] ??
          json['montantMateriaux'] ??
          financials?['tokenAmount'],
    );
    final montantMo = _parseAmount(
      json['montant_mo'] ?? json['montantMo'] ?? financials?['laborCost'],
    );
    final montantTotal = _parseAmount(
      json['montant_total'] ??
          json['montantTotal'] ??
          json['total'] ??
          (montantMateriaux + montantMo),
    );

    return MissionModel(
      id: _parseInt(json['id']),
      clientId: _parseInt(
        json['client_id'] ?? json['clientId'] ?? client?['id'],
      ),
      artisanId: _parseInt(
        json['artisan_id'] ?? json['artisanId'] ?? artisan?['id'],
      ),
      status: normalizeStatus((json['status'] ?? '').toString()),
      montantTotal: montantTotal,
      montantMateriaux: montantMateriaux,
      montantMo: montantMo,
      ratioMateriaux: _parseRatio(
        json['ratio_materiaux'] ??
            json['ratioMateriaux'] ??
            _computeRatio(montantMateriaux, montantTotal),
      ),
      createdAt:
          (json['created_at'] ??
                  json['createdAt'] ??
                  DateTime.now().toIso8601String())
              .toString(),
      updatedAt: (json['updated_at'] ?? json['updatedAt'])?.toString(),
      clientName:
          (json['client_name'] ??
                  json['clientName'] ??
                  client?['name'] ??
                  json['clientNom'])
              as String?,
      artisanName:
          (json['artisan_name'] ?? json['artisanName'] ?? artisan?['name'])
              as String?,
      description: (json['description'] ?? json['problem']) as String?,
      category:
          (json['category'] ??
                  json['geminiCategory'] ??
                  json['artisanCategory'])
              as String?,
      urgency: (json['urgency'] ?? json['geminiUrgency']) as String?,
      location: _parseLocation(json),
      paymentStatus:
          (json['paymentStatus'] as String?) ??
          _derivePaymentStatus((json['status'] ?? '').toString()),
      paymentType:
          (json['payment_type'] ?? json['paymentType'] ?? 'total').toString(),
      statusGemini:
          (json['statusGemini'] ?? json['status'] ?? '').toString().isEmpty
          ? null
          : (json['statusGemini'] ?? json['status']).toString(),
      hasDevis: json['has_devis'] == true || json['hasDevis'] == true,
      photos: (json['photos'] as List<dynamic>?)
              ?.map((e) => e.toString())
              .toList() ??
          [],
      clientPhone: (client?['phone'] ?? json['clientPhone'])?.toString(),
      clientLatitude: json['clientCoordinates'] is Map<String, dynamic>
          ? double.tryParse((json['clientCoordinates'] as Map<String, dynamic>)['lat'].toString())
          : (json['clientLatitude'] != null ? double.tryParse(json['clientLatitude'].toString()) : null),
      clientLongitude: json['clientCoordinates'] is Map<String, dynamic>
          ? double.tryParse((json['clientCoordinates'] as Map<String, dynamic>)['lng'].toString())
          : (json['clientLongitude'] != null ? double.tryParse(json['clientLongitude'].toString()) : null),
    );
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
    'paymentStatus': paymentStatus,
    'payment_type': paymentType,
    'statusGemini': statusGemini,
    'has_devis': hasDevis,
    'photos': photos,
    'clientPhone': clientPhone,
    'clientLatitude': clientLatitude,
    'clientLongitude': clientLongitude,
  };

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
    String? paymentStatus,
    String? paymentType,
    String? statusGemini,
    bool? hasDevis,
    List<String>? photos,
    String? clientPhone,
    double? clientLatitude,
    double? clientLongitude,
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
      paymentStatus: paymentStatus ?? this.paymentStatus,
      paymentType: paymentType ?? this.paymentType,
      statusGemini: statusGemini ?? this.statusGemini,
      hasDevis: hasDevis ?? this.hasDevis,
      photos: photos ?? this.photos,
      clientPhone: clientPhone ?? this.clientPhone,
      clientLatitude: clientLatitude ?? this.clientLatitude,
      clientLongitude: clientLongitude ?? this.clientLongitude,
    );
  }

  static String? _parseLocation(Map<String, dynamic> json) {
    final location = json['location'];
    if (location is String && location.trim().isNotEmpty) {
      return location;
    }

    return (json['clientAddress'] ??
            json['location_address'] ??
            json['adresse'])
        as String?;
  }

  static int _parseInt(dynamic value) {
    if (value == null) return 0;
    if (value is int) return value;
    if (value is double) return value.toInt();
    return int.tryParse(value.toString()) ?? 0;
  }

  static int _parseAmount(dynamic value) {
    if (value == null) return 0;
    if (value is int) return value;
    if (value is double) return value.toInt();
    return int.tryParse(value.toString()) ?? 0;
  }

  static double _parseRatio(dynamic value) {
    if (value == null) return 0.0;
    if (value is double) return value.clamp(0.0, 1.0);
    if (value is int) return value.toDouble().clamp(0.0, 1.0);
    return (double.tryParse(value.toString()) ?? 0.0).clamp(0.0, 1.0);
  }

  static double _computeRatio(int materiaux, int total) {
    if (total <= 0) return 0.0;
    return (materiaux / total).clamp(0.0, 1.0);
  }

  static String normalizeStatus(String rawStatus) {
    switch (rawStatus) {
      case 'draft':
      case 'pending_funding':
      case 'sent':
      case 'quote_provided':
      case 'quote_rejected':
      case 'pending':
        return 'en_attente';
      case 'funded_locked':
      case 'funded':
      case 'paid':
        return 'financee';
      case 'in_progress':
      case 'pending_approval':
      case 'materials_picked_up':
      case 'work_done':
      case 'shipping':
      case 'driver_assigned':
        return 'en_cours';
      case 'completed':
      case 'delivered':
        return 'terminee';
      case 'disputed':
        return 'litige';
      default:
        return rawStatus.isEmpty ? 'en_attente' : rawStatus;
    }
  }

  static String? _derivePaymentStatus(String status) {
    switch (status) {
      case 'funded':
      case 'paid':
      case 'financee':
      case 'en_cours':
      case 'terminee':
        return 'funded';
      case 'disputed':
      case 'litige':
        return 'blocked';
      case '':
        return null;
      default:
        return 'pending';
    }
  }
}
