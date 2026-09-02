import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'app_logger.dart';

/// Écran de test pour les notifications Telegram
/// Accessible via Get.to(() => TelegramTestScreen())
class TelegramTestScreen extends StatelessWidget {
  const TelegramTestScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Test Telegram Logger'),
        backgroundColor: const Color(0xFF5B5FEF),
        foregroundColor: Colors.white,
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          const Text(
            'Testez les notifications Telegram',
            style: TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Assurez-vous d\'avoir configuré votre bot token et chat ID dans env_config.dart',
            style: TextStyle(color: Colors.grey),
          ),
          const SizedBox(height: 24),
          _buildTestButton(
            icon: Icons.info_outline,
            label: 'Test Info',
            color: Colors.blue,
            onPressed: () async {
              await AppLogger.info(
                'Ceci est un test de notification info',
                context: 'Test Screen',
              );
              _showSuccess('Info envoyée');
            },
          ),
          _buildTestButton(
            icon: Icons.warning_amber,
            label: 'Test Warning',
            color: Colors.orange,
            onPressed: () async {
              await AppLogger.warning(
                'Ceci est un test de warning',
                context: 'Test Screen',
              );
              _showSuccess('Warning envoyé');
            },
          ),
          _buildTestButton(
            icon: Icons.error_outline,
            label: 'Test Error',
            color: Colors.red,
            onPressed: () async {
              await AppLogger.error(
                'Ceci est un test d\'erreur',
                error: Exception('Test exception'),
                context: 'Test Screen',
              );
              _showSuccess('Erreur envoyée');
            },
          ),
          _buildTestButton(
            icon: Icons.event,
            label: 'Test Event',
            color: Colors.green,
            onPressed: () async {
              await AppLogger.event(
                'test_event',
                data: {
                  'user_id': 123,
                  'action': 'button_click',
                  'timestamp': DateTime.now().toIso8601String(),
                },
                context: 'Test Screen',
              );
              _showSuccess('Événement envoyé');
            },
          ),
          const Divider(height: 32),
          const Text(
            'Tests métier',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.bold,
            ),
          ),
          const SizedBox(height: 16),
          _buildTestButton(
            icon: Icons.account_balance_wallet,
            label: 'Test Transaction',
            color: const Color(0xFF5B5FEF),
            onPressed: () async {
              await AppLogger.transaction(
                'acompte',
                montant: 50000,
                data: {
                  'mission_id': 456,
                  'provider': 'wave',
                },
              );
              _showSuccess('Transaction loggée');
            },
          ),
          _buildTestButton(
            icon: Icons.verified_user,
            label: 'Test KYC',
            color: const Color(0xFF4CAF50),
            onPressed: () async {
              await AppLogger.kyc(
                'submitted',
                status: 'en_attente',
                data: {
                  'user_id': 789,
                  'document_type': 'cni',
                },
              );
              _showSuccess('KYC loggé');
            },
          ),
          _buildTestButton(
            icon: Icons.work_outline,
            label: 'Test Mission',
            color: const Color(0xFFFDB750),
            onPressed: () async {
              await AppLogger.mission(
                'created',
                missionId: 123,
                data: {
                  'client_id': 111,
                  'artisan_id': 222,
                  'montant': 150000,
                },
              );
              _showSuccess('Mission loggée');
            },
          ),
          _buildTestButton(
            icon: Icons.qr_code,
            label: 'Test J-Code',
            color: const Color(0xFF9C27B0),
            onPressed: () async {
              await AppLogger.jcode(
                'generated',
                code: 'PA-1234',
                data: {
                  'mission_id': 123,
                  'montant': 50000,
                },
              );
              _showSuccess('J-Code loggé');
            },
          ),
          _buildTestButton(
            icon: Icons.location_on,
            label: 'Test Géolocalisation',
            color: const Color(0xFFE91E63),
            onPressed: () async {
              await AppLogger.geoError(
                'Permission de localisation refusée',
                error: 'Location services disabled',
              );
              _showSuccess('Erreur géo loggée');
            },
          ),
          const Divider(height: 32),
          _buildTestButton(
            icon: Icons.bug_report,
            label: 'Test Crash Simulé',
            color: Colors.red[900]!,
            onPressed: () {
              // Simule un crash
              throw Exception('Test crash - ceci est normal');
            },
          ),
        ],
      ),
    );
  }

  Widget _buildTestButton({
    required IconData icon,
    required String label,
    required Color color,
    required VoidCallback onPressed,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: ElevatedButton.icon(
        onPressed: onPressed,
        icon: Icon(icon),
        label: Text(label),
        style: ElevatedButton.styleFrom(
          backgroundColor: color,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(vertical: 16),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
        ),
      ),
    );
  }

  void _showSuccess(String message) {
    Get.snackbar(
      'Succès',
      message,
      snackPosition: SnackPosition.BOTTOM,
      backgroundColor: const Color(0xFF4CAF50),
      colorText: Colors.white,
      duration: const Duration(seconds: 2),
      margin: const EdgeInsets.all(16),
      borderRadius: 8,
    );
  }
}
