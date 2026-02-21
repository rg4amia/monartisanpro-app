import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:image_picker/image_picker.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/controllers/milestone_controller.dart';
import '../../../../shared/controllers/auth_controller.dart';
import '../../../../shared/models/milestone_model.dart';
import '../../../../shared/models/project_model.dart';

class MilestoneTrackingScreen extends StatefulWidget {
  final int projectId;
  final Project project;

  const MilestoneTrackingScreen({
    super.key,
    required this.projectId,
    required this.project,
  });

  @override
  State<MilestoneTrackingScreen> createState() =>
      _MilestoneTrackingScreenState();
}

class _MilestoneTrackingScreenState extends State<MilestoneTrackingScreen> {
  final _milestoneController = Get.put(MilestoneController());
  final _authController = Get.find<AuthController>();
  bool _isLoading = true;

  @override
  void initState() {
    super.initState();
    _loadMilestones();
  }

  Future<void> _loadMilestones() async {
    setState(() => _isLoading = true);
    await _milestoneController.refreshMilestoneData(widget.projectId);
    setState(() => _isLoading = false);
  }

  bool get _isClient =>
      _authController.currentUser.value?.id == widget.project.client.id;
  bool get _isArtisan =>
      _authController.currentUser.value?.id == widget.project.artisan?.id;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Suivi des étapes'),
        actions: [
          if (_isArtisan)
            IconButton(
              icon: const Icon(Icons.add),
              onPressed: _showCreateMilestoneDialog,
            ),
        ],
      ),
      body: Obx(() {
        final milestones = _milestoneController.projectMilestones;
        final progress = _milestoneController.projectProgress.value;

        if (_isLoading && milestones.isEmpty) {
          return const Center(child: CircularProgressIndicator());
        }

        return RefreshIndicator(
          onRefresh: _loadMilestones,
          child: CustomScrollView(
            slivers: [
              // Progress Header
              if (progress != null)
                SliverToBoxAdapter(
                  child: Container(
                    margin: const EdgeInsets.all(Spacing.screenPadding),
                    padding: const EdgeInsets.all(Spacing.lg),
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        colors: AppColors.primaryGradient,
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(Spacing.radiusMd),
                    ),
                    child: Column(
                      children: [
                        Text(
                          'Progression du projet',
                          style: Theme.of(context).textTheme.titleMedium
                              ?.copyWith(
                                color: Colors.white,
                                fontWeight: FontWeight.bold,
                              ),
                        ),
                        const SizedBox(height: Spacing.md),
                        Text(
                          progress.formattedCompletion,
                          style: Theme.of(context).textTheme.displayMedium
                              ?.copyWith(
                                color: Colors.white,
                                fontWeight: FontWeight.bold,
                              ),
                        ),
                        const SizedBox(height: Spacing.sm),
                        Text(
                          progress.milestonesText,
                          style: Theme.of(context).textTheme.bodyMedium
                              ?.copyWith(
                                color: Colors.white.withValues(alpha: 0.9),
                              ),
                        ),
                        const SizedBox(height: Spacing.lg),
                        ClipRRect(
                          borderRadius: BorderRadius.circular(Spacing.radiusSm),
                          child: LinearProgressIndicator(
                            value: progress.completionPercentage / 100,
                            backgroundColor: Colors.white.withValues(
                              alpha: 0.3,
                            ),
                            valueColor: AlwaysStoppedAnimation<Color>(
                              Colors.white.withValues(alpha: 0.9),
                            ),
                            minHeight: 8,
                          ),
                        ),
                        const SizedBox(height: Spacing.md),
                        Row(
                          mainAxisAlignment: MainAxisAlignment.spaceBetween,
                          children: [
                            Text(
                              'Montant libéré: ${progress.releasedLaborAmount.toStringAsFixed(0)} FCFA',
                              style: Theme.of(context).textTheme.bodySmall
                                  ?.copyWith(
                                    color: Colors.white.withValues(alpha: 0.9),
                                  ),
                            ),
                            Text(
                              'En attente: ${progress.pendingLaborAmount.toStringAsFixed(0)} FCFA',
                              style: Theme.of(context).textTheme.bodySmall
                                  ?.copyWith(
                                    color: Colors.white.withValues(alpha: 0.9),
                                  ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ),

              // Milestones List
              if (milestones.isEmpty)
                SliverFillRemaining(
                  child: Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          Icons.flag_outlined,
                          size: 64,
                          color: AppColors.lightTextTertiary,
                        ),
                        const SizedBox(height: Spacing.lg),
                        Text(
                          'Aucune étape définie',
                          style: Theme.of(context).textTheme.bodyLarge
                              ?.copyWith(color: AppColors.lightTextSecondary),
                        ),
                        if (_isArtisan) ...[
                          const SizedBox(height: Spacing.md),
                          ElevatedButton.icon(
                            onPressed: _showCreateMilestoneDialog,
                            icon: const Icon(Icons.add),
                            label: const Text('Créer une étape'),
                          ),
                        ],
                      ],
                    ),
                  ),
                )
              else
                SliverPadding(
                  padding: const EdgeInsets.all(Spacing.screenPadding),
                  sliver: SliverList(
                    delegate: SliverChildBuilderDelegate((context, index) {
                      final milestone = milestones[index];
                      return _buildMilestoneCard(context, milestone, index);
                    }, childCount: milestones.length),
                  ),
                ),
            ],
          ),
        );
      }),
    );
  }

  Widget _buildMilestoneCard(
    BuildContext context,
    Milestone milestone,
    int index,
  ) {
    return Card(
      margin: const EdgeInsets.only(bottom: Spacing.md),
      child: Column(
        children: [
          // Header
          Container(
            padding: const EdgeInsets.all(Spacing.base),
            decoration: BoxDecoration(
              color: _getMilestoneColor(
                milestone.status,
              ).withValues(alpha: 0.1),
              borderRadius: const BorderRadius.only(
                topLeft: Radius.circular(Spacing.radiusMd),
                topRight: Radius.circular(Spacing.radiusMd),
              ),
            ),
            child: Row(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: _getMilestoneColor(milestone.status),
                    shape: BoxShape.circle,
                  ),
                  child: Center(
                    child: Text(
                      '${index + 1}',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.bold,
                        color: Colors.white,
                      ),
                    ),
                  ),
                ),
                const SizedBox(width: Spacing.md),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        milestone.title,
                        style: Theme.of(context).textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      Text(
                        milestone.formattedAmount,
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: _getMilestoneColor(milestone.status),
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                ),
                _buildStatusBadge(milestone.status, milestone.statusLabel),
              ],
            ),
          ),

          // Body
          Padding(
            padding: const EdgeInsets.all(Spacing.base),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  milestone.description,
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
                const SizedBox(height: Spacing.md),

                // Requirements
                if (milestone.requiresPhoto || milestone.requiresOtp) ...[
                  Wrap(
                    spacing: Spacing.sm,
                    children: [
                      if (milestone.requiresPhoto)
                        Chip(
                          avatar: Icon(
                            milestone.photoUrl != null
                                ? Icons.check_circle
                                : Icons.camera_alt,
                            size: 16,
                            color: milestone.photoUrl != null
                                ? AppColors.success
                                : null,
                          ),
                          label: const Text('Photo requise'),
                          labelStyle: const TextStyle(fontSize: 12),
                        ),
                      if (milestone.requiresOtp)
                        Chip(
                          avatar: Icon(
                            milestone.otpVerifiedAt != null
                                ? Icons.check_circle
                                : Icons.sms,
                            size: 16,
                            color: milestone.otpVerifiedAt != null
                                ? AppColors.success
                                : null,
                          ),
                          label: const Text('OTP requis'),
                          labelStyle: const TextStyle(fontSize: 12),
                        ),
                    ],
                  ),
                  const SizedBox(height: Spacing.md),
                ],

                // Photo if uploaded
                if (milestone.photoUrl != null) ...[
                  ClipRRect(
                    borderRadius: BorderRadius.circular(Spacing.radiusMd),
                    child: Image.network(
                      milestone.photoUrl!,
                      height: 200,
                      width: double.infinity,
                      fit: BoxFit.cover,
                    ),
                  ),
                  const SizedBox(height: Spacing.md),
                ],

                // Actions
                if (_isArtisan && milestone.canComplete)
                  ElevatedButton.icon(
                    onPressed: () => _completeMilestone(milestone),
                    icon: const Icon(Icons.check),
                    label: const Text('Marquer comme terminé'),
                    style: ElevatedButton.styleFrom(
                      minimumSize: const Size(double.infinity, 48),
                    ),
                  ),

                if (_isClient && milestone.canValidate)
                  Row(
                    children: [
                      Expanded(
                        child: ElevatedButton.icon(
                          onPressed: () => _validateMilestone(milestone, true),
                          icon: const Icon(Icons.check_circle),
                          label: const Text('Valider'),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: AppColors.success,
                          ),
                        ),
                      ),
                      const SizedBox(width: Spacing.md),
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: () => _validateMilestone(milestone, false),
                          icon: const Icon(Icons.cancel),
                          label: const Text('Rejeter'),
                          style: OutlinedButton.styleFrom(
                            foregroundColor: AppColors.error,
                            side: BorderSide(color: AppColors.error),
                          ),
                        ),
                      ),
                    ],
                  ),

                // Status info
                if (milestone.isPaid && milestone.paidAt != null)
                  Container(
                    margin: const EdgeInsets.only(top: Spacing.md),
                    padding: const EdgeInsets.all(Spacing.sm),
                    decoration: BoxDecoration(
                      color: AppColors.success.withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(Spacing.radiusSm),
                    ),
                    child: Row(
                      children: [
                        Icon(
                          Icons.payments,
                          color: AppColors.success,
                          size: 16,
                        ),
                        const SizedBox(width: Spacing.sm),
                        Expanded(
                          child: Text(
                            'Payé le ${_formatDate(milestone.paidAt!)}',
                            style: Theme.of(context).textTheme.bodySmall
                                ?.copyWith(
                                  color: AppColors.success,
                                  fontWeight: FontWeight.w600,
                                ),
                          ),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStatusBadge(String status, String label) {
    return Container(
      padding: const EdgeInsets.symmetric(
        horizontal: Spacing.sm,
        vertical: Spacing.xs,
      ),
      decoration: BoxDecoration(
        color: _getMilestoneColor(status).withValues(alpha: 0.1),
        borderRadius: BorderRadius.circular(Spacing.radiusSm),
        border: Border.all(
          color: _getMilestoneColor(status).withValues(alpha: 0.3),
        ),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.bold,
          color: _getMilestoneColor(status),
        ),
      ),
    );
  }

  Color _getMilestoneColor(String status) {
    switch (status) {
      case 'pending':
        return AppColors.lightTextSecondary;
      case 'in_progress':
        return AppColors.info;
      case 'completed':
        return AppColors.warning;
      case 'validated':
        return AppColors.success;
      case 'paid':
        return AppColors.success;
      default:
        return AppColors.lightTextSecondary;
    }
  }

  void _showCreateMilestoneDialog() {
    final titleController = TextEditingController();
    final descriptionController = TextEditingController();
    final percentageController = TextEditingController();
    bool requiresPhoto = true;
    bool requiresOtp = true;

    Get.dialog(
      StatefulBuilder(
        builder: (context, setState) {
          return AlertDialog(
            title: const Text('Créer une étape'),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  TextField(
                    controller: titleController,
                    decoration: const InputDecoration(
                      labelText: 'Titre',
                      hintText: 'Ex: Fondations terminées',
                    ),
                  ),
                  const SizedBox(height: Spacing.md),
                  TextField(
                    controller: descriptionController,
                    maxLines: 3,
                    decoration: const InputDecoration(
                      labelText: 'Description',
                      hintText: 'Détails de cette étape...',
                    ),
                  ),
                  const SizedBox(height: Spacing.md),
                  TextField(
                    controller: percentageController,
                    keyboardType: TextInputType.number,
                    decoration: const InputDecoration(
                      labelText: 'Pourcentage du paiement MO',
                      suffix: Text('%'),
                    ),
                  ),
                  const SizedBox(height: Spacing.md),
                  CheckboxListTile(
                    title: const Text('Photo requise'),
                    value: requiresPhoto,
                    onChanged: (value) =>
                        setState(() => requiresPhoto = value!),
                  ),
                  CheckboxListTile(
                    title: const Text('OTP requis'),
                    value: requiresOtp,
                    onChanged: (value) => setState(() => requiresOtp = value!),
                  ),
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Get.back(),
                child: const Text('Annuler'),
              ),
              ElevatedButton(
                onPressed: () async {
                  final request = CreateMilestoneRequest(
                    projectId: widget.projectId,
                    title: titleController.text,
                    description: descriptionController.text,
                    laborPercentage:
                        double.tryParse(percentageController.text) ?? 0,
                    sequenceOrder:
                        _milestoneController.projectMilestones.length + 1,
                    requiresPhoto: requiresPhoto,
                    requiresOtp: requiresOtp,
                  );

                  final success = await _milestoneController.createMilestone(
                    request,
                  );
                  Get.back();

                  if (success) {
                    Get.snackbar(
                      'Succès',
                      'Étape créée',
                      backgroundColor: AppColors.success,
                      colorText: Colors.white,
                      snackPosition: SnackPosition.TOP,
                    );
                  }
                },
                child: const Text('Créer'),
              ),
            ],
          );
        },
      ),
    );
  }

  Future<void> _completeMilestone(Milestone milestone) async {
    String? photoPath;

    if (milestone.requiresPhoto) {
      final picker = ImagePicker();
      final image = await picker.pickImage(source: ImageSource.camera);
      if (image == null) return;
      photoPath = image.path;
    }

    final request = CompleteMilestoneRequest(
      milestoneId: milestone.id,
      photoPath: photoPath,
    );

    final success = await _milestoneController.completeMilestone(request);

    if (success) {
      Get.snackbar(
        'Succès',
        'Étape marquée comme terminée',
        backgroundColor: AppColors.success,
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
      );
    }
  }

  Future<void> _validateMilestone(Milestone milestone, bool approved) async {
    String? otpCode;

    if (approved && milestone.requiresOtp) {
      // Send OTP first
      await _milestoneController.sendMilestoneOtp(milestone.id);

      // Show OTP dialog
      final otpController = TextEditingController();
      otpCode = await Get.dialog<String>(
        AlertDialog(
          title: const Text('Code OTP'),
          content: TextField(
            controller: otpController,
            keyboardType: TextInputType.number,
            maxLength: 6,
            decoration: const InputDecoration(
              labelText: 'Entrez le code OTP reçu par SMS',
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Get.back(),
              child: const Text('Annuler'),
            ),
            ElevatedButton(
              onPressed: () => Get.back(result: otpController.text),
              child: const Text('Valider'),
            ),
          ],
        ),
      );

      if (otpCode == null || otpCode.isEmpty) return;
    }

    final request = ValidateMilestoneRequest(
      milestoneId: milestone.id,
      otpCode: otpCode,
      approved: approved,
    );

    final success = await _milestoneController.validateMilestone(request);

    if (success) {
      Get.snackbar(
        'Succès',
        approved ? 'Étape validée' : 'Étape rejetée',
        backgroundColor: approved ? AppColors.success : AppColors.warning,
        colorText: Colors.white,
        snackPosition: SnackPosition.TOP,
      );
    }
  }

  String _formatDate(DateTime date) {
    return '${date.day}/${date.month}/${date.year}';
  }
}
