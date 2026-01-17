import 'package:flutter/material.dart';
import 'package:get/get.dart';
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
    Key? key,
    required this.missionId,
    required this.devisId,
    required this.totalAmountCentimes,
  }) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final PaymentController controller = Get.put(PaymentController());

    return Scaffold(
      appBar: AppBar(
        title: const Text('Paiement'),
        backgroundColor: Theme.of(context).primaryColor,
        foregroundColor: Colors.white,
      ),
      body: Obx(() {
        if (controller.isLoading.value) {
          return const Center(child: CircularProgressIndicator());
        }

        return SingleChildScrollView(
          padding: const EdgeInsets.all(16.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Payment amount display
              PaymentAmountDisplay(totalAmountCentimes: totalAmountCentimes),

              const SizedBox(height: 24),

              // Payment method selection
              Text(
                'Choisissez votre méthode de paiement',
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.bold,
                ),
              ),

              const SizedBox(height: 16),

              // Mobile Money options
              MobileMoneyOptionCard(
                provider: 'Wave',
                icon: Icons.waves,
                color: Colors.blue,
                onTap: () => _initiatePayment(controller, 'wave'),
              ),

              const SizedBox(height: 12),

              MobileMoneyOptionCard(
                provider: 'Orange Money',
                icon: Icons.phone_android,
                color: Colors.orange,
                onTap: () => _initiatePayment(controller, 'orange'),
              ),

              const SizedBox(height: 12),

              MobileMoneyOptionCard(
                provider: 'MTN Mobile Money',
                icon: Icons.phone_iphone,
                color: Colors.yellow.shade700,
                onTap: () => _initiatePayment(controller, 'mtn'),
              ),

              const SizedBox(height: 32),

              // Security notice
              Container(
                padding: const EdgeInsets.all(16),
                decoration: BoxDecoration(
                  color: Colors.blue.shade50,
                  borderRadius: BorderRadius.circular(8),
                  border: Border.all(color: Colors.blue.shade200),
                ),
                child: Row(
                  children: [
                    Icon(Icons.security, color: Colors.blue.shade600),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Text(
                        'Vos fonds seront sécurisés dans un compte séquestre jusqu\'à la validation des travaux.',
                        style: TextStyle(
                          color: Colors.blue.shade800,
                          fontSize: 14,
                        ),
                      ),
                    ),
                  ],
                ),
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
