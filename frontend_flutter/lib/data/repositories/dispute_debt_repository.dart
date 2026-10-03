import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/network/network_executor.dart';
import '../../core/utils/json_readers.dart';
import '../models/dispute_debt_model.dart';
import '../models/payment_model.dart';

/// Dettes de litige de commande du fournisseur ou du livreur connecté
/// (Chantier 22). Aucun cache : une panne remonte au contrôleur.
class DisputeDebtRepository {
  DisputeDebtRepository({ApiClient? client}) : _client = client ?? ApiClient();

  final ApiClient _client;

  Future<List<DisputeDebt>> list() async {
    final res = await NetworkExecutor.run(
      () => _client.get(ApiEndpoints.disputeDebts),
    );

    return readDataList(res.data).map(DisputeDebt.fromJson).toList();
  }

  /// Ouvre le règlement de la dette chez l'opérateur. Le montant est le
  /// restant dû établi par le serveur : rien n'est envoyé par l'application.
  Future<PaymentInitiationModel> pay(
    int debtId, {
    required String provider,
  }) async {
    final res = await _client.post(
      ApiEndpoints.disputeDebtPay(debtId),
      data: {'provider': provider},
    );
    final data = readMap(readMap(res.data)?['data']);
    if (data == null) {
      throw const FormatException('Réponse inattendue du serveur.');
    }

    return PaymentInitiationModel.fromJson(data);
  }
}
