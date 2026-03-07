class DevisLigne {
  final String type; // 'mo' | 'mat'
  final String description;
  final int montant;

  const DevisLigne({
    required this.type,
    required this.description,
    required this.montant,
  });

  factory DevisLigne.fromJson(Map<String, dynamic> json) => DevisLigne(
        type: json['type'] as String,
        description: json['description'] as String,
        montant: json['montant'] as int,
      );

  Map<String, dynamic> toJson() => {
        'type': type,
        'description': description,
        'montant': montant,
      };
}

class DevisJalon {
  final int ordre;
  final String description;
  final int montant;
  final String dateCible;

  const DevisJalon({
    required this.ordre,
    required this.description,
    required this.montant,
    required this.dateCible,
  });

  factory DevisJalon.fromJson(Map<String, dynamic> json) => DevisJalon(
        ordre: json['ordre'] as int,
        description: json['description'] as String,
        montant: json['montant'] as int,
        dateCible: json['date_cible'] as String? ?? json['dateCible'] as String,
      );

  Map<String, dynamic> toJson() => {
        'ordre': ordre,
        'description': description,
        'montant': montant,
        'date_cible': dateCible,
      };
}

class DevisModel {
  final int id;
  final int missionId;
  final int artisanId;
  final List<DevisLigne> lignes;
  final List<DevisJalon> jalons;
  final String statut;
  final String createdAt;
  final String? artisanName;

  const DevisModel({
    required this.id,
    required this.missionId,
    required this.artisanId,
    required this.lignes,
    required this.jalons,
    required this.statut,
    required this.createdAt,
    this.artisanName,
  });

  int get totalMo => lignes
      .where((l) => l.type == 'mo')
      .fold(0, (s, l) => s + l.montant);

  int get totalMat => lignes
      .where((l) => l.type == 'mat')
      .fold(0, (s, l) => s + l.montant);

  int get totalGeneral => totalMo + totalMat;

  factory DevisModel.fromJson(Map<String, dynamic> json) => DevisModel(
        id: json['id'] as int,
        missionId: json['missionId'] as int,
        artisanId: json['artisanId'] as int,
        lignes: (json['lignesJson'] as List<dynamic>)
            .map((e) => DevisLigne.fromJson(e as Map<String, dynamic>))
            .toList(),
        jalons: (json['jalonsJson'] as List<dynamic>)
            .map((e) => DevisJalon.fromJson(e as Map<String, dynamic>))
            .toList(),
        statut: json['statut'] as String,
        createdAt: json['createdAt'] as String,
        artisanName: json['artisanName'] as String?,
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'missionId': missionId,
        'artisanId': artisanId,
        'lignesJson': lignes.map((l) => l.toJson()).toList(),
        'jalonsJson': jalons.map((j) => j.toJson()).toList(),
        'statut': statut,
        'createdAt': createdAt,
        'artisanName': artisanName,
      };
}
