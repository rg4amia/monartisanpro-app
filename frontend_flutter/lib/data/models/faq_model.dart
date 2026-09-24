import 'package:frontend_flutter/core/utils/json_readers.dart';

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
        id: readInt(json['id']) ?? 0,
        question: readString(json['question']) ?? '',
        reponse: readString(json['reponse']) ?? '',
        categorie: readString(json['categorie']),
        ordre: readInt(json['ordre']) ?? 0,
      );

  Map<String, dynamic> toJson() => {
        'id': id,
        'question': question,
        'reponse': reponse,
        'categorie': categorie,
        'ordre': ordre,
      };
}
