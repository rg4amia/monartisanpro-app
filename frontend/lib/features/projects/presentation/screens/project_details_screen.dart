import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/controllers/project_controller.dart';
import '../../../../shared/controllers/auth_controller.dart';
import 'create_quote_screen.dart';
import 'quote_review_screen.dart';

class ProjectDetailsScreen extends StatefulWidget {
  final int projectId;

  const ProjectDetailsScreen({super.key, required this.projectId});

  @override
  State<ProjectDetailsScreen> createState() => _ProjectDetailsScreenState();
}

class _ProjectDetailsScreenState extends State<ProjectDetailsScreen> {
  final _projectController = Get.find<ProjectController>();
  final _authController = Get.find<AuthController>();

  @override
  void initState() {
    super.initState();
    _loadProjectDetails();
  }

  Future<void> _loadProjectDetails() async {
    await _projectController.fetchProjectDetails(widget.projectId);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Obx(() {
        if (_projectController.isLoading.value) {
          return const Center(child: CircularProgressIndicator());
        }

        final project = _projectController.currentProject.value;
        if (project == null) {
          return const Center(child: Text('Projet non trouvé'));
        }

        final user = _authController.currentUser.value;
        final isClient = user?.id == project.client.id;
        final isArtisan = user?.id == project.artisan?.id;

        return CustomScrollView(
          slivers: [
            // App Bar
            SliverAppBar(
              expandedHeight: 200,
              pinned: true,
              flexibleSpace: FlexibleSpaceBar(
                title: Text(
                  project.title,
                  style: const TextStyle(
                    shadows: [Shadow(color: Colors.black45, blurRadius: 10)],
                  ),
                ),
                background: Container(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      colors: AppColors.primaryGradient,
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                  ),
                  child: Center(
                    child: Icon(
                      Icons.construction,
                      size: 80,
                      color: Colors.white.withValues(alpha: 0.3),
                    ),
                  ),
                ),
              ),
            ),

            // Content
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.all(Spacing.screenPadding),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Status Badge
                    Align(
                      alignment: Alignment.centerLeft,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: Spacing.md,
                          vertical: Spacing.sm,
                        ),
                        decoration: BoxDecoration(
                          color: _getStatusColor(
                            project.status,
                          ).withValues(alpha: 0.1),
                          borderRadius: BorderRadius.circular(Spacing.radiusMd),
                          border: Border.all(
                            color: _getStatusColor(project.status),
                            width: 2,
                          ),
                        ),
                        child: Text(
                          project.statusLabel,
                          style: TextStyle(
                            fontWeight: FontWeight.bold,
                            color: _getStatusColor(project.status),
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: Spacing.xl),

                    // Description
                    _buildSection(
                      context,
                      'Description',
                      Icons.description_outlined,
                    ),
                    const SizedBox(height: Spacing.md),
                    Text(
                      project.description,
                      style: Theme.of(context).textTheme.bodyMedium,
                    ),
                    const SizedBox(height: Spacing.xl),

                    // Location
                    _buildSection(
                      context,
                      'Localisation',
                      Icons.location_on_outlined,
                    ),
                    const SizedBox(height: Spacing.md),
                    Container(
                      padding: const EdgeInsets.all(Spacing.base),
                      decoration: BoxDecoration(
                        color: AppColors.lightBackground,
                        borderRadius: BorderRadius.circular(Spacing.radiusMd),
                      ),
                      child: Row(
                        children: [
                          Icon(
                            Icons.place,
                            color: Theme.of(context).colorScheme.primary,
                          ),
                          const SizedBox(width: Spacing.md),
                          Expanded(
                            child: Text(
                              project.address,
                              style: Theme.of(context).textTheme.bodyMedium,
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: Spacing.xl),

                    // Client/Artisan info
                    _buildSection(
                      context,
                      isClient ? 'Mes informations' : 'Client',
                      Icons.person_outline,
                    ),
                    const SizedBox(height: Spacing.md),
                    _buildUserCard(project.client),
                    const SizedBox(height: Spacing.xl),

                    if (project.artisan != null) ...[
                      _buildSection(
                        context,
                        isArtisan ? 'Mes informations' : 'Artisan',
                        Icons.build_outlined,
                      ),
                      const SizedBox(height: Spacing.md),
                      _buildUserCard(project.artisan!),
                      const SizedBox(height: Spacing.xl),
                    ],

                    // Quotes
                    _buildSection(
                      context,
                      'Devis (${_projectController.projectQuotes.length})',
                      Icons.receipt_long_outlined,
                    ),
                    const SizedBox(height: Spacing.md),

                    if (_projectController.projectQuotes.isEmpty)
                      Container(
                        padding: const EdgeInsets.all(Spacing.xl),
                        decoration: BoxDecoration(
                          color: AppColors.lightBackground,
                          borderRadius: BorderRadius.circular(Spacing.radiusMd),
                        ),
                        child: Column(
                          children: [
                            Icon(
                              Icons.inbox_outlined,
                              size: 48,
                              color: AppColors.lightTextTertiary,
                            ),
                            const SizedBox(height: Spacing.md),
                            Text(
                              'Aucun devis reçu',
                              style: Theme.of(context).textTheme.bodyMedium
                                  ?.copyWith(
                                    color: AppColors.lightTextSecondary,
                                  ),
                            ),
                          ],
                        ),
                      )
                    else
                      ..._projectController.projectQuotes.map((quote) {
                        return Padding(
                          padding: const EdgeInsets.only(bottom: Spacing.md),
                          child: _buildQuoteCard(quote, isClient),
                        );
                      }),

                    const SizedBox(height: Spacing.xl),

                    // Actions
                    if (isClient && project.isPending) ...[
                      OutlinedButton.icon(
                        onPressed: () async {
                          final confirm = await Get.dialog<bool>(
                            AlertDialog(
                              title: const Text('Annuler le projet'),
                              content: const Text(
                                'Êtes-vous sûr de vouloir annuler ce projet?',
                              ),
                              actions: [
                                TextButton(
                                  onPressed: () => Get.back(result: false),
                                  child: const Text('Non'),
                                ),
                                ElevatedButton(
                                  onPressed: () => Get.back(result: true),
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: AppColors.error,
                                  ),
                                  child: const Text('Oui, annuler'),
                                ),
                              ],
                            ),
                          );

                          if (confirm == true) {
                            final success = await _projectController
                                .cancelProject(
                                  project.id,
                                  'Annulé par le client',
                                );
                            if (success) {
                              Get.back(result: true);
                            }
                          }
                        },
                        icon: const Icon(Icons.cancel_outlined),
                        label: const Text('Annuler le projet'),
                        style: OutlinedButton.styleFrom(
                          foregroundColor: AppColors.error,
                        ),
                      ),
                    ],

                    if (!isClient && !isArtisan && project.isPending) ...[
                      ElevatedButton.icon(
                        onPressed: () async {
                          final result = await Get.to(
                            () => CreateQuoteScreen(projectId: project.id),
                          );
                          if (result == true) {
                            _loadProjectDetails();
                          }
                        },
                        icon: const Icon(Icons.add),
                        label: const Text('Soumettre un devis'),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ],
        );
      }),
    );
  }

  Widget _buildSection(BuildContext context, String title, IconData icon) {
    return Row(
      children: [
        Icon(icon, size: 20, color: Theme.of(context).colorScheme.primary),
        const SizedBox(width: Spacing.sm),
        Text(
          title,
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.bold),
        ),
      ],
    );
  }

  Widget _buildUserCard(dynamic user) {
    return Container(
      padding: const EdgeInsets.all(Spacing.base),
      decoration: BoxDecoration(
        color: AppColors.lightBackground,
        borderRadius: BorderRadius.circular(Spacing.radiusMd),
      ),
      child: Row(
        children: [
          CircleAvatar(
            radius: 24,
            backgroundImage: user.avatar != null
                ? NetworkImage(user.avatar!)
                : null,
            child: user.avatar == null ? const Icon(Icons.person) : null,
          ),
          const SizedBox(width: Spacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  user.name,
                  style: Theme.of(
                    context,
                  ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w600),
                ),
                if (user.phone != null)
                  Text(
                    user.phone,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: AppColors.lightTextSecondary,
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildQuoteCard(dynamic quote, bool isClient) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(Spacing.base),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    quote.artisan?.name ?? 'Artisan',
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: Spacing.sm,
                    vertical: Spacing.xs,
                  ),
                  decoration: BoxDecoration(
                    color: _getQuoteStatusColor(
                      quote.status,
                    ).withValues(alpha: 0.1),
                    borderRadius: BorderRadius.circular(Spacing.radiusSm),
                  ),
                  child: Text(
                    quote.statusLabel,
                    style: TextStyle(
                      fontSize: 11,
                      fontWeight: FontWeight.bold,
                      color: _getQuoteStatusColor(quote.status),
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: Spacing.md),
            Row(
              children: [
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Total',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: AppColors.lightTextSecondary,
                        ),
                      ),
                      Text(
                        quote.formattedTotal,
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(
                              fontWeight: FontWeight.bold,
                              color: Theme.of(context).colorScheme.primary,
                            ),
                      ),
                    ],
                  ),
                ),
                if (isClient && quote.isSent)
                  ElevatedButton(
                    onPressed: () async {
                      final result = await Get.to(
                        () => QuoteReviewScreen(
                          quote: quote,
                          projectId: widget.projectId,
                        ),
                      );
                      if (result == true) {
                        _loadProjectDetails();
                      }
                    },
                    child: const Text('Voir'),
                  ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Color _getStatusColor(String status) {
    switch (status) {
      case 'pending':
        return AppColors.warning;
      case 'in_progress':
        return AppColors.info;
      case 'completed':
        return AppColors.success;
      case 'cancelled':
      case 'disputed':
        return AppColors.error;
      default:
        return AppColors.lightTextSecondary;
    }
  }

  Color _getQuoteStatusColor(String status) {
    switch (status) {
      case 'sent':
        return AppColors.info;
      case 'accepted':
        return AppColors.success;
      case 'rejected':
      case 'expired':
        return AppColors.error;
      default:
        return AppColors.lightTextSecondary;
    }
  }
}
