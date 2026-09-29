import 'package:get/get.dart';

import '../../../core/utils/error_handler.dart';
import '../../../data/models/artisan_availability_model.dart';
import '../../../data/repositories/artisan_availability_repository.dart';

/// « Ma disponibilité » (Chantier 15) : l'artisan déclare son statut, ses
/// jours et horaires et les interventions de nuit ; la déclaration n'apparaît
/// dans l'annuaire du site qu'après validation d'un administrateur.
class AvailabilityController extends GetxController {
  AvailabilityController({ArtisanAvailabilityRepository? repository})
      : _repo = repository ?? ArtisanAvailabilityRepository();

  final ArtisanAvailabilityRepository _repo;

  static const int maxSlots = 14;

  final isLoading = true.obs;
  final isSubmitting = false.obs;
  final errorMsg = RxnString();
  final submitError = RxnString();
  final submitSuccess = RxnString();
  final overview = Rxn<AvailabilityOverview>();

  // Formulaire
  final status = 'disponible'.obs;
  final untilDate = Rxn<DateTime>();
  final slots = <AvailabilitySlot>[].obs;
  final nightWork = false.obs;

  bool get needsUntilDate => status.value != 'disponible';

  bool get canSubmit =>
      !isSubmitting.value && (!needsUntilDate || untilDate.value != null);

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load() async {
    isLoading.value = true;
    errorMsg.value = null;
    try {
      final result = await _repo.getOverview();
      overview.value = result;
      _fillForm(result.pending ?? result.published);
    } catch (e) {
      // Une panne ne se déguise pas en « aucune disponibilité » (Règle d'or 75).
      errorMsg.value = ErrorHandler.getErrorMessage(e);
    } finally {
      isLoading.value = false;
    }
  }

  void setStatus(String value) {
    status.value = value;
    if (value == 'disponible') untilDate.value = null;
  }

  void addSlot() {
    if (slots.length >= maxSlots) return;
    slots.add(const AvailabilitySlot(day: 1, start: '08:00', end: '17:00'));
  }

  void updateSlot(int index, AvailabilitySlot slot) {
    if (index < 0 || index >= slots.length) return;
    slots[index] = slot;
  }

  void removeSlot(int index) {
    if (index < 0 || index >= slots.length) return;
    slots.removeAt(index);
  }

  Future<void> submit() async {
    if (!canSubmit) return;
    isSubmitting.value = true;
    submitError.value = null;
    submitSuccess.value = null;
    try {
      overview.value = await _repo.submit(
        status: status.value,
        untilDate: untilDate.value,
        slots: slots.toList(),
        nightWork: nightWork.value,
      );
      submitSuccess.value =
          'Disponibilité envoyée : elle sera publiée dans l\'annuaire après validation.';
    } catch (e) {
      submitError.value = ErrorHandler.getErrorMessage(e);
    } finally {
      isSubmitting.value = false;
    }
  }

  void _fillForm(ArtisanAvailability? source) {
    if (source == null) return;
    status.value = source.status;
    untilDate.value = source.status == 'disponible' ? null : source.untilDate;
    slots.assignAll(source.slots);
    nightWork.value = source.nightWork;
  }
}
