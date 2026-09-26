import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/utils/json_readers.dart';
import '../models/payment_model.dart';

class PaymentRepository {
  final ApiClient _client = ApiClient();

  Future<PaymentInitiationModel> initiatePayment({
    required int missionId,
    required int devisId,
    required int montant,
    required String provider,
    required String phone,
    String? paymentType,
  }) async {
    final res = await _client.post(
      ApiEndpoints.paymentsInitiate,
      data: {
        'mission_id': missionId,
        'devis_id': devisId,
        'montant': montant,
        'provider': provider,
        'phone': phone,
        if (paymentType != null) 'payment_type': paymentType,
      },
    );

    return PaymentInitiationModel.fromJson(
      (res.data as Map<String, dynamic>)['data'] as Map<String, dynamic>,
    );
  }

  Future<PaymentInitiationModel> initiateJalonPayment({
    required int jalonId,
    required String provider,
    required String phone,
  }) async {
    final res = await _client.post(
      '/payments/jalons/$jalonId/pay',
      data: {
        'provider': provider,
        'phone': phone,
      },
    );

    return PaymentInitiationModel.fromJson(
      (res.data as Map<String, dynamic>)['data'] as Map<String, dynamic>,
    );
  }

  /// Règlement de la course livrée (modèle « à la Yango ») : le montant est
  /// fixé par le serveur à la livraison, jamais transmis par l'application.
  Future<PaymentInitiationModel> initiateDeliveryFarePayment({
    required int orderId,
    required String provider,
    required String phone,
  }) async {
    final res = await _client.post(
      ApiEndpoints.deliveryFarePayment(orderId),
      data: {
        'provider': provider,
        'phone': phone,
      },
    );

    return PaymentInitiationModel.fromJson(
      (res.data as Map<String, dynamic>)['data'] as Map<String, dynamic>,
    );
  }

  /// Règlement d'une commande de matériaux en attente — ou de tout son
  /// panier multi-quincailleries (Chantier 11). Montant fixé par le serveur.
  Future<PaymentInitiationModel> initiateOrderPayment({
    required int orderId,
    required String provider,
    required String phone,
  }) async {
    final res = await _client.post(
      ApiEndpoints.orderCheckoutPayment(orderId),
      data: {'provider': provider, 'phone': phone},
    );

    return PaymentInitiationModel.fromJson(
      readMap(readMap(res.data)?['data']) ?? const <String, dynamic>{},
    );
  }

  /// Lien signé (15 min) vers le reçu PDF d'une transaction confirmée.
  Future<String> receiptLink(int transactionId) async {
    final res = await _client.get(
      ApiEndpoints.transactionReceiptLink(transactionId),
    );
    final url = readString(readMap(readMap(res.data)?['data'])?['url']);
    if (url == null || url.isEmpty) {
      throw const FormatException('Lien de reçu absent de la réponse.');
    }

    return url;
  }

  Future<PaymentStatusModel> checkStatus(int transactionId) async {
    final res = await _client.get(ApiEndpoints.paymentStatus(transactionId));
    return PaymentStatusModel.fromJson(
      (res.data as Map<String, dynamic>)['data'] as Map<String, dynamic>,
    );
  }
}
