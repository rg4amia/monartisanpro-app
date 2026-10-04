import 'package:get/get.dart';

import '../../../core/utils/error_handler.dart';
import '../../../data/models/notification_model.dart';
import '../../../data/repositories/notification_repository.dart';

/// Préférences de notification, lues et enregistrées sur le serveur.
///
/// Un interrupteur n'affiche que ce que le serveur a retenu : un
/// enregistrement en échec remet l'état précédent et l'annonce.
class NotificationPreferencesController extends GetxController {
  NotificationPreferencesController({NotificationRepository? repository})
      : _repo = repository ?? NotificationRepository();

  final NotificationRepository _repo;

  final promotionalPush = false.obs;
  final domains = <NotificationDomainPreference>[].obs;
  final isLoading = false.obs;
  final isSaving = false.obs;
  final isLoaded = false.obs;
  final errorMsg = RxnString();

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load() async {
    isLoading.value = true;
    errorMsg.value = null;

    try {
      _apply(await _repo.getPreferences());
      isLoaded.value = true;
    } catch (e) {
      errorMsg.value = ErrorHandler.getErrorMessage(e);
    } finally {
      isLoading.value = false;
    }
  }

  void _apply(NotificationPreferences preferences) {
    promotionalPush.value = preferences.promotionalPush;
    domains.assignAll(preferences.domains);
  }

  Future<void> setPromotionalPush(bool value) async {
    final previous = promotionalPush.value;
    promotionalPush.value = value;

    await _save(
      () => _repo.updatePreferences(promotionalPush: value),
      revert: () => promotionalPush.value = previous,
    );
  }

  Future<void> setDomainPush(String key, bool value) =>
      _setDomain(key, 'push', value, (d) => d.copyWith(push: value));

  Future<void> setDomainSms(String key, bool value) =>
      _setDomain(key, 'sms', value, (d) => d.copyWith(sms: value));

  Future<void> _setDomain(
    String key,
    String channel,
    bool value,
    NotificationDomainPreference Function(NotificationDomainPreference) change,
  ) async {
    final index = domains.indexWhere((d) => d.key == key);
    if (index == -1) return;

    final previous = domains[index];
    domains[index] = change(previous);

    await _save(
      () => _repo.updatePreferences(
        domains: {
          key: {channel: value},
        },
      ),
      revert: () {
        final current = domains.indexWhere((d) => d.key == key);
        if (current != -1) domains[current] = previous;
      },
    );
  }

  Future<void> _save(
    Future<NotificationPreferences> Function() request, {
    required void Function() revert,
  }) async {
    isSaving.value = true;
    errorMsg.value = null;

    try {
      _apply(await request());
    } catch (e) {
      revert();
      errorMsg.value = ErrorHandler.getErrorMessage(e);
    } finally {
      isSaving.value = false;
    }
  }
}
