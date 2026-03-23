import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../models/payment_model.dart';

class PaymentRepository {
  final ApiClient _client = ApiClient();

  Future<PaymentInitiationModel> initiatePayment({
    required int missionId,
    required int devisId,
    required int montant,
    required String provider,
    required String phone,
  }) async {
    final res = await _client.post(
      ApiEndpoints.paymentsInitiate,
      data: {
        'mission_id': missionId,
        'devis_id': devisId,
        'montant': montant,
        'provider': provider,
        'phone': phone,
      },
    );

    return PaymentInitiationModel.fromJson(
      res.data['data'] as Map<String, dynamic>,
    );
  }

  Future<PaymentStatusModel> checkStatus(int transactionId) async {
    final res = await _client.get(ApiEndpoints.paymentStatus(transactionId));
    return PaymentStatusModel.fromJson(
      res.data['data'] as Map<String, dynamic>,
    );
  }
}
