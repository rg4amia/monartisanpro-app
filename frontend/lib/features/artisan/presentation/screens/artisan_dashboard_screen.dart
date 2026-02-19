import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/controllers/auth_controller.dart';
import '../../../../shared/controllers/project_controller.dart';
import '../../../projects/presentation/screens/project_list_screen.dart';
import '../../../search/presentation/screens/map_search_screen_google.dart.bak';
import 'score_dashboard_screen.dart';
import 'quote_management_screen.dart';
import 'artisan_payment_history_screen.dart';

class ArtisanDashboardScreen extends StatefulWidget {
  const ArtisanDashboardScreen({super.key});

  @override
  State<ArtisanDashboardScreen> createState() => _ArtisanDashboardScreenState();
}

class _ArtisanDashboardScreenState extends State<ArtisanDashboardScreen> {
  final _authController = Get.find<AuthController>();
  final _projectController = Get.put(ProjectController());

  @override
  void initState() {
    super.initState();
    _loadDashboardData();
  }

  Future<void> _loadDashboardData() async {
    await _projectController.fetchMyProjects();
  }

  @override
  Widget build(BuildContext context) {
    final user = _authController.currentUser.value;

    return Scaffold(
      body: RefreshIndicator(
        onRefresh: _loadDashboardData,
        child: CustomScrollView(
          slivers: [
            // App Bar with gradient
            SliverAppBar(
              expandedHeight: 200,
              pinned: true,
              flexibleSpace: FlexibleSpaceBar(
                title: const Text('Tableau de bord'),
                background: Container(
                  decoration: const BoxDecoration(
                    gradient: LinearGradient(
                      colors: AppColors.primaryGradient,
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                    ),
                  ),
                  child: SafeArea(
                    child: Padding(
                      padding: const EdgeInsets.all(Spacing.lg),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const SizedBox(height: Spacing.md),
                          Text(
                            'Bonjour, ${user?.name ?? "Artisan"}',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 24,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          const SizedBox(height: Spacing.xs),
                          Row(
                            children: [
                              const Icon(
                                Icons.star,
                                color: AppColors.starRating,
                                size: 20,
                              ),
                              const SizedBox(width: Spacing.xs),
                              Text(
                                'Score N\'Zassa: --',
                                style: const TextStyle(
                                  color: Colors.white70,
                                  fontSize: 14,
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),

            // Quick Actions
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.all(Spacing.md),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Actions rapides',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: Spacing.md),
                    Row(
                      children: [
                        Expanded(
                          child: _QuickActionCard(
                            icon: Icons.search,
                            label: 'Chercher projets',
                            color: AppColors.info,
                            onTap: () => Get.to(() => const MapSearchScreen()),
                          ),
                        ),
                        const SizedBox(width: Spacing.md),
                        Expanded(
                          child: _QuickActionCard(
                            icon: Icons.description,
                            label: 'Mes devis',
                            color: AppColors.warning,
                            onTap: () =>
                                Get.to(() => const QuoteManagementScreen()),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: Spacing.md),
                    Row(
                      children: [
                        Expanded(
                          child: _QuickActionCard(
                            icon: Icons.star,
                            label: 'Mon score',
                            color: AppColors.starRating,
                            onTap: () =>
                                Get.to(() => const ScoreDashboardScreen()),
                          ),
                        ),
                        const SizedBox(width: Spacing.md),
                        Expanded(
                          child: _QuickActionCard(
                            icon: Icons.payments,
                            label: 'Paiements',
                            color: AppColors.success,
                            onTap: () => Get.to(
                              () => const ArtisanPaymentHistoryScreen(),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),

            // Statistics
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.all(Spacing.md),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Statistiques',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: Spacing.md),
                    Obx(() {
                      final projects = _projectController.myProjects;
                      final activeProjects = projects
                          .where((p) => p.status == 'in_progress')
                          .length;
                      final completedProjects = projects
                          .where((p) => p.status == 'completed')
                          .length;

                      return Column(
                        children: [
                          _StatCard(
                            title: 'Projets actifs',
                            value: '$activeProjects',
                            icon: Icons.work,
                            color: AppColors.info,
                          ),
                          const SizedBox(height: Spacing.md),
                          _StatCard(
                            title: 'Projets terminés',
                            value: '$completedProjects',
                            icon: Icons.check_circle,
                            color: AppColors.success,
                          ),
                          const SizedBox(height: Spacing.md),
                          _StatCard(
                            title: 'Devis en attente',
                            value: '0',
                            icon: Icons.pending,
                            color: AppColors.warning,
                          ),
                        ],
                      );
                    }),
                  ],
                ),
              ),
            ),

            // Recent Projects
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.all(Spacing.md),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        const Text(
                          'Projets récents',
                          style: TextStyle(
                            fontSize: 18,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        TextButton(
                          onPressed: () =>
                              Get.to(() => const ProjectListScreen()),
                          child: const Text('Voir tout'),
                        ),
                      ],
                    ),
                    const SizedBox(height: Spacing.md),
                    Obx(() {
                      final projects = _projectController.myProjects
                          .take(3)
                          .toList();
                      if (projects.isEmpty) {
                        return _buildEmptyState();
                      }
                      return Column(
                        children: projects
                            .map(
                              (project) => Card(
                                margin: const EdgeInsets.only(
                                  bottom: Spacing.md,
                                ),
                                child: ListTile(
                                  leading: CircleAvatar(
                                    child: Text(project.title[0].toUpperCase()),
                                  ),
                                  title: Text(project.title),
                                  subtitle: Text(project.status),
                                  trailing: const Icon(
                                    Icons.arrow_forward_ios,
                                    size: 16,
                                  ),
                                  onTap: () {
                                    // Navigate to project details
                                  },
                                ),
                              ),
                            )
                            .toList(),
                      );
                    }),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => Get.to(() => const MapSearchScreen()),
        icon: const Icon(Icons.search),
        label: const Text('Chercher projets'),
        backgroundColor: AppColors.darkAccentPrimary,
      ),
    );
  }

  Widget _buildEmptyState() {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(Spacing.xl),
        child: Column(
          children: [
            Icon(Icons.work_outline, size: 64, color: Colors.grey[400]),
            const SizedBox(height: Spacing.md),
            Text(
              'Aucun projet',
              style: TextStyle(fontSize: 16, color: Colors.grey[600]),
            ),
            const SizedBox(height: Spacing.xs),
            Text(
              'Cherchez des projets à proximité',
              style: TextStyle(fontSize: 14, color: Colors.grey[500]),
            ),
          ],
        ),
      ),
    );
  }
}

class _QuickActionCard extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;

  const _QuickActionCard({
    required this.icon,
    required this.label,
    required this.color,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 2,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(Spacing.lg),
          child: Column(
            children: [
              Container(
                padding: const EdgeInsets.all(Spacing.md),
                decoration: BoxDecoration(
                  color: color.withOpacity(0.1),
                  shape: BoxShape.circle,
                ),
                child: Icon(icon, size: 32, color: color),
              ),
              const SizedBox(height: Spacing.sm),
              Text(
                label,
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                ),
                textAlign: TextAlign.center,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _StatCard extends StatelessWidget {
  final String title;
  final String value;
  final IconData icon;
  final Color color;

  const _StatCard({
    required this.title,
    required this.value,
    required this.icon,
    required this.color,
  });

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 2,
      child: Padding(
        padding: const EdgeInsets.all(Spacing.lg),
        child: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(Spacing.md),
              decoration: BoxDecoration(
                color: color.withOpacity(0.1),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icon, size: 32, color: color),
            ),
            const SizedBox(width: Spacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(fontSize: 14, color: Colors.grey),
                  ),
                  const SizedBox(height: Spacing.xs),
                  Text(
                    value,
                    style: const TextStyle(
                      fontSize: 24,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
