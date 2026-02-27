import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../data/models/jcode_model.dart';
import '../../../data/repositories/jcode_repository.dart';
import '../../../app/routes/app_routes.dart';

class JcodeController extends GetxController {
  final JcodeRepository _repo = JcodeRepository();

  final activeJcode = Rx<JcodeModel?>(null);
  final scanResult = Rx<Map<String, dynamic>?>(null);
  final isLoading = false.obs;
  final isScanning = false.obs;

  @override
  void onInit() {
    super.onInit();
    loadActiveJcode();
  }

  Future<void> loadActiveJcode() async {
    isLoading.value = true;
    try {
      activeJcode.value = await _repo.getActiveJcode();
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> generateJcode(int missionId, int montant) async {
    isLoading.value = true;
    try {
      final jcode = await _repo.createJcode(
        missionId: missionId,
        montant: montant,
      );
      activeJcode.value = jcode;
      Get.snackbar(
        'J-Code généré',
        'Code : ${jcode.code}',
        snackPosition: SnackPosition.TOP,
      );
    } finally {
      isLoading.value = false;
    }
  }

  /// Fournisseur scanne le QR — le QR encode l'ID entier du J-Code
  Future<void> scanJcode(int jcodeId, double lat, double lng) async {
    isScanning.value = true;
    try {
      final result = await _repo.scanJcode(id: jcodeId, lat: lat, lng: lng);
      scanResult.value = result;
      Get.toNamed(Routes.transactionConfirm, arguments: result);
    } catch (e) {
      Get.snackbar(
        'Scan refusé',
        e.toString().replaceAll('Exception:', '').trim(),
        snackPosition: SnackPosition.TOP,
        backgroundColor: const Color(0xFFE74C3C),
        colorText: Colors.white,
      );
    } finally {
      isScanning.value = false;
    }
  }
}
