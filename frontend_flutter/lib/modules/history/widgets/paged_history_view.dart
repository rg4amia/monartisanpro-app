import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../controllers/paged_history_controller.dart';

/// Un filtre proposé en tête d'un historique. [value] `null` = tout.
class HistoryFilter {
  const HistoryFilter(this.value, this.label);

  final String? value;
  final String label;
}

/// Écran d'un historique paginé : filtres, liste, « Voir plus », panne
/// annoncée avec « Réessayer », mention explicite quand il n'y a rien.
///
/// L'écran possède son contrôleur : il peut servir d'onglet comme de page.
class PagedHistoryView<T> extends StatefulWidget {
  const PagedHistoryView({
    required this.title,
    required this.fetch,
    required this.itemBuilder,
    required this.emptyTitle,
    required this.emptyMessage,
    this.filters = const [],
    this.initialFilter,
    super.key,
  });

  final String title;
  final HistoryFetcher<T> fetch;
  final Widget Function(BuildContext context, T item) itemBuilder;
  final String emptyTitle;
  final String emptyMessage;
  final List<HistoryFilter> filters;
  final String? initialFilter;

  @override
  State<PagedHistoryView<T>> createState() => _PagedHistoryViewState<T>();
}

class _PagedHistoryViewState<T> extends State<PagedHistoryView<T>> {
  late final PagedHistoryController<T> controller;

  @override
  void initState() {
    super.initState();
    controller = PagedHistoryController<T>(
      widget.fetch,
      initialFilter: widget.initialFilter,
    )..onInit();
  }

  @override
  void dispose() {
    controller.onClose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(widget.title),
        backgroundColor: AppColors.surface,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
      ),
      body: Column(
        children: [
          if (widget.filters.isNotEmpty)
            SizedBox(
              height: 56,
              child: Obx(
                () => ListView(
                  scrollDirection: Axis.horizontal,
                  padding:
                      const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
                  children: [
                    for (final filter in widget.filters)
                      Padding(
                        padding: const EdgeInsets.only(right: 8),
                        child: ChoiceChip(
                          label: Text(filter.label),
                          selected: controller.filter.value == filter.value,
                          onSelected: (_) => controller.setFilter(filter.value),
                        ),
                      ),
                  ],
                ),
              ),
            ),
          Expanded(
            child: Obx(() {
              final items = controller.items;
              final error = controller.errorMsg.value;

              if (controller.isLoading.value && items.isEmpty) {
                return const Center(child: CircularProgressIndicator());
              }

              return RefreshIndicator(
                onRefresh: controller.load,
                child: ListView(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 32),
                  children: [
                    if (error != null)
                      _ErrorBanner(message: error, onRetry: controller.load),
                    if (items.isEmpty && error == null)
                      _EmptyState(
                        title: widget.emptyTitle,
                        message: widget.emptyMessage,
                      ),
                    for (final item in items)
                      Padding(
                        padding: const EdgeInsets.only(bottom: 12),
                        child: widget.itemBuilder(context, item),
                      ),
                    if (controller.hasMore.value)
                      Padding(
                        padding: const EdgeInsets.only(top: 4),
                        child: controller.isLoadingMore.value
                            ? const Center(child: CircularProgressIndicator())
                            : OutlinedButton(
                                onPressed: controller.loadMore,
                                child: const Text('Voir plus'),
                              ),
                      ),
                  ],
                ),
              );
            }),
          ),
        ],
      ),
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.dangerSoft,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        children: [
          const Icon(Icons.error_outline, color: AppColors.danger, size: 18),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              message,
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: AppColors.danger,
              ),
            ),
          ),
          TextButton(onPressed: onRetry, child: const Text('Réessayer')),
        ],
      ),
    );
  }
}

class _EmptyState extends StatelessWidget {
  const _EmptyState({required this.title, required this.message});

  final String title;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(top: 72, bottom: 24),
      child: Column(
        children: [
          Icon(
            Icons.history_rounded,
            size: 48,
            color: AppColors.textSecondary.withValues(alpha: 0.5),
          ),
          const SizedBox(height: 12),
          Text(
            title,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w700,
              color: AppColors.textPrimary,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            message,
            textAlign: TextAlign.center,
            style:
                const TextStyle(fontSize: 13, color: AppColors.textSecondary),
          ),
        ],
      ),
    );
  }
}

/// Carte commune aux lignes d'un historique.
class HistoryCard extends StatelessWidget {
  const HistoryCard({
    required this.title,
    required this.lines,
    this.badge,
    this.badgeColor,
    this.trailing,
    this.onTap,
    super.key,
  });

  final String title;
  final List<String> lines;
  final String? badge;
  final Color? badgeColor;
  final String? trailing;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final color = badgeColor ?? AppColors.primary;

    return Material(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(18),
      child: InkWell(
        borderRadius: BorderRadius.circular(18),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      title,
                      style: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w800,
                        color: AppColors.textPrimary,
                      ),
                    ),
                  ),
                  if (badge != null)
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 4,
                      ),
                      decoration: BoxDecoration(
                        color: color.withValues(alpha: 0.12),
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: Text(
                        badge!,
                        style: TextStyle(
                          fontSize: 11,
                          fontWeight: FontWeight.w800,
                          color: color,
                        ),
                      ),
                    ),
                ],
              ),
              for (final line in lines.where((l) => l.isNotEmpty))
                Padding(
                  padding: const EdgeInsets.only(top: 6),
                  child: Text(
                    line,
                    style: const TextStyle(
                      fontSize: 13,
                      color: AppColors.textSecondary,
                    ),
                  ),
                ),
              if (trailing != null)
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Text(
                    trailing!,
                    style: const TextStyle(
                      fontSize: 13.5,
                      fontWeight: FontWeight.w700,
                      color: AppColors.textPrimary,
                    ),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}
