import 'package:get/get.dart';

import '../../../data/models/jalon_model.dart';
import '../../../data/models/mission_model.dart';
import '../../../data/repositories/mission_repository.dart';

class MissionsController extends GetxController {
  final MissionRepository _repo = MissionRepository();

  final missions = <MissionModel>[].obs;
  final currentMission = Rx<MissionModel?>(null);
  final jalons = <JalonModel>[].obs;
  final isLoading = false.obs;
  final selectedFilter = 'all'.obs;
  final errorMsg = Rx<String?>(null);

  // Estimate response from Gemini AI
  final estimateResult = Rx<Map<String, dynamic>?>(null);

  @override
  void onInit() {
    super.onInit();
    loadMissions();
  }

  Future<void> loadMissions({String? status}) async {
    isLoading.value = true;
    try {
      missions.value = await _repo.getMissions(
        status: status == 'all' ? null : status,
      );
    } catch (e) {
      errorMsg.value = e.toString();
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> loadMission(int id) async {
    isLoading.value = true;
    try {
      currentMission.value = await _repo.getMission(id);
      jalons.value = await _repo.getJalons(id);
    } catch (e) {
      errorMsg.value = e.toString();
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> estimate(String description, String category) async {
    isLoading.value = true;
    try {
      estimateResult.value =
          await _repo.estimate(description: description, category: category);
    } catch (_) {
      estimateResult.value = null;
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> submitJalon(int jalonId) async {
    await _repo.submitJalon(jalonId);
    if (currentMission.value != null) {
      await loadMission(currentMission.value!.id);
    }
  }

  Future<void> requestOtp(int jalonId) async {
    await _repo.requestOtp(jalonId);
    Get.snackbar('OTP envoyé', 'Code envoyé par SMS au client.',
        snackPosition: SnackPosition.TOP);
  }

  Future<void> validateOtp(int jalonId, String otp) async {
    await _repo.validateOtp(jalonId, otp);
    if (currentMission.value != null) {
      await loadMission(currentMission.value!.id);
    }
    Get.snackbar('Jalon validé', 'Paiement libéré avec succès.',
        snackPosition: SnackPosition.TOP);
  }

  void applyFilter(String filter) {
    selectedFilter.value = filter;
    loadMissions(status: filter);
  }

  List<MissionModel> get filteredMissions => missions;
}
