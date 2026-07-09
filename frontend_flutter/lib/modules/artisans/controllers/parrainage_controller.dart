import 'package:dio/dio.dart';
import 'package:get/get.dart';
import '../../../data/repositories/parrainage_repository.dart';

class ParrainageController extends GetxController {
  final ParrainageRepository _repo = ParrainageRepository();

  final filleuls = <Map<String, dynamic>>[].obs;
  final isLoading = false.obs;
  final isSubmitting = false.obs;
  final errorMsg = RxnString();

  @override
  void onInit() {
    super.onInit();
    loadFilleuls();
  }

  Future<void> loadFilleuls() async {
    isLoading.value = true;
    errorMsg.value = null;
    try {
      final data = await _repo.getFilleuls();
      filleuls.value = data;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);
    } catch (e) {
      errorMsg.value = 'Erreur lors du chargement des filleuls';
    } finally {
      isLoading.value = false;
    }
  }

  Future<bool> addFilleul(String phone) async {
    isSubmitting.value = true;
    errorMsg.value = null;
    try {
      await _repo.addFilleul(phone);
      Get.snackbar('Succès', 'Filleul ajouté avec succès !');
      await loadFilleuls();
      return true;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);
      Get.snackbar('Erreur', errorMsg.value ?? 'Impossible d\'ajouter le filleul',
          backgroundColor: Get.theme.colorScheme.error.withValues(alpha: 0.9),
          colorText: Get.theme.colorScheme.onError);
      return false;
    } catch (e) {
      errorMsg.value = 'Une erreur est survenue';
      return false;
    } finally {
      isSubmitting.value = false;
    }
  }

  String _handleDioError(DioException e) {
    if (e.response != null) {
      final data = e.response!.data;
      if (data is Map) {
        if (data.containsKey('errors')) {
          final errors = data['errors'] as Map;
          if (errors.isNotEmpty) {
            final firstError = errors.values.first;
            if (firstError is List && firstError.isNotEmpty) {
              return firstError.first.toString();
            }
          }
        }
        if (data.containsKey('message')) {
          return data['message'] as String;
        }
      }
      return 'Erreur ${e.response?.statusCode}';
    }
    return 'Erreur réseau';
  }
}
