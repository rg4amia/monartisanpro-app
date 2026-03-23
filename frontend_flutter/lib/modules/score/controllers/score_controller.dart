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
      final res = await _repo.getScore(artisanId);
      final data = (res['data'] as Map<String, dynamic>?) ?? res;
      final breakdown = data['breakdown'] is Map
          ? Map<String, dynamic>.from(data['breakdown'] as Map)
          : const <String, dynamic>{};

      score.value = _asInt(data['score_nzassa'] ?? data['scoreNzassa']);
      fiabilite.value = _normalizeCriterion(breakdown['fiabilite']);
      integrite.value = _normalizeCriterion(breakdown['integrite']);
      qualite.value = _normalizeCriterion(breakdown['qualite']);
      reactivite.value = _normalizeCriterion(breakdown['reactivite']);
      hasAccesMicrocredit.value =
          (data['micro_credit_eligible'] as bool?) ?? score.value >= 70;
    } finally {
      isLoading.value = false;
    }
  }

  int _asInt(dynamic value) {
    if (value == null) return 0;
    if (value is int) return value;
    if (value is double) return value.toInt();
    return int.tryParse(value.toString()) ?? 0;
  }

  double _normalizeCriterion(dynamic value) {
    double parsed;

    if (value is num) {
      parsed = value.toDouble();
    } else {
      parsed = double.tryParse(value?.toString() ?? '') ?? 0;
    }

    if (parsed <= 5) {
      return (parsed / 5 * 100).clamp(0, 100);
    }

    return parsed.clamp(0, 100);
  }
}
