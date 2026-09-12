import 'package:flutter/material.dart';

import '../../../../core/theme/app_colors.dart';

/// Note moyenne d'un acteur, ou mention explicite s'il n'a jamais été évalué.
///
/// L'API renvoyait auparavant 5,0 par défaut pour un compte sans aucune
/// évaluation : les classements affichaient la note maximale à des acteurs
/// n'ayant jamais servi personne. Une note absente doit se dire — l'étoile
/// n'apparaît que lorsqu'elle a été gagnée.
class RatingLabel extends StatelessWidget {
  const RatingLabel({super.key, required this.rating});

  /// `null` quand aucune évaluation n'existe.
  final double? rating;

  /// Lit la note d'un enregistrement JSON en tolérant `null`, un nombre ou
  /// une chaîne.
  static double? parse(dynamic value) {
    if (value == null) return null;
    if (value is num) return value.toDouble();

    return double.tryParse(value.toString());
  }

  @override
  Widget build(BuildContext context) {
    final value = rating;

    if (value == null) {
      return const Text(
        'Non évalué',
        style: TextStyle(
          fontWeight: FontWeight.w600,
          fontSize: 12.5,
          color: AppColors.textMuted,
        ),
      );
    }

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        const Icon(Icons.star_rounded, color: Colors.amber, size: 16),
        const SizedBox(width: 4),
        Text(
          value.toStringAsFixed(1),
          style: const TextStyle(
            fontWeight: FontWeight.w700,
            fontSize: 13,
            color: AppColors.textPrimary,
          ),
        ),
      ],
    );
  }
}
