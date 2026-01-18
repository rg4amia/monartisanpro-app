import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';
import 'package:qr_flutter/qr_flutter.dart';
import '../controllers/jeton_controller.dart';
import '../widgets/jeton_info_card.dart';
import '../widgets/jeton_status_badge.dart';

/// Jeton display screen for artisans (show code and QR)
///
/// Requirements: 5.1, 5.2
class JetonDisplayPage extends StatelessWidget {
  final String jetonId;

  const JetonDisplayPage({Key? key, required this.jetonId}) : super(key: key);

  @override
  Widget build(BuildContext context) {
    final JetonController controller = Get.put(JetonController());

    // Load jeton details when page opens
    WidgetsBinding.instance.addPostFrameCallback((_) {
      controller.loadJetonDetails(jetonId);
    });

    return Scaffold(
      appBar: AppBar(
        title: const Text('Jeton Matériel'),
        backgroundColor: Theme.of(context).primaryColor,
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => controller.loadJetonDetails(jetonId),
          ),
        ],
      ),
      body: Obx(() {
        if (controller.isLoading.value) {
          return const Center(child: CircularProgressIndicator());
        }

        final jeton = controller.currentJeton.value;
        if (jeton == null) {
          return const Center(child: Text('Jeton non trouvé'));
        }

        return SingleChildScrollView(
          padding: const EdgeInsets.all(16.0),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              // Jeton status
              JetonStatusBadge(status: jeton.status),

              const SizedBox(height: 24),

              // QR Code
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(12),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.grey.withOpacity(0.2),
                      spreadRadius: 2,
                      blurRadius: 8,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: Column(
                  children: [
                    QrImage(
                      data: jeton.qrCodeData,
                      version: QrVersions.auto,
                      size: 200.0,
                      backgroundColor: Colors.white,
                    ),
                    const SizedBox(height: 16),
                    Text(
                      'Code: ${jeton.code}',
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(
                            fontWeight: FontWeight.bold,
                            fontFamily: 'monospace',
                          ),
                    ),
                    const SizedBox(height: 8),
                    ElevatedButton.icon(
                      onPressed: () => _copyToClipboard(jeton.code),
                      icon: const Icon(Icons.copy, size: 16),
                      label: const Text('Copier le code'),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: Colors.grey.shade200,
                        foregroundColor: Colors.black87,
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 24),

              // Jeton information
              JetonInfoCard(jeton: jeton),

              const SizedBox(height: 24),

              // Expiration warning
              if (jeton.isExpired)
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.red.shade50,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: Colors.red.shade200),
                  ),
                  child: Row(
                    children: [
                      Icon(Icons.warning, color: Colors.red.shade600),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          'Ce jeton a expiré. Les fonds non utilisés ont été retournés au séquestre.',
                          style: TextStyle(
                            color: Colors.red.shade800,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ),
                    ],
                  ),
                )
              else
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.green.shade50,
                    borderRadius: BorderRadius.circular(8),
                    border: Border.all(color: Colors.green.shade200),
                  ),
                  child: Row(
                    children: [
                      Icon(Icons.info, color: Colors.green.shade600),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          'Présentez ce code QR ou le code à votre fournisseur pour acheter des matériaux.',
                          style: TextStyle(color: Colors.green.shade800),
                        ),
                      ),
                    ],
                  ),
                ),

              const SizedBox(height: 32),

              // Generate new jeton button (if current is expired or fully used)
              if (jeton.isExpired || jeton.status == 'FULLY_USED')
                SizedBox(
                  width: double.infinity,
                  child: ElevatedButton.icon(
                    onPressed: () => controller.generateNewJeton(),
                    icon: const Icon(Icons.add),
                    label: const Text('Générer un nouveau jeton'),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: Theme.of(context).primaryColor,
                      foregroundColor: Colors.white,
                      padding: const EdgeInsets.symmetric(vertical: 16),
                    ),
                  ),
                ),
            ],
          ),
        );
      }),
    );
  }

  void _copyToClipboard(String code) {
    Clipboard.setData(ClipboardData(text: code));
    Get.snackbar(
      'Code copié',
      'Le code du jeton a été copié dans le presse-papiers',
      snackPosition: SnackPosition.BOTTOM,
      backgroundColor: Colors.green,
      colorText: Colors.white,
      duration: const Duration(seconds: 2),
    );
  }
}
