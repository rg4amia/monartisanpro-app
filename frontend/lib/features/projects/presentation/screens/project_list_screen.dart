import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/controllers/project_controller.dart';
import '../../../../shared/controllers/auth_controller.dart';
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
  String _selectedFilter = 'all';

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 4, vsync: this);
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

  @override
  Widget build(BuildContext context) {
    final user = _authController.currentUser.value;
    final isClient = user?.role == 'client';

    return Scaffold(
      appBar: AppBar(
        title: const Text('Mes projets'),
        bottom: TabBar(
          controller: _tabController,
          tabs: const [
            Tab(text: 'Tous'),
            Tab(text: 'En attente'),
            Tab(text: 'En cours'),
            Tab(text: 'Terminés'),
          ],
          onTap: (index) {
            setState(() {
              switch (index) {
                case 0:
                  _selectedFilter = 'all';
                  break;
                case 1:
                  _selectedFilter = 'pending';
                  break;
                case 2:
                  _selectedFilter = 'in_progress';
                  break;
                case 3:
                  _selectedFilter = 'completed';
                  break;
              }
            });
          },
        ),
      ),
      body: Obx(() {
        if (_projectController.isLoading.value) {
          return const Center(child: CircularProgressIndicator());
        }

        final allProjects = _projectController.myProjects;
        final filteredProjects = _selectedFilter == 'all'
            ? allProjects
            : allProjects.where((p) => p.status == _selectedFilter).toList();

        if (filteredProjects.isEmpty) {
          return Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(
                  Icons.work_off_outlined,
                  size: 80,
                  color: AppColors.lightTextTertiary,
                ),
                const SizedBox(height: Spacing.lg),
                Text(
                  'Aucun projet',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: Spacing.sm),
                Text(
                  isClient
                      ? 'Créez votre premier projet'
                      : 'Aucun projet reçu pour le moment',
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: AppColors.lightTextSecondary,
                      ),
                ),
                if (isClient) ...[
                  const SizedBox(height: Spacing.xl),
                  ElevatedButton.icon(
                    onPressed: () async {
                      final result = await Get.to(() => const CreateProjectScreen());
                      if (result == true) {
                        _loadProjects();
                      }
                    },
                    icon: const Icon(Icons.add),
                    label: const Text('Créer un projet'),
                  ),
                ],
              ],
            ),
          );
        }

        return RefreshIndicator(
          onRefresh: _loadProjects,
          child: ListView.separated(
            padding: const EdgeInsets.all(Spacing.screenPadding),
            itemCount: filteredProjects.length,
            separatorBuilder: (context, index) => const SizedBox(height: Spacing.md),
            itemBuilder: (context, index) {
              final project = filteredProjects[index];
              return _ProjectCard(
                project: project,
                onTap: () async {
                  await Get.to(() => ProjectDetailsScreen(projectId: project.id));
                  _loadProjects();
                },
              );
            },
          ),
        );
      }),
      floatingActionButton: isClient
          ? FloatingActionButton.extended(
              onPressed: () async {
                final result = await Get.to(() => const CreateProjectScreen());
                if (result == true) {
                  _loadProjects();
                }
              },
              icon: const Icon(Icons.add),
              label: const Text('Nouveau projet'),
            )
          : null,
    );
  }
}

class _ProjectCard extends StatelessWidget {
  final dynamic project;
  final VoidCallback onTap;

  const _ProjectCard({required this.project, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.all(Spacing.base),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Title and Status
              Row(
                children: [
                  Expanded(
                    child: Text(
                      project.title,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.bold,
                          ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  const SizedBox(width: Spacing.sm),
                  _buildStatusBadge(context, project.status),
                ],
              ),
              const SizedBox(height: Spacing.sm),

              // Description
              Text(
                project.description,
                style: Theme.of(context).textTheme.bodyMedium,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
              const SizedBox(height: Spacing.md),

              // Meta info
              Row(
                children: [
                  Icon(
                    Icons.location_on_outlined,
                    size: 16,
                    color: AppColors.lightTextSecondary,
                  ),
                  const SizedBox(width: Spacing.xs),
                  Expanded(
                    child: Text(
                      project.address,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: AppColors.lightTextSecondary,
                          ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  const SizedBox(width: Spacing.md),
                  Icon(
                    Icons.calendar_today,
                    size: 14,
                    color: AppColors.lightTextSecondary,
                  ),
                  const SizedBox(width: Spacing.xs),
                  Text(
                    _formatDate(project.createdAt),
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: AppColors.lightTextSecondary,
                        ),
                  ),
                ],
              ),

              // Quotes info
              if (project.quotes != null && project.quotes.isNotEmpty) ...[
                const SizedBox(height: Spacing.sm),
                Row(
                  children: [
                    Icon(
                      Icons.description_outlined,
                      size: 16,
                      color: Theme.of(context).colorScheme.primary,
                    ),
                    const SizedBox(width: Spacing.xs),
                    Text(
                      '${project.quotes.length} devis reçu(s)',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: Theme.of(context).colorScheme.primary,
                            fontWeight: FontWeight.w600,
                          ),
                    ),
                  ],
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildStatusBadge(BuildContext context, String status) {
    Color backgroundColor;
    Color textColor;

    switch (status) {
      case 'pending':
        backgroundColor = AppColors.warning.withValues(alpha: 0.1);
        textColor = AppColors.warning;
        break;
      case 'in_progress':
        backgroundColor = AppColors.info.withValues(alpha: 0.1);
        textColor = AppColors.info;
        break;
      case 'completed':
        backgroundColor = AppColors.success.withValues(alpha: 0.1);
        textColor = AppColors.success;
        break;
      case 'cancelled':
        backgroundColor = AppColors.error.withValues(alpha: 0.1);
        textColor = AppColors.error;
        break;
      default:
        backgroundColor = AppColors.lightBackground;
        textColor = AppColors.lightTextSecondary;
    }

    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: Spacing.sm,
        vertical: Spacing.xs,
      ),
      decoration: BoxDecoration(
        color: backgroundColor,
        borderRadius: BorderRadius.circular(Spacing.radiusSm),
      ),
      child: Text(
        project.statusLabel,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.bold,
          color: textColor,
        ),
      ),
    );
  }

  String _formatDate(DateTime date) {
    final now = DateTime.now();
    final diff = now.difference(date);

    if (diff.inDays == 0) {
      return 'Aujourd\'hui';
    } else if (diff.inDays == 1) {
      return 'Hier';
    } else if (diff.inDays < 7) {
      return 'Il y a ${diff.inDays} jours';
    } else {
      return '${date.day}/${date.month}/${date.year}';
    }
  }
}
