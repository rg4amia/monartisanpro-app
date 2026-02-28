import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';

class WalletRepository {
  final ApiClient _client = ApiClient();

  Future<Map<String, int>> getBalance() async {
    final res = await _client.get(ApiEndpoints.walletBalance);
    final data = res.data['data'] as Map<String, dynamic>;
    return {
      'walletMateriaux': data['walletMateriaux'] as int? ?? 0,
      'walletMo': data['walletMo'] as int? ?? 0,
      'total': data['total'] as int? ?? 0,
    };
  }
}
