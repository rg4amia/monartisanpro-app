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
  });

  bool get needsReferent => montantTotal > 2000000;

  factory MissionModel.fromJson(Map<String, dynamic> json) => MissionModel(
        id: json['id'] as int,
        clientId: json['clientId'] as int,
        artisanId: json['artisanId'] as int,
        status: json['status'] as String,
        montantTotal: json['montantTotal'] as int,
        montantMateriaux: json['montantMateriaux'] as int,
        montantMo: json['montantMo'] as int,
        ratioMateriaux: (json['ratioMateriaux'] as num).toDouble(),
        createdAt: json['createdAt'] as String,
        clientName: json['clientName'] as String?,
        artisanName: json['artisanName'] as String?,
        description: json['description'] as String?,
        category: json['category'] as String?,
        urgency: json['urgency'] as String?,
        location: json['location'] as String?,
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'clientId': clientId,
        'artisanId': artisanId,
        'status': status,
        'montantTotal': montantTotal,
        'montantMateriaux': montantMateriaux,
        'montantMo': montantMo,
        'ratioMateriaux': ratioMateriaux,
        'createdAt': createdAt,
        'clientName': clientName,
        'artisanName': artisanName,
        'description': description,
        'category': category,
        'urgency': urgency,
        'location': location,
      };
}
