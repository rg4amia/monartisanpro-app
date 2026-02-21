import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/controllers/project_controller.dart';
import '../../../../shared/controllers/auth_controller.dart';
import '../../../../shared/models/project_model.dart';
import 'create_project_screen.dart';
import 'project_details_screen.dart';

class ProjectListScreen extends StatefulWidget {
  const ProjectListScreen({super.key});

  @override
  State<ProjectListScreen> createState() => _ProjectListScreenState();
}

class _ProjectListScreenState extends State<ProjectListScreen>
    with SingleTickerProviderStateMixin {
  final _projectController = Get.find<ProjectController>();
  final _authController = Get.find<AuthController>();

  late TabController _tabController;

  // Tab index → list of statuses (empty = tous)
  static const _tabStatuses = [
    <String>[],
    ['pending', 'awaiting_quotes', 'quoted', 'payment_pending'],
    ['in_progress'],
    ['completed', 'cancelled'],
  ];

  static const _tabLabels = ['Tous', 'En attente', 'En cours', 'Terminés'];

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 4, vsync: this);
    _tabController.addListener(() => setState(() {}));
    _loadProjects();
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _loadProjects() async {
    await _projectController.fetchMyProjects();
  }

  List<Project> _filteredProjects(List<Project> all) {
    final statuses = _tabStatuses[_tabController.index];
    if (statuses.isEmpty) return all;
    return all.where((p) => statuses.contains(p.status)).toList();
  }

  int _countForTab(int tabIndex, List<Project> all) {
    final statuses = _tabStatuses[tabIndex];
    if (statuses.isEmpty) return all.length;
    return all.where((p) => statuses.contains(p.status)).length;
  }

  @override
  Widget build(BuildContext context) {
    final user = _authController.currentUser.value;
    final isClient = user?.role == 'client';

    return Scaffold(
      appBar: AppBar(
        title: const Text('Mes projets'),
        bottom: Obx(() {
          final all = _projectController.myProjects.toList();
          return TabBar(
            controller: _tabController,
            isScrollable: false,
            labelStyle: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600),
            unselectedLabelStyle: const TextStyle(fontSize: 12),
            tabs: List.generate(4, (i) {
              final count = _countForTab(i, all);
              final badgeColor = switch (i) {
                1 => AppColors.warning,
                2 => AppColors.lightAccentSecondary,
                3 => AppColors.lightTextSecondary,
                _ => Theme.of(context).colorScheme.primary,
              };
              return Tab(
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(_tabLabels[i]),
                    if (count > 0 && i > 0) ...[
                      const SizedBox(width: 4),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 5,
                          vertical: 1,
                        ),
                        decoration: BoxDecoration(
                          color: badgeColor,
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Text(
                          '$count',
                          style: const TextStyle(
                            fontSize: 10,
                            color: Colors.white,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
              );
            }),
          );
        }),
      ),
      body: Obx(() {
        if (_projectController.isLoading.value) {
          return const Center(child: CircularProgressIndicator());
        }

        final allProjects = _projectController.myProjects.toList();
        final filtered = _filteredProjects(allProjects);

        if (filtered.isEmpty) {
          return _buildEmptyState(context, isClient);
        }

        return RefreshIndicator(
          onRefresh: _loadProjects,
          child: ListView.separated(
            padding: const EdgeInsets.all(Spacing.screenPadding),
            itemCount: filtered.length,
            separatorBuilder: (_, __) => const SizedBox(height: Spacing.md),
            itemBuilder: (context, index) {
              final project = filtered[index];
              return _ProjectCard(
                project: project,
                onTap: () async {
                  await Get.to(
                    () => ProjectDetailsScreen(projectId: project.id),
                  );
                  _loadProjects();
                },
              );
            },
          ),
        );
      }),
      floatingActionButton: isClient &&
              (_tabController.index == 0 || _tabController.index == 1)
          ? FloatingActionButton.extended(
              onPressed: () async {
                final result = await Get.to(() => const CreateProjectScreen());
                if (result == true) _loadProjects();
              },
              icon: const Icon(Icons.add),
              label: const Text('Nouveau projet'),
            )
          : null,
    );
  }

  Widget _buildEmptyState(BuildContext context, bool isClient) {
    final tabIndex = _tabController.index;
    final String message;
    final IconData icon;

    switch (tabIndex) {
      case 1:
        icon = Icons.hourglass_empty_outlined;
        message = isClient
            ? 'Aucun projet en attente.\nCréez votre premier projet !'
            : 'Aucun projet en attente de votre intervention.';
        break;
      case 2:
        icon = Icons.work_outline;
        message =
            isClient ? 'Aucun projet en cours.' : 'Aucun chantier en cours.';
        break;
      case 3:
        icon = Icons.check_circle_outline;
        message = 'Aucun projet terminé pour le moment.';
        break;
      default:
        icon = Icons.work_off_outlined;
        message = isClient
            ? 'Vous n\'avez pas encore de projet.'
            : 'Aucun projet pour le moment.';
    }

    return Center(
      child: Padding(
        padding: const EdgeInsets.all(Spacing.xxl),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, size: 72, color: AppColors.lightTextTertiary),
            const SizedBox(height: Spacing.lg),
            Text(
              message,
              style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                    color: AppColors.lightTextSecondary,
                  ),
              textAlign: TextAlign.center,
            ),
            if (isClient && tabIndex == 0) ...[
              const SizedBox(height: Spacing.xl),
              ElevatedButton.icon(
                onPressed: () async {
                  final result =
                      await Get.to(() => const CreateProjectScreen());
                  if (result == true) _loadProjects();
                },
                icon: const Icon(Icons.add),
                label: const Text('Créer un projet'),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

// ---------------------------------------------------------------------------
// Project Card
// ---------------------------------------------------------------------------

class _ProjectCard extends StatelessWidget {
  final Project project;
  final VoidCallback onTap;

  const _ProjectCard({required this.project, required this.onTap});

  Color _statusColor(String status) => switch (status) {
        'pending' || 'awaiting_quotes' => AppColors.warning,
        'quoted' || 'payment_pending' => AppColors.info,
        'in_progress' => AppColors.lightAccentSecondary,
        'completed' => AppColors.success,
        'cancelled' => AppColors.error,
        _ => AppColors.lightTextSecondary,
      };

  String _formatDate(String dateString) {
    try {
      final date = DateTime.parse(dateString);
      final diff = DateTime.now().difference(date);
      if (diff.inDays == 0) return "Aujourd'hui";
      if (diff.inDays == 1) return 'Hier';
      if (diff.inDays < 7) return 'Il y a ${diff.inDays} jours';
      return '${date.day}/${date.month}/${date.year}';
    } catch (_) {
      return dateString;
    }
  }

  String _formatBudget() {
    final min = project.budgetMin;
    final max = project.budgetMax;
    if (min == null && max == null) return 'Budget libre';
    if (min != null && max != null) {
      return '${min.toInt().toString().replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+$)'), (m) => '${m[1]} ')} – ${max.toInt().toString().replaceAllMapped(RegExp(r'(\d{1,3})(?=(\d{3})+$)'), (m) => '${m[1]} ')} FCFA';
    }
    if (max != null) return 'Max ${max.toInt()} FCFA';
    return 'À partir de ${min!.toInt()} FCFA';
  }

  @override
  Widget build(BuildContext context) {
    final statusColor = _statusColor(project.status);
    final hasArtisan = project.artisan != null;

    return Card(
      clipBehavior: Clip.antiAlias,
      elevation: 2,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(Spacing.base),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // ── Header: titre + statut ──────────────────────────────────
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Text(
                      project.title,
                      style:
                          Theme.of(context).textTheme.titleMedium?.copyWith(
                                fontWeight: FontWeight.bold,
                              ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  const SizedBox(width: Spacing.sm),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: Spacing.sm,
                      vertical: 3,
                    ),
                    decoration: BoxDecoration(
                      color: statusColor.withValues(alpha: 0.12),
                      borderRadius: BorderRadius.circular(Spacing.radiusSm),
                      border: Border.all(
                        color: statusColor.withValues(alpha: 0.4),
                      ),
                    ),
                    child: Text(
                      project.statusLabel,
                      style: TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w600,
                        color: statusColor,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: Spacing.xs),

              // ── Métier (chip) ──────────────────────────────────────────
              Row(
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: Spacing.sm,
                      vertical: 2,
                    ),
                    decoration: BoxDecoration(
                      color: AppColors.lightAccentPrimary.withValues(alpha: 0.08),
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: Text(
                      project.trade.name,
                      style: TextStyle(
                        fontSize: 11,
                        color: AppColors.lightAccentPrimary,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: Spacing.sm),

              // ── Description ─────────────────────────────────────────────
              Text(
                project.description,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: AppColors.lightTextSecondary,
                    ),
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              const SizedBox(height: Spacing.sm),

              // ── Budget ──────────────────────────────────────────────────
              Row(
                children: [
                  Icon(
                    Icons.account_balance_wallet_outlined,
                    size: 14,
                    color: AppColors.lightTextSecondary,
                  ),
                  const SizedBox(width: 4),
                  Text(
                    _formatBudget(),
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: AppColors.lightTextSecondary,
                          fontWeight: FontWeight.w500,
                        ),
                  ),
                ],
              ),
              const SizedBox(height: Spacing.md),

              // ── Footer ──────────────────────────────────────────────────
              Row(
                children: [
                  // Artisan ou nb de devis
                  Expanded(
                    child: hasArtisan
                        ? Row(
                            children: [
                              Icon(
                                Icons.person_outline,
                                size: 14,
                                color: AppColors.lightAccentSecondary,
                              ),
                              const SizedBox(width: 4),
                              Flexible(
                                child: Text(
                                  project.artisan!.name,
                                  style: Theme.of(context)
                                      .textTheme
                                      .bodySmall
                                      ?.copyWith(
                                        color: AppColors.lightAccentSecondary,
                                        fontWeight: FontWeight.w600,
                                      ),
                                  maxLines: 1,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                            ],
                          )
                        : Row(
                            children: [
                              Icon(
                                Icons.description_outlined,
                                size: 14,
                                color: project.quoteCount > 0
                                    ? Theme.of(context).colorScheme.primary
                                    : AppColors.lightTextTertiary,
                              ),
                              const SizedBox(width: 4),
                              Text(
                                project.quoteCount > 0
                                    ? '${project.quoteCount} devis reçu(s)'
                                    : 'Aucun devis',
                                style: Theme.of(context)
                                    .textTheme
                                    .bodySmall
                                    ?.copyWith(
                                      color: project.quoteCount > 0
                                          ? Theme.of(context)
                                              .colorScheme
                                              .primary
                                          : AppColors.lightTextTertiary,
                                      fontWeight: project.quoteCount > 0
                                          ? FontWeight.w600
                                          : FontWeight.normal,
                                    ),
                              ),
                            ],
                          ),
                  ),

                  // Localisation
                  Icon(
                    Icons.location_on_outlined,
                    size: 13,
                    color: AppColors.lightTextTertiary,
                  ),
                  const SizedBox(width: 2),
                  Flexible(
                    child: Text(
                      project.address,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: AppColors.lightTextTertiary,
                          ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  const SizedBox(width: Spacing.sm),

                  // Date
                  Text(
                    _formatDate(project.createdAt),
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: AppColors.lightTextTertiary,
                        ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}
