import 'package:get/get.dart';
import '../../../data/repositories/artisan_repository.dart';
import '../../../core/storage/storage_service.dart';

class ScoreController extends GetxController {
  final ArtisanRepository _repo = ArtisanRepository();

  final score = 0.obs;
  final fiabilite = 0.0.obs;
  final integrite = 0.0.obs;
  final qualite = 0.0.obs;
  final reactivite = 0.0.obs;
  final isLoading = false.obs;
  final hasAccesMicrocredit = false.obs;

  @override
  void onInit() {
    super.onInit();
    final userId = StorageService.getUserId();
    if (userId != null) loadScore(userId);
  }

  Future<void> loadScore(int artisanId) async {
    isLoading.value = true;
    try {
      final data = await _repo.getScore(artisanId);
      score.value = (data['scoreNzassa'] as num?)?.toInt() ?? 0;
      fiabilite.value = (data['fiabilite'] as num?)?.toDouble() ?? 0;
      integrite.value = (data['integrite'] as num?)?.toDouble() ?? 0;
      qualite.value = (data['qualite'] as num?)?.toDouble() ?? 0;
      reactivite.value = (data['reactivite'] as num?)?.toDouble() ?? 0;
      hasAccesMicrocredit.value = score.value > 70;
    } finally {
      isLoading.value = false;
    }
  }
}
