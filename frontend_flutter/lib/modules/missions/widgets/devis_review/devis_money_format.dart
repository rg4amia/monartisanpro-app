/// Formate un montant entier en FCFA avec séparateur d'espace simple
/// (« 1 500 000 FCFA »). Comportement identique à l'ancien `_formatFCFA`
/// dupliqué dans `devis_review_screen.dart`.
String formatDevisFcfa(int amount) {
  return '${amount.toString().replaceAllMapped(
        RegExp(r'(\d{1,3})(?=(\d{3})+(?!\d))'),
        (Match m) => '${m[1]} ',
      )} FCFA';
}
