class CommunicationModel {
  final int id;
  final String type; // annonce | le_saviez_vous
  final String titre;
  final String contenu;
  final List<String> cibles;
  final String statut;
  final String? publieAt;
  final String? clotureAt;
  final String createdAt;

  const CommunicationModel({
    required this.id,
    required this.type,
    required this.titre,
    required this.contenu,
    required this.cibles,
    required this.statut,
    this.publieAt,
    this.clotureAt,
    required this.createdAt,
  });

  factory CommunicationModel.fromJson(Map<String, dynamic> json) {
    var ciblesList = <String>[];
    if (json['cibles_json'] is List) {
      ciblesList = List<String>.from(json['cibles_json']);
    } else if (json['cibles'] is List) {
      ciblesList = List<String>.from(json['cibles']);
    }

    return CommunicationModel(
      id: json['id'] as int? ?? 0,
      type: json['type'] as String? ?? 'annonce',
      titre: json['titre'] as String? ?? json['title'] as String? ?? '',
      contenu: json['contenu'] as String? ??
          json['content'] as String? ??
          json['message'] as String? ??
          '',
      cibles: ciblesList,
      statut:
          json['statut'] as String? ?? json['status'] as String? ?? 'brouillon',
      publieAt: json['publie_at'] as String?,
      clotureAt: json['cloture_at'] as String?,
      createdAt:
          json['created_at'] as String? ?? DateTime.now().toIso8601String(),
    );
  }

  Map<String, dynamic> toJson() => {
        'id': id,
        'type': type,
        'titre': titre,
        'contenu': contenu,
        'cibles_json': cibles,
        'statut': statut,
        'publie_at': publieAt,
        'cloture_at': clotureAt,
        'created_at': createdAt,
      };
}
