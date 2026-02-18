import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/theme/app_colors.dart';
import '../../../../core/constants/spacing.dart';
import '../../../../shared/controllers/token_controller.dart';

class OfflineTokenValidationScreen extends StatefulWidget {
  const OfflineTokenValidationScreen({super.key});

  @override
  State<OfflineTokenValidationScreen> createState() =>
      _OfflineTokenValidationScreenState();
}

class _OfflineTokenValidationScreenState
    extends State<OfflineTokenValidationScreen> {
  final _tokenController = Get.find<TokenController>();
  final _codeController = TextEditingController();
  final _otpController = TextEditingController();
  final _amountController = TextEditingController();
  final _formKey = GlobalKey<FormState>();

  bool _otpSent = false;
  bool _isValidating = false;

  @override
  void dispose() {
    _codeController.dispose();
    _otpController.dispose();
    _amountController.dispose();
    super.dispose();
  }

  Future<void> _sendOtp() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isValidating = true);

    // TODO: Implement OTP sending via SMS
    await Future.delayed(const Duration(seconds: 2));

    setState(() {
      _isValidating = false;
      _otpSent = true;
    });

    Get.snackbar(
      'OTP envoyé',
      'Un code de validation a été envoyé par SMS',
      backgroundColor: AppColors.success,
      colorText: Colors.white,
    );
  }

  Future<void> _validateWithOtp() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isValidating = true);

    // TODO: Implement OTP validation
    await Future.delayed(const Duration(seconds: 2));

    setState(() => _isValidating = false);

    Get.snackbar(
      'Validation réussie',
      'Le jeton a été validé avec succès',
      backgroundColor: AppColors.success,
      colorText: Colors.white,
    );

    Get.back();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Validation hors ligne'),
        backgroundColor: AppColors.warning,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(Spacing.lg),
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              // Info Card
              Card(
                color: AppColors.warning.withOpacity(0.1),
                child: Padding(
                  padding: const EdgeInsets.all(Spacing.md),
                  child: Row(
                    children: [
                      const Icon(Icons.info_outline, color: AppColors.warning),
                      const SizedBox(width: Spacing.md),
                      Expanded(
                        child: Text(
                          'Mode hors ligne: La validation se fera par SMS OTP',
                          style: TextStyle(
                            fontSize: 14,
                            color: Colors.grey[700],
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ),

              const SizedBox(height: Spacing.xl),

              // Token Code Input
              TextFormField(
                controller: _codeController,
                decoration: const InputDecoration(
                  labelText: 'Code du jeton',
                  hintText: 'Entrez le code du jeton',
                  prefixIcon: Icon(Icons.qr_code),
                  border: OutlineInputBorder(),
                ),
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return 'Code requis';
                  }
                  return null;
                },
                enabled: !_otpSent,
              ),

              const SizedBox(height: Spacing.md),

              // Amount Input
              TextFormField(
                controller: _amountController,
                decoration: const InputDecoration(
                  labelText: 'Montant (FCFA)',
                  hintText: 'Montant à valider',
                  prefixIcon: Icon(Icons.attach_money),
                  border: OutlineInputBorder(),
                ),
                keyboardType: TextInputType.number,
                validator: (value) {
                  if (value == null || value.isEmpty) {
                    return 'Montant requis';
                  }
                  final amount = double.tryParse(value);
                  if (amount == null || amount <= 0) {
                    return 'Montant invalide';
                  }
                  return null;
                },
                enabled: !_otpSent,
              ),

              const SizedBox(height: Spacing.xl),

              // Send OTP Button
              if (!_otpSent)
                ElevatedButton.icon(
                  onPressed: _isValidating ? null : _sendOtp,
                  icon: _isValidating
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(Icons.sms),
                  label: Text(_isValidating ? 'Envoi...' : 'Envoyer OTP'),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.info,
                    padding: const EdgeInsets.all(Spacing.md),
                  ),
                ),

              // OTP Input and Validate
              if (_otpSent) ...[
                TextFormField(
                  controller: _otpController,
                  decoration: const InputDecoration(
                    labelText: 'Code OTP',
                    hintText: 'Entrez le code reçu par SMS',
                    prefixIcon: Icon(Icons.lock),
                    border: OutlineInputBorder(),
                  ),
                  keyboardType: TextInputType.number,
                  maxLength: 6,
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'Code OTP requis';
                    }
                    if (value.length != 6) {
                      return 'Code OTP invalide';
                    }
                    return null;
                  },
                ),

                const SizedBox(height: Spacing.md),

                ElevatedButton.icon(
                  onPressed: _isValidating ? null : _validateWithOtp,
                  icon: _isValidating
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: Colors.white,
                          ),
                        )
                      : const Icon(Icons.check_circle),
                  label: Text(
                    _isValidating ? 'Validation...' : 'Valider le jeton',
                  ),
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.success,
                    padding: const EdgeInsets.all(Spacing.md),
                  ),
                ),

                const SizedBox(height: Spacing.md),

                TextButton(
                  onPressed: _sendOtp,
                  child: const Text('Renvoyer le code OTP'),
                ),
              ],

              const SizedBox(height: Spacing.xl),

              // Help Text
              Text(
                'Le code OTP sera envoyé au numéro de l\'artisan. Assurez-vous d\'avoir une connexion réseau pour recevoir le SMS.',
                style: TextStyle(fontSize: 12, color: Colors.grey[600]),
                textAlign: TextAlign.center,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
