import 'package:get/get.dart';
import '../../data/repositories/reputation_repository_impl.dart';
import '../../data/services/reputation_api_service.dart';
import '../../domain/repositories/reputation_repository.dart';
import '../controllers/reputation_controller.dart';

class ReputationBinding extends Bindings {
  @override
  void dependencies() {
    // Register API service
    Get.lazyPut<ReputationApiService>(() => ReputationApiService());

    // Register repository
    Get.lazyPut<ReputationRepository>(
      () => ReputationRepositoryImpl(Get.find<ReputationApiService>()),
    );

    // Register controller
    Get.lazyPut<ReputationController>(
      () => ReputationController(Get.find<ReputationRepository>()),
    );
  }
}
