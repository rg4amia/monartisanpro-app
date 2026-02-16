import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/controllers/kyc_controller.dart';
import 'kyc_upload_screen.dart';

class KycStatusScreen extends StatefulWidget {
  const KycStatusScreen({super.key});

  @override
  State<KycStatusScreen> createState() => _KycStatusScreenState();
}

class _KycStatusScreenState extends State<KycStatusScreen> {
  final _kycController = Get.find<KycController>();

  @override
  void initState() {
    super.initState();
    _kycController.fetchKycStatus();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Statut de vérification'),
      ),
      body: Obx(() {
        if (_kycController.isLoading.value) {
          return const Center(child: CircularProgressIndicator());
        }

        final status = _kycController.kycStatus.value;

        if (status == null) {
          return _buildNoKycState();
        }

        if (status.isPending) {
          return _buildPendingState();
        }

        if (status.isApproved) {
          return _buildApprovedState(status);
        }

        if (status.isRejected) {
          return _buildRejectedState(status);
        }

        return _buildNoKycState();
      }),
    );
  }

  Widget _buildNoKycState() {
    return Padding(
      padding: const EdgeInsets.all(Spacing.screenPadding),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Icon(
            Icons.verified_user_outlined,
            size: 100,
            color: AppColors.lightTextTertiary,
          ),
          const SizedBox(height: Spacing.xl),
          Text(
            'Vérification requise',
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.bold,
                ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: Spacing.md),
          Text(
            'Pour utiliser toutes les fonctionnalités de la plateforme, vous devez vérifier votre identité.',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: AppColors.lightTextSecondary,
                ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: Spacing.xxxl),
          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: () {
                Get.to(() => const KycUploadScreen());
              },
              child: const Text('Commencer la vérification'),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPendingState() {
    return Padding(
      padding: const EdgeInsets.all(Spacing.screenPadding),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          // Animated icon
          Container(
            width: 120,
            height: 120,
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: AppColors.warningGradient,
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.hourglass_empty,
              size: 64,
              color: Colors.white,
            ),
          ),
          const SizedBox(height: Spacing.xl),

          Text(
            'Vérification en cours',
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.bold,
                ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: Spacing.md),

          Text(
            'Vos documents sont en cours de vérification par notre équipe. Cela peut prendre jusqu\'à 24 heures.',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: AppColors.lightTextSecondary,
                ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: Spacing.xxxl),

          // Info cards
          _buildInfoCard(
            icon: Icons.check_circle_outline,
            title: 'Documents reçus',
            description: 'Nous avons bien reçu vos documents',
          ),
          const SizedBox(height: Spacing.md),
          _buildInfoCard(
            icon: Icons.access_time,
            title: 'En attente',
            description: 'Vérification en cours',
          ),
          const SizedBox(height: Spacing.xxxl),

          OutlinedButton.icon(
            onPressed: () {
              _kycController.fetchKycStatus();
            },
            icon: const Icon(Icons.refresh),
            label: const Text('Actualiser le statut'),
          ),
        ],
      ),
    );
  }

  Widget _buildApprovedState(dynamic status) {
    return Padding(
      padding: const EdgeInsets.all(Spacing.screenPadding),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          // Success icon
          Container(
            width: 120,
            height: 120,
            decoration: BoxDecoration(
              gradient: LinearGradient(
                colors: AppColors.successGradient,
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
              ),
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.check_circle,
              size: 64,
              color: Colors.white,
            ),
          ),
          const SizedBox(height: Spacing.xl),

          Text(
            'Compte vérifié!',
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.bold,
                  color: AppColors.success,
                ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: Spacing.md),

          Text(
            'Votre identité a été vérifiée avec succès. Vous pouvez maintenant accéder à toutes les fonctionnalités.',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: AppColors.lightTextSecondary,
                ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: Spacing.sm),

          if (status.verifiedAt != null)
            Text(
              'Vérifié le ${_formatDate(status.verifiedAt)}',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: AppColors.lightTextTertiary,
                  ),
            ),
          const SizedBox(height: Spacing.xxxl),

          SizedBox(
            width: double.infinity,
            child: ElevatedButton(
              onPressed: () {
                // TODO: Navigate to home or complete profile
                Get.offAllNamed('/home');
              },
              child: const Text('Continuer'),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildRejectedState(dynamic status) {
    return Padding(
      padding: const EdgeInsets.all(Spacing.screenPadding),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          // Error icon
          Container(
            width: 120,
            height: 120,
            decoration: BoxDecoration(
              color: AppColors.error.withValues(alpha: 0.1),
              shape: BoxShape.circle,
              border: Border.all(
                color: AppColors.error,
                width: 2,
              ),
            ),
            child: Icon(
              Icons.cancel_outlined,
              size: 64,
              color: AppColors.error,
            ),
          ),
          const SizedBox(height: Spacing.xl),

          Text(
            'Vérification refusée',
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.bold,
                  color: AppColors.error,
                ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: Spacing.md),

          Text(
            'Malheureusement, nous n\'avons pas pu vérifier votre identité.',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: AppColors.lightTextSecondary,
                ),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: Spacing.xl),

          // Rejection reason
          if (status.rejectionReason != null)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(Spacing.base),
              decoration: BoxDecoration(
                color: AppColors.error.withValues(alpha: 0.1),
                borderRadius: BorderRadius.circular(Spacing.radiusMd),
                border: Border.all(
                  color: AppColors.error.withValues(alpha: 0.3),
                ),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Icon(
                        Icons.info_outline,
                        color: AppColors.error,
                        size: 20,
                      ),
                      const SizedBox(width: Spacing.sm),
                      Text(
                        'Raison du refus',
                        style: Theme.of(context).textTheme.titleSmall?.copyWith(
                              fontWeight: FontWeight.w600,
                              color: AppColors.error,
                            ),
                      ),
                    ],
                  ),
                  const SizedBox(height: Spacing.sm),
                  Text(
                    status.rejectionReason,
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ),
            ),
          const SizedBox(height: Spacing.xxxl),

          // Retry button
          SizedBox(
            width: double.infinity,
            child: ElevatedButton.icon(
              onPressed: () {
                // Clear previous files and navigate to upload
                _kycController.clearFiles();
                Get.off(() => const KycUploadScreen());
              },
              icon: const Icon(Icons.refresh),
              label: const Text('Soumettre à nouveau'),
            ),
          ),
          const SizedBox(height: Spacing.md),

          OutlinedButton(
            onPressed: () {
              // TODO: Contact support
              Get.snackbar(
                'Support',
                'Contactez-nous à support@prosartisan.net',
                backgroundColor: AppColors.info,
                colorText: Colors.white,
                snackPosition: SnackPosition.TOP,
              );
            },
            child: const Text('Contacter le support'),
          ),
        ],
      ),
    );
  }

  Widget _buildInfoCard({
    required IconData icon,
    required String title,
    required String description,
  }) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(Spacing.base),
      decoration: BoxDecoration(
        color: AppColors.lightBackground,
        borderRadius: BorderRadius.circular(Spacing.radiusMd),
      ),
      child: Row(
        children: [
          Container(
            padding: const EdgeInsets.all(Spacing.sm),
            decoration: BoxDecoration(
              color: Theme.of(context).colorScheme.primary.withValues(alpha: 0.1),
              shape: BoxShape.circle,
            ),
            child: Icon(
              icon,
              color: Theme.of(context).colorScheme.primary,
              size: 24,
            ),
          ),
          const SizedBox(width: Spacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w600,
                      ),
                ),
                Text(
                  description,
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

  String _formatDate(DateTime date) {
    final months = [
      'janvier',
      'février',
      'mars',
      'avril',
      'mai',
      'juin',
      'juillet',
      'août',
      'septembre',
      'octobre',
      'novembre',
      'décembre'
    ];
    return '${date.day} ${months[date.month - 1]} ${date.year}';
  }
}
