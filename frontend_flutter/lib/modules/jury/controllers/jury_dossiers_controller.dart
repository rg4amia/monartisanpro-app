import 'package:get/get.dart';

import '../../../core/utils/error_handler.dart';
import '../../../data/models/jury_dossier_model.dart';
import '../../../data/repositories/jury_repository.dart';

/// Liste des dossiers d'arbitrage assignés à l'artisan juré (Chantier 12).
class JuryDossiersController extends GetxController {
  JuryDossiersController({JuryRepository? repository})
      : _repo = repository ?? JuryRepository();

  final JuryRepository _repo;

  final isLoading = true.obs;
  final errorMsg = RxnString();
  final dossiers = <JuryDossierSummary>[].obs;

  /// Dossiers en attente de l'avis du juré, en tête de liste.
  List<JuryDossierSummary> get openDossiers =>
      dossiers.where((d) => d.isOpen).toList(growable: false);

  List<JuryDossierSummary> get closedDossiers =>
      dossiers.where((d) => !d.isOpen).toList(growable: false);

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load() async {
    isLoading.value = true;
    errorMsg.value = null;
    try {
      dossiers.value = await _repo.getDossiers();
    } catch (e) {
      // Une panne ne se déguise pas en « aucun dossier » (Règle d'or 75).
      errorMsg.value = ErrorHandler.getErrorMessage(e);
    } finally {
      isLoading.value = false;
    }
  }
}
