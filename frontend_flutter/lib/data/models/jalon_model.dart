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

  bool get isPending => statut == 'en_attente';
  bool get isSubmitted => statut == 'soumis';
  bool get isValidated => statut == 'valide';
  bool get isPaid => statut == 'paye';

  factory JalonModel.fromJson(Map<String, dynamic> json) => JalonModel(
        id: json['id'] as int,
        missionId: json['missionId'] as int,
        ordre: json['ordre'] as int,
        description: json['description'] as String,
        montant: json['montant'] as int,
        statut: json['statut'] as String,
        otpCode: json['otpCode'] as String?,
        otpExpiresAt: json['otpExpiresAt'] as String?,
        photosJson: (json['photosJson'] as List<dynamic>?)
            ?.map((e) => e as Map<String, dynamic>)
            .toList(),
        valideAt: json['valideAt'] as String?,
        payeAt: json['payeAt'] as String?,
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'missionId': missionId,
        'ordre': ordre,
        'description': description,
        'montant': montant,
        'statut': statut,
        'otpCode': otpCode,
        'otpExpiresAt': otpExpiresAt,
        'photosJson': photosJson,
        'valideAt': valideAt,
        'payeAt': payeAt,
      };
}
