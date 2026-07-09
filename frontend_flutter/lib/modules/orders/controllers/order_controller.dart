import 'package:dio/dio.dart';
import 'package:get/get.dart';
import '../../../data/repositories/order_repository.dart';

class OrderController extends GetxController {
  final OrderRepository _repo = OrderRepository();

  final isSubmitting = false.obs;
  final errorMsg = RxnString();

  Future<bool> createOrder({
    required int supplierId,
    required String deliveryMode,
    required List<Map<String, dynamic>> items,
    String? vehicleClass,
    double? surgeMultiplier,
  }) async {
    isSubmitting.value = true;
    errorMsg.value = null;

    try {
      await _repo.createOrder(
        supplierId: supplierId,
        deliveryMode: deliveryMode,
        items: items,
        vehicleClass: vehicleClass,
        surgeMultiplier: surgeMultiplier,
      );
      Get.snackbar('Succès', 'Commande e-commerce créée avec succès !');
      return true;
    } on DioException catch (e) {
      errorMsg.value = _handleDioError(e);
      Get.snackbar('Erreur', errorMsg.value ?? 'Impossible de créer la commande',
          backgroundColor: Get.theme.colorScheme.error.withValues(alpha: 0.9),
          colorText: Get.theme.colorScheme.onError);
      return false;
    } catch (e) {
      errorMsg.value = 'Erreur inattendue';
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
    return 'Erreur de connexion';
  }
}
