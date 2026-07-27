import 'package:get/get.dart';
import '../../../data/models/artisan_model.dart';
import '../../../data/repositories/artisan_repository.dart';

class ArtisanController extends GetxController {
  final ArtisanRepository _repo = ArtisanRepository();
  final artisan = Rx<ArtisanModel?>(null);
  final score = Rx<Map<String, dynamic>?>(null);
  final isLoading = false.obs;
  final fromSelection = false.obs;

  @override
  void onInit() {
    super.onInit();
    final arg = Get.arguments;
    if (arg is ArtisanModel) {
      artisan.value = arg;
      _loadScore(arg.id);
    } else if (arg is Map<String, dynamic>) {
      artisan.value = arg['artisan'] as ArtisanModel?;
      fromSelection.value = arg['fromSelection'] as bool? ?? false;
      if (artisan.value != null) {
        _loadScore(artisan.value!.id);
      }
    } else if (arg is int) {
      _loadArtisan(arg);
    }
  }

  Future<void> _loadArtisan(int id) async {
    isLoading.value = true;
    try {
      artisan.value = await _repo.getArtisan(id);
      await _loadScore(id);
    } finally {
      isLoading.value = false;
    }
  }

  Future<void> _loadScore(int id) async {
    try {
      score.value = await _repo.getScore(id);
    } catch (_) {}
  }
}
