import 'package:url_launcher/url_launcher.dart';

import '../../data/models/payment_model.dart';
import '../../data/repositories/payment_repository.dart';

/// Issue d'un paiement ouvert chez l'opérateur (Wave, Orange Money).
enum OperatorPaymentOutcome {
  /// Confirmé par l'opérateur : le serveur a encaissé.
  confirmed,

  /// Échoué ou annulé par le client.
  failed,

  /// Pas encore confirmé à la fin du suivi : la confirmation arrivera par le
  /// webhook ; l'écran invite à actualiser.
  pending,
}

/// Ouvre la page de paiement de l'opérateur puis suit le statut côté serveur
/// (Chantier 11). Partagé par le paiement d'une commande, d'un panier et
/// d'une course : le montant est toujours fixé par le serveur, l'application
/// ne fait qu'ouvrir la page et interroger le statut.
class OperatorPaymentRunner {
  OperatorPaymentRunner({
    PaymentRepository? paymentRepository,
    Future<bool> Function(Uri uri)? openPaymentUrl,
    this.pollInterval = const Duration(seconds: 2),
    this.pollAttempts = 6,
  })  : _paymentRepo = paymentRepository ?? PaymentRepository(),
        _openPaymentUrl = openPaymentUrl ??
            ((uri) => launchUrl(uri, mode: LaunchMode.externalApplication));

  final PaymentRepository _paymentRepo;
  final Future<bool> Function(Uri uri) _openPaymentUrl;
  final Duration pollInterval;
  final int pollAttempts;

  Future<OperatorPaymentOutcome> run(PaymentInitiationModel payment) async {
    final url = payment.launchUrl;
    if (url != null && url.isNotEmpty) {
      final uri = Uri.tryParse(url);
      if (uri != null) await _openPaymentUrl(uri);
    }

    for (var attempt = 0; attempt < pollAttempts; attempt++) {
      final status = await _paymentRepo.checkStatus(payment.transactionId);
      if (status.isConfirmed) return OperatorPaymentOutcome.confirmed;
      if (status.isFailed) return OperatorPaymentOutcome.failed;
      await Future<void>.delayed(pollInterval);
    }

    return OperatorPaymentOutcome.pending;
  }
}
