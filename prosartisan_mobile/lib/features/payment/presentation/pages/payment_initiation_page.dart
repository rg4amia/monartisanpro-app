import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/theme/app_spacing.dart';
import '../../../../core/theme/app_typography.dart';
import '../../../../shared/widgets/cards/info_card.dart';
import '../controllers/payment_controller.dart';
import '../widgets/mobile_money_option_card.dart';
import '../widgets/payment_amount_display.dart';

/// Payment initiation screen with mobile money options
///
/// Requirements: 4.1, 15.2
class PaymentInitiationPage extends StatelessWidget {
  final String missionId;
  final String devisId;
  final int totalAmountCentimes;

  const PaymentInitiationPage({
    super.key,
    required this.missionId,
    required this.devisId,
    required this.totalAmountCentimes,
  });

  @override
  Widget build(BuildContext context) {
    final PaymentController controller = Get.put(PaymentController());

    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(
          'Paiement',
          style: AppTypography.headingMedium.copyWith(
            color: AppColors.textLight,
          ),
        ),
        backgroundColor: AppColors.primary,
        foregroundColor: AppColors.textLight,
        elevation: 0,
      ),
      body: Obx(() {
        if (controller.isLoading.value) {
          return Center(
            child: CircularProgressIndicator(color: AppColors.primary),
          );
        }

        return SingleChildScrollView(
          padding: EdgeInsets.all(AppSpacing.md),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Payment amount display
              PaymentAmountDisplay(totalAmountCentimes: totalAmountCentimes),

              SizedBox(height: AppSpacing.lg),

              // Payment method selection
              Text(
                'Choisissez votre méthode de paiement',
                style: AppTypography.headingSmall.copyWith(
                  color: AppColors.textPrimary,
                ),
              ),

              SizedBox(height: AppSpacing.md),

              // Mobile Money options
              MobileMoneyOptionCard(
                provider: 'Wave',
                icon: Icons.waves,
                color: AppColors.info,
                onTap: () => _initiatePayment(controller, 'wave'),
              ),

              SizedBox(height: AppSpacing.sm),

              MobileMoneyOptionCard(
                provider: 'Orange Money',
                icon: Icons.phone_android,
                color: AppColors.warning,
                onTap: () => _initiatePayment(controller, 'orange'),
              ),

              SizedBox(height: AppSpacing.sm),

              MobileMoneyOptionCard(
                provider: 'MTN Mobile Money',
                icon: Icons.phone_iphone,
                color: AppColors.accent,
                onTap: () => _initiatePayment(controller, 'mtn'),
              ),

              SizedBox(height: AppSpacing.xl),

              // Security notice
              InfoCard(
                title: 'Sécurité des fonds',
                subtitle:
                    'Vos fonds seront sécurisés dans un compte séquestre jusqu\'à la validation des travaux.',
                icon: Icons.security,
                backgroundColor: AppColors.info.withValues(alpha: 0.1),
                borderColor: AppColors.info,
              ),
            ],
          ),
        );
      }),
    );
  }

  void _initiatePayment(PaymentController controller, String provider) {
    controller.initiateEscrowPayment(
      missionId: missionId,
      devisId: devisId,
      totalAmountCentimes: totalAmountCentimes,
      provider: provider,
    );
  }
}
