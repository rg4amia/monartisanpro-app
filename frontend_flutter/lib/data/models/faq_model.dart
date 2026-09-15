class FaqModel {
  final int id;
  final String question;
  final String reponse;
  final String? categorie;
  final int ordre;

  const FaqModel({
    required this.id,
    required this.question,
    required this.reponse,
    this.categorie,
    this.ordre = 0,
  });

  factory FaqModel.fromJson(Map<String, dynamic> json) => FaqModel(
        id: json['id'] is int
            ? json['id'] as int
            : int.tryParse('${json['id']}') ?? 0,
        question: json['question'] as String? ?? '',
        reponse: json['reponse'] as String? ?? '',
        categorie: json['categorie'] as String?,
        ordre: json['ordre'] is int
            ? json['ordre'] as int
            : int.tryParse('${json['ordre']}') ?? 0,
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'question': question,
        'reponse': reponse,
        'categorie': categorie,
        'ordre': ordre,
      };
}
