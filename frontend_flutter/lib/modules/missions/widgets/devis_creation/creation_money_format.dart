/// Formatage FCFA local aux widgets de création de devis : séparateur
/// d'espace simple entre les milliers (ex. `1 500 000 FCFA`).
///
/// Volontairement distinct de `Formatters.fcfa` (qui utilise
/// `NumberFormat` et une espace insécable fine) pour conserver le rendu
/// visuel historique de cet écran.
String formatCreationFcfa(int amount) {
  return '${amount.toString().replaceAllMapped(
        RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
        (Match m) => '${m[1]} ',
      )} FCFA';
}
