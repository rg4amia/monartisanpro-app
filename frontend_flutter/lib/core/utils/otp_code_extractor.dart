/// Extrait le code de vérification d'un SMS : la première suite d'exactement
/// [length] chiffres, non accolée à d'autres chiffres.
///
/// Le texte du SMS se modifie depuis le backoffice : l'extraction ne dépend
/// donc d'aucune formulation. Renvoie `null` si le message ne contient aucun
/// code, plutôt qu'une valeur approchante qui serait refusée par le serveur.
String? extractOtpCode(String? sms, {int length = 4}) {
  if (sms == null || sms.isEmpty) return null;

  return RegExp('(?<!\\d)\\d{$length}(?!\\d)').firstMatch(sms)?.group(0);
}
