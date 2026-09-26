import 'package:url_launcher/url_launcher.dart';

import '../../data/repositories/payment_repository.dart';
import '../utils/error_handler.dart';

/// Ouvre le reçu PDF d'une transaction confirmée (Chantier 11) : le serveur
/// délivre un lien signé valable 15 minutes, jamais une URL permanente
/// (Règle d'or 40), ouvert ensuite dans le navigateur du téléphone.
///
/// Partagé par l'historique des transactions et les retraits livreur.
class ReceiptOpener {
  ReceiptOpener({
    Future<String> Function(int transactionId)? fetchLink,
    Future<bool> Function(Uri uri)? openUrl,
  })  : _fetchLink = fetchLink ?? PaymentRepository().receiptLink,
        _openUrl = openUrl ??
            ((uri) => launchUrl(uri, mode: LaunchMode.externalApplication));

  final Future<String> Function(int transactionId) _fetchLink;
  final Future<bool> Function(Uri uri) _openUrl;

  /// Renvoie `null` si le reçu s'est ouvert, sinon le message à afficher.
  Future<String?> open(int transactionId) async {
    try {
      final uri = Uri.tryParse(await _fetchLink(transactionId));
      if (uri == null || !await _openUrl(uri)) {
        return 'Impossible d\'ouvrir le reçu sur cet appareil.';
      }

      return null;
    } catch (e) {
      return ErrorHandler.getErrorMessage(e);
    }
  }
}
