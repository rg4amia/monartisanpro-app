import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:geolocator/geolocator.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';

import '../../../data/repositories/mission_repository.dart';
import '../../../core/theme/app_colors.dart';

class ReferentValidationController extends GetxController {
  final MissionRepository _missionRepo = MissionRepository();

  final photos = <XFile>[].obs;
  final notesCtrl = TextEditingController();
  final isLoading = false.obs;
  final missionId = Get.arguments['missionId'] as int;

  @override
  void onClose() {
    notesCtrl.dispose();
    super.onClose();
  }

  Future<void> pickImage() async {
    final picker = ImagePicker();
    final image = await picker.pickImage(source: ImageSource.camera);
    if (image != null) photos.add(image);
  }

  Future<void> submit() async {
    if (photos.length < 2) {
      Get.snackbar(
        'Erreur',
        'Veuillez prendre au moins 2 photos du chantier.',
        snackPosition: SnackPosition.TOP,
      );
      return;
    }

    isLoading.value = true;
    try {
      final position = await _getCurrentPosition();

      await _missionRepo.validateReferentMission(
        missionId: missionId,
        latitude: position.latitude,
        longitude: position.longitude,
        photos: photos.toList(),
        notes: notesCtrl.text,
      );

      Get.back();
      Get.snackbar(
        'Validation enregistrée',
        'Mission validée. Les paiements en attente ont été libérés.',
        snackPosition: SnackPosition.TOP,
        backgroundColor: AppColors.success,
        colorText: Colors.white,
      );
    } on DioException catch (e) {
      Get.snackbar(
        'Validation refusée',
        _extractMessage(e),
        snackPosition: SnackPosition.TOP,
        backgroundColor: AppColors.danger,
        colorText: Colors.white,
      );
    } catch (e) {
      Get.snackbar(
        'Erreur',
        e.toString().replaceAll('Exception:', '').trim(),
        snackPosition: SnackPosition.TOP,
        backgroundColor: AppColors.danger,
        colorText: Colors.white,
      );
    } finally {
      isLoading.value = false;
    }
  }

  Future<Position> _getCurrentPosition() async {
    var permission = await Geolocator.checkPermission();
    if (permission == LocationPermission.denied) {
      permission = await Geolocator.requestPermission();
    }

    if (permission == LocationPermission.denied ||
        permission == LocationPermission.deniedForever) {
      throw Exception(
        'La position GPS est obligatoire pour valider physiquement une mission.',
      );
    }

    return Geolocator.getCurrentPosition(
      locationSettings: const LocationSettings(
        accuracy: LocationAccuracy.high,
      ),
    );
  }

  String _extractMessage(DioException e) {
    final data = e.response?.data;
    if (data is Map && data['message'] is String) {
      return data['message'] as String;
    }
    return 'Impossible de valider la mission.';
  }
}

class ReferentValidationScreen extends GetView<ReferentValidationController> {
  const ReferentValidationScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Validation Référent')),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Mission #${controller.missionId}',
              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 18),
            ),
            const SizedBox(height: 8),
            const Text(
              'En tant que référent, vous devez certifier physiquement que les travaux correspondant aux jalons ont été effectués.',
              style: TextStyle(color: AppColors.textMuted, fontSize: 14),
            ),
            const SizedBox(height: 32),
            const Text(
              'Photos du chantier (min. 2)',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
            ),
            const SizedBox(height: 12),
            Obx(
              () => Wrap(
                spacing: 12,
                runSpacing: 12,
                children: [
                  ...controller.photos.map(
                    (file) => Stack(
                      children: [
                        Container(
                          width: 100,
                          height: 100,
                          decoration: BoxDecoration(
                            borderRadius: BorderRadius.circular(8),
                            color: AppColors.shadowColor,
                            image: DecorationImage(
                              image: FileImage(File(file.path)),
                              fit: BoxFit.cover,
                            ),
                          ),
                        ),
                        Positioned(
                          right: 0,
                          child: IconButton(
                            icon: const Icon(Icons.cancel, color: Colors.red),
                            onPressed: () => controller.photos.remove(file),
                          ),
                        ),
                      ],
                    ),
                  ),
                  GestureDetector(
                    onTap: controller.pickImage,
                    child: Container(
                      width: 100,
                      height: 100,
                      decoration: BoxDecoration(
                        border: Border.all(color: AppColors.primary),
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: const Icon(
                        Icons.add_a_photo,
                        color: AppColors.primary,
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 32),
            const Text(
              'Notes d\'inspection',
              style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
            ),
            const SizedBox(height: 12),
            TextField(
              controller: controller.notesCtrl,
              maxLines: 4,
              decoration: const InputDecoration(
                hintText: 'Observations sur la qualité des travaux...',
                border: OutlineInputBorder(),
              ),
            ),
            const SizedBox(height: 48),
            Obx(
              () => SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed:
                      controller.isLoading.value ? null : controller.submit,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.primary,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 16),
                  ),
                  child: controller.isLoading.value
                      ? const CircularProgressIndicator(color: Colors.white)
                      : const Text('Confirmer la validation physique'),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
