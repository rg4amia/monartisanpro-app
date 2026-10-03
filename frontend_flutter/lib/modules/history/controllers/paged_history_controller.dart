import 'package:get/get.dart';

import '../../../core/utils/error_handler.dart';
import '../../../data/models/history_models.dart';

/// Charge une page d'un historique pour le filtre courant (`null` = tout).
typedef HistoryFetcher<T> = Future<HistoryPage<T>> Function(
  String? filter,
  int page,
);

/// Historique chargé page par page, avec un filtre facultatif.
///
/// Un échec est annoncé par [errorMsg] : la liste déjà affichée est conservée
/// et n'est jamais remplacée par une liste vide.
class PagedHistoryController<T> extends GetxController {
  PagedHistoryController(this._fetch, {String? initialFilter})
      : filter = RxnString(initialFilter);

  final HistoryFetcher<T> _fetch;

  final items = <T>[].obs;
  final isLoading = false.obs;
  final isLoadingMore = false.obs;
  final errorMsg = RxnString();
  final hasMore = false.obs;
  final RxnString filter;

  int _page = 1;

  /// Numéro de la demande en cours : la réponse d'un filtre abandonné est ignorée.
  int _request = 0;

  @override
  void onInit() {
    super.onInit();
    load();
  }

  Future<void> load() async {
    final request = ++_request;
    isLoading.value = true;
    errorMsg.value = null;

    try {
      final page = await _fetch(filter.value, 1);
      if (request != _request) return;

      _page = 1;
      items.assignAll(page.items);
      hasMore.value = page.hasMore;
    } catch (e) {
      if (request != _request) return;
      errorMsg.value = ErrorHandler.getErrorMessage(e);
    } finally {
      if (request == _request) isLoading.value = false;
    }
  }

  Future<void> loadMore() async {
    if (!hasMore.value || isLoadingMore.value || isLoading.value) return;

    final request = _request;
    isLoadingMore.value = true;
    errorMsg.value = null;

    try {
      final page = await _fetch(filter.value, _page + 1);
      if (request != _request) return;

      _page += 1;
      items.addAll(page.items);
      hasMore.value = page.hasMore;
    } catch (e) {
      if (request != _request) return;
      errorMsg.value = ErrorHandler.getErrorMessage(e);
    } finally {
      isLoadingMore.value = false;
    }
  }

  Future<void> setFilter(String? value) async {
    if (filter.value == value) return;

    filter.value = value;
    items.clear();
    hasMore.value = false;
    await load();
  }
}
