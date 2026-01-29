import 'package:get/get.dart';
import '../../../../core/services/api/api_client.dart';
import '../../data/datasources/auth_remote_datasource.dart';
import '../../data/repositories/auth_repository_impl.dart';
import '../../domain/repositories/auth_repository.dart';
import '../../domain/usecases/login_usecase.dart';
import '../../domain/usecases/register_usecase.dart';
import '../../domain/usecases/upload_kyc_usecase.dart';
import '../../domain/usecases/verify_otp_usecase.dart';
import '../controllers/auth_controller.dart';
import '../controllers/kyc_controller.dart';
import '../controllers/otp_controller.dart';

/// Dependency injection binding for authentication feature
class AuthBinding extends Bindings {
  @override
  void dependencies() {
    // Data sources (ApiClient and ApiService are already registered globally)
    Get.lazyPut<AuthRemoteDataSource>(
      () => AuthRemoteDataSource(Get.find<ApiClient>()),
    );

    // Repositories
    Get.lazyPut<AuthRepository>(
      () => AuthRepositoryImpl(
        Get.find<AuthRemoteDataSource>(),
        Get.find<ApiClient>(),
      ),
    );

    // Use cases
    Get.lazyPut<LoginUseCase>(() => LoginUseCase(Get.find<AuthRepository>()));

    Get.lazyPut<RegisterUseCase>(
      () => RegisterUseCase(Get.find<AuthRepository>()),
    );

    Get.lazyPut<VerifyOtpUseCase>(
      () => VerifyOtpUseCase(Get.find<AuthRepository>()),
    );

    Get.lazyPut<UploadKycUseCase>(
      () => UploadKycUseCase(Get.find<AuthRepository>()),
    );

    // Controllers
    Get.lazyPut<AuthController>(
      () => AuthController(
        Get.find<LoginUseCase>(),
        Get.find<RegisterUseCase>(),
        Get.find<AuthRepository>(),
      ),
    );

    Get.lazyPut<OtpController>(
      () => OtpController(
        Get.find<VerifyOtpUseCase>(),
        Get.find<AuthRepository>(),
      ),
    );

    Get.lazyPut<KycController>(
      () => KycController(Get.find<UploadKycUseCase>()),
    );
  }
}
