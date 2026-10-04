/// Ramène un numéro ivoirien à la forme `+225` suivie de dix chiffres, pour
/// comparer deux numéros saisis différemment (`0701020304`, `+2250701020304`).
/// Une saisie vide donne une chaîne vide.
String normalizeIvorianPhone(String? phone) {
  final cleaned = (phone ?? '').replaceAll(RegExp(r'[^0-9+]'), '');
  if (cleaned.isEmpty) return '';
  return cleaned.startsWith('+') ? cleaned : '+225$cleaned';
}
