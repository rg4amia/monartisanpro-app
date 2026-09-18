import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/utils/error_handler.dart';
import '../../../data/models/artisan_stock_model.dart';
import '../../../data/repositories/artisan_stock_repository.dart';

/// Gère le stock personnel de l'artisan (matériaux/outils qu'il possède déjà,
/// distinct du catalogue d'une quincaillerie).
class StockController extends GetxController {
  final ArtisanStockRepository _repo = ArtisanStockRepository();

  final stockItems = <ArtisanStockModel>[].obs;
  final isLoading = false.obs;
  final hasError = false.obs;
  final isSaving = false.obs;

  @override
  void onInit() {
    super.onInit();
    loadStock();
  }

  Future<void> loadStock() async {
    isLoading.value = true;
    hasError.value = false;
    try {
      stockItems.value = await _repo.getStock();
    } catch (e) {
      hasError.value = true;
      _showError('Impossible de charger votre stock', e);
    } finally {
      isLoading.value = false;
    }
  }

  @override
  Future<void> refresh() => loadStock();

  /// Retourne `true` si l'article a bien été enregistré.
  Future<bool> saveItem({
    int? id,
    required String description,
    required int quantity,
    required int unitCost,
    required String condition,
  }) async {
    isSaving.value = true;
    try {
      if (id == null) {
        await _repo.createStockItem(
          description: description,
          quantity: quantity,
          unitCost: unitCost,
          condition: condition,
        );
      } else {
        await _repo.updateStockItem(
          id,
          description: description,
          quantity: quantity,
          unitCost: unitCost,
          condition: condition,
        );
      }
      await loadStock();
      Get.snackbar(
        id == null ? 'Article ajouté' : 'Article mis à jour',
        id == null
            ? 'L\'article a été ajouté à votre stock.'
            : 'L\'article a été mis à jour.',
        snackPosition: SnackPosition.TOP,
        backgroundColor: const Color(0xFF27AE60),
        colorText: Colors.white,
      );
      return true;
    } catch (e) {
      _showError('Impossible d\'enregistrer l\'article', e);
      return false;
    } finally {
      isSaving.value = false;
    }
  }

  Future<bool> deleteItem(ArtisanStockModel item) async {
    isSaving.value = true;
    try {
      await _repo.deleteStockItem(item.id);
      stockItems.removeWhere((e) => e.id == item.id);
      Get.snackbar(
        'Article supprimé',
        '${item.description} a été retiré de votre stock.',
        snackPosition: SnackPosition.TOP,
        backgroundColor: const Color(0xFF27AE60),
        colorText: Colors.white,
      );
      return true;
    } catch (e) {
      _showError('Impossible de supprimer l\'article', e);
      return false;
    } finally {
      isSaving.value = false;
    }
  }

  void _showError(String title, Object error) {
    Get.snackbar(
      title,
      ErrorHandler.getErrorMessage(error),
      snackPosition: SnackPosition.TOP,
      backgroundColor: const Color(0xFFE74C3C),
      colorText: Colors.white,
    );
  }
}
