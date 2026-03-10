import 'package:get/get.dart' hide FormData, MultipartFile;
import 'package:dio/dio.dart';

import '../../../core/network/api_client.dart';
import '../../../core/storage/storage_service.dart';
import '../../../core/utils/app_logger.dart';
import '../../../app/routes/app_routes.dart';

/// Exemple d'utilisation du logger dans un controller
class AuthControllerExample extends GetxController {
  final ApiClient _apiClient = ApiClient();
  final RxBool isLoading = false.obs;

  /// Exemple de login avec logging
  Future<void> login(String phone, String password) async {
    try {
      isLoading.value = true;
      
      // Log l'action utilisateur
      await AppLogger.userAction(
        'login_attempt',
        data: {'phone': phone},
      );

      final response = await _apiClient.post(
        '/auth/login',
        data: {
          'phone': phone,
          'password': password,
        },
      );

      final token = response.data['token'];
      final user = response.data['user'];

      await StorageService.saveToken(token);
      StorageService.saveRole(user['role']);
      StorageService.saveUserId(user['id']);

      // Log le succès
      await AppLogger.event(
        'login_success',
        data: {
          'user_id': user['id'],
          'role': user['role'],
        },
        context: 'Auth',
      );

      AppLogger.success('Login réussi');
      Get.offAllNamed(Routes.mainTab);
      
    } on DioException catch (e) {
      // L'erreur HTTP est déjà loggée par l'interceptor
      // On affiche juste le message à l'utilisateur
      AppLogger.debug('Login failed: ${e.response?.data}');
      
      Get.snackbar(
        'Erreur',
        'Identifiants incorrects',
        snackPosition: SnackPosition.BOTTOM,
      );
      
    } catch (e, stack) {
      // Erreur inattendue
      await AppLogger.error(
        'Erreur inattendue lors du login',
        error: e,
        stackTrace: stack,
        context: 'AuthController.login',
      );
      
    } finally {
      isLoading.value = false;
    }
  }

  /// Exemple de soumission KYC avec logging
  Future<void> submitKyc({
    required String cniPath,
    required String selfiePath,
  }) async {
    try {
      isLoading.value = true;

      await AppLogger.kyc(
        'submission_started',
        data: {
          'user_id': StorageService.getUserId(),
        },
      );

      final formData = FormData.fromMap({
        'cni_photo': await MultipartFile.fromFile(cniPath),
        'selfie_photo': await MultipartFile.fromFile(selfiePath),
      });

      final response = await _apiClient.postMultipart(
        '/kyc/submit',
        formData,
      );

      await AppLogger.kyc(
        'submitted',
        status: 'en_attente',
        data: {
          'user_id': StorageService.getUserId(),
          'kyc_id': response.data['kyc_id'],
        },
      );

      Get.snackbar(
        'Succès',
        'Documents KYC soumis avec succès',
        snackPosition: SnackPosition.BOTTOM,
      );

      Get.offAllNamed(Routes.mainTab);
      
    } on DioException catch (e) {
      await AppLogger.kyc(
        'submission_failed',
        status: 'error',
        data: {
          'error': e.response?.data,
        },
      );
      
    } catch (e, stack) {
      await AppLogger.error(
        'Erreur lors de la soumission KYC',
        error: e,
        stackTrace: stack,
        context: 'AuthController.submitKyc',
      );
      
    } finally {
      isLoading.value = false;
    }
  }

  /// Exemple de gestion de permission
  Future<void> requestLocationPermission() async {
    try {
      // Simuler une demande de permission
      final granted = false; // await Permission.location.request().isGranted;

      if (!granted) {
        await AppLogger.permissionDenied(
          'location',
          context: 'AuthController',
        );
        
        Get.snackbar(
          'Permission requise',
          'La localisation est nécessaire pour trouver des artisans',
          snackPosition: SnackPosition.BOTTOM,
        );
      }
      
    } catch (e, stack) {
      await AppLogger.error(
        'Erreur lors de la demande de permission',
        error: e,
        stackTrace: stack,
        context: 'AuthController.requestLocationPermission',
      );
    }
  }
}
