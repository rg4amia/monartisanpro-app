import '../../core/network/api_client.dart';
import '../../core/network/api_endpoints.dart';
import '../models/micro_credit_model.dart';

class MicroCreditRepository {
  final ApiClient _client = ApiClient();

  Future<MicroCreditEligibilityModel> getEligibility() async {
    final res = await _client.get(ApiEndpoints.microCreditEligibility);
    return MicroCreditEligibilityModel.fromJson(
      (res.data as Map<String, dynamic>)['data'] as Map<String, dynamic>,
    );
  }

  Future<MicroCreditApplicationModel> apply(int amount) async {
    final res = await _client.post(
      ApiEndpoints.microCreditApply,
      data: {'amount': amount},
    );
    return MicroCreditApplicationModel.fromJson(
      (res.data as Map<String, dynamic>)['data'] as Map<String, dynamic>,
    );
  }
}
