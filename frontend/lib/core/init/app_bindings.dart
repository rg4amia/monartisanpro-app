import 'package:get/get.dart';
import '../network/dio_client.dart';
import '../network/auth_service.dart';
import '../network/kyc_service.dart';
import '../network/search_service.dart';
import '../network/trade_service.dart';
import '../network/project_service.dart';
import '../network/payment_service.dart';
import '../network/token_service.dart';
import '../network/score_service.dart';
import '../network/milestone_service.dart';
import '../network/dispute_service.dart';
import '../network/message_service.dart';
import '../../shared/controllers/auth_controller.dart';

/// Global dependency injection setup
/// Registers all services and controllers needed by the app
class AppBindings extends Bindings {
  @override
  void dependencies() {
    // Core network client (singleton)
    Get.put(DioClient(), permanent: true);

    // Register all services as lazy singletons
    // Services are created only when first requested
    Get.lazyPut<AuthService>(() => AuthService(), fenix: true);
    Get.lazyPut<KycService>(() => KycService(), fenix: true);
    Get.lazyPut<SearchService>(() => SearchService(), fenix: true);
    Get.lazyPut<TradeService>(() => TradeService(), fenix: true);
    Get.lazyPut<ProjectService>(() => ProjectService(), fenix: true);
    Get.lazyPut<PaymentService>(() => PaymentService(), fenix: true);
    Get.lazyPut<TokenService>(() => TokenService(), fenix: true);
    Get.lazyPut<ScoreService>(() => ScoreService(), fenix: true);
    Get.lazyPut<MilestoneService>(() => MilestoneService(), fenix: true);
    Get.lazyPut<DisputeService>(() => DisputeService(), fenix: true);
    Get.lazyPut<MessageService>(
      () => MessageService(Get.find<DioClient>()),
      fenix: true,
    );

    // Register AuthController as permanent singleton
    // This ensures it's available throughout the app lifecycle
    Get.put(AuthController(), permanent: true);
  }
}
