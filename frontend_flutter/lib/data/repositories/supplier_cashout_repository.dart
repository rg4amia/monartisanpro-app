import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../models/supplier_cashout_model.dart';

class SupplierCashoutRepository {
  final ApiClient _client = ApiClient();

  Future<Map<String, dynamic>> getCashouts({int page = 1}) async {
    final res = await _client.get(
      ApiEndpoints.supplierCashouts,
      params: {'page': page},
    );

    final data = (res.data as Map<String, dynamic>)['data'] as Map<String, dynamic>;
    final statsJson = data['stats'] as Map<String, dynamic>? ?? {};
    final cashoutsData = data['cashouts'] as Map<String, dynamic>? ?? {};
    final list = (cashoutsData['data'] as List<dynamic>? ?? [])
        .map((e) => SupplierCashoutModel.fromJson(Map<String, dynamic>.from(e as Map)))
        .toList();

    return {
      'stats': SupplierCashoutStatsModel.fromJson(statsJson),
      'cashouts': list,
      'total': (cashoutsData['total'] as num?)?.toInt() ?? list.length,
    };
  }

  Future<SupplierCashoutModel> requestCashout({
    required int montantBrut,
    required String modeRetrait,
    String? beneficiaryName,
    String? beneficiaryPhone,
    String? bankName,
    String? bankAccountNumber,
    String? notes,
  }) async {
    final payload = {
      'montant_brut': montantBrut,
      'mode_retrait': modeRetrait,
      if (beneficiaryName != null && beneficiaryName.isNotEmpty)
        'beneficiary_name': beneficiaryName,
      if (beneficiaryPhone != null && beneficiaryPhone.isNotEmpty)
        'beneficiary_phone': beneficiaryPhone,
      if (bankName != null && bankName.isNotEmpty) 'bank_name': bankName,
      if (bankAccountNumber != null && bankAccountNumber.isNotEmpty)
        'bank_account_number': bankAccountNumber,
      if (notes != null && notes.isNotEmpty) 'notes': notes,
    };

    final res = await _client.post(
      ApiEndpoints.supplierCashouts,
      data: payload,
    );

    final cashoutJson = (res.data as Map<String, dynamic>)['data'] as Map<String, dynamic>;
    return SupplierCashoutModel.fromJson(cashoutJson);
  }

  String getReceiptUrl(int id) => ApiEndpoints.supplierCashoutReceipt(id);
}
