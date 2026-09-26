import 'package:get/get.dart';

import '../../../core/utils/error_handler.dart';
import '../../../data/models/jury_dossier_model.dart';
import '../../../data/repositories/jury_repository.dart';

/// Instruction d'un dossier anonymisé et vote motivé du juré (Chantier 12).
class JuryDossierDetailController extends GetxController {
  JuryDossierDetailController({JuryRepository? repository, int? litigeId})
      : _repo = repository ?? JuryRepository(),
        _litigeIdOverride = litigeId;

  final JuryRepository _repo;
  final int? _litigeIdOverride;

  late final int litigeId;

  final isLoading = true.obs;
  final isVoting = false.obs;
  final errorMsg = RxnString();
  final voteError = RxnString();
  final dossier = Rxn<JuryDossierDetail>();

  /// Vote en préparation.
  final verdict = RxnString();
  final splitArtisanPercentage = 50.obs;
  final technicalComment = ''.obs;

  @override
  void onInit() {
    super.onInit();
    litigeId = _litigeIdOverride ?? _litigeIdFromArguments(Get.arguments);
    load();
  }

  /// Accepte un id brut (notification push) ou `{'litigeId': …}`.
  static int _litigeIdFromArguments(dynamic arg) {
    if (arg is int) return arg;
    if (arg is Map) {
      final value = arg['litigeId'];
      if (value is int) return value;
      if (value is String) return int.tryParse(value) ?? 0;
    }
    return 0;
  }

  Future<void> load() async {
    isLoading.value = true;
    errorMsg.value = null;
    try {
      dossier.value = await _repo.getDossier(litigeId);
    } catch (e) {
      errorMsg.value = ErrorHandler.getErrorMessage(e);
    } finally {
      isLoading.value = false;
    }
  }

  /// Validation de confort ; le serveur reste seul juge.
  String? validateVote() {
    if (verdict.value == null) return 'Choisissez votre avis.';
    if (verdict.value == JuryVerdict.partage) {
      final split = splitArtisanPercentage.value;
      if (split < 0 || split > 100) {
        return 'La part de l\'artisan doit être comprise entre 0 et 100 %.';
      }
    }
    return null;
  }

  /// Envoie le vote ; renvoie le message du serveur en cas de succès,
  /// `null` sinon (l'erreur est alors dans [voteError]).
  Future<String?> submitVote() async {
    if (isVoting.value) return null;
    final invalid = validateVote();
    if (invalid != null) {
      voteError.value = invalid;
      return null;
    }

    isVoting.value = true;
    voteError.value = null;
    try {
      final message = await _repo.vote(
        litigeId,
        verdict: verdict.value!,
        splitArtisanPercentage: verdict.value == JuryVerdict.partage
            ? splitArtisanPercentage.value
            : null,
        technicalComment: technicalComment.value,
      );
      await load();

      return message;
    } catch (e) {
      voteError.value = ErrorHandler.getErrorMessage(e);
      return null;
    } finally {
      isVoting.value = false;
    }
  }
}
