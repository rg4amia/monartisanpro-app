import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../../core/utils/json_readers.dart';
import '../models/payout_model.dart';

/// Versements Mobile Money reçus (artisan, livreur) et retraits des gains
/// livreur (Chantier 10).
class PayoutRepository {
  PayoutRepository({ApiClient? client}) : _client = client ?? ApiClient();

  final ApiClient _client;

  Future<List<PayoutModel>> getPayouts() async {
    final res = await _client.get(ApiEndpoints.payouts);

    return readDataList(res.data).map(PayoutModel.fromJson).toList();
  }

  /// Relance d'un virement échoué par son bénéficiaire. Renvoie le versement
  /// à jour et le message du serveur (succès ou nouvel échec).
  Future<({PayoutModel payout, String message})> retryPayout(int id) async {
    final res = await _client.post(ApiEndpoints.payoutRetry(id));
    final body = readMap(res.data) ?? const {};

    return (
      payout: PayoutModel.fromJson(readMap(body['data']) ?? const {}),
      message: readApiMessage(body) ?? 'Relance effectuée.',
    );
  }

  Future<({DriverCashoutStats stats, List<DriverCashoutModel> cashouts})>
      getDriverCashouts() async {
    final res = await _client.get(ApiEndpoints.driverCashouts);
    final data = readMap(readMap(res.data)?['data']) ?? const {};

    return (
      stats: DriverCashoutStats.fromJson(readMap(data['stats']) ?? const {}),
      cashouts: readMapList(data['cashouts'])
          .map(DriverCashoutModel.fromJson)
          .toList(),
    );
  }

  Future<({DriverCashoutModel cashout, String message})> requestDriverCashout({
    required int montant,
    required String mode,
    String? beneficiaryPhone,
    String? bankName,
    String? bankAccountNumber,
  }) async {
    final res = await _client.post(
      ApiEndpoints.driverCashouts,
      data: {
        'montant_brut': montant,
        'mode_retrait': mode,
        if (beneficiaryPhone != null && beneficiaryPhone.isNotEmpty)
          'beneficiary_phone': beneficiaryPhone,
        if (bankName != null && bankName.isNotEmpty) 'bank_name': bankName,
        if (bankAccountNumber != null && bankAccountNumber.isNotEmpty)
          'bank_account_number': bankAccountNumber,
      },
    );
    final body = readMap(res.data) ?? const {};

    return (
      cashout: DriverCashoutModel.fromJson(readMap(body['data']) ?? const {}),
      message: readApiMessage(body) ?? 'Demande de retrait enregistrée.',
    );
  }
}
