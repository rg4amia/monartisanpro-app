import 'package:flutter/material.dart';
import 'package:get/get.dart';
import '../../../../core/controllers/language_controller.dart';
import '../../../../generated/l10n/app_localizations.dart';

class LanguageSettingsScreen extends StatelessWidget {
  const LanguageSettingsScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final languageController = Get.find<LanguageController>();
    final l10n = AppLocalizations.of(context);

    return Scaffold(
      appBar: AppBar(
        title: Text(l10n.language),
        backgroundColor: Colors.orange,
        foregroundColor: Colors.white,
        elevation: 0,
      ),
      body: Obx(
        () => ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Card(
              child: Column(
                children: [
                  ListTile(
                    leading: const Icon(Icons.language, color: Colors.orange),
                    title: Text(
                      l10n.changeLanguage,
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                    subtitle: Text(
                      languageController.getLanguageDisplayName(
                        languageController.currentLanguageCode,
                      ),
                      style: TextStyle(color: Colors.grey[600]),
                    ),
                  ),
                  const Divider(height: 1),
                  ...languageController.getAvailableLanguages().entries.map(
                    (entry) => RadioListTile<String>(
                      title: Text(entry.value),
                      subtitle: Text(
                        languageController.getLanguageDisplayName(entry.key),
                        style: TextStyle(color: Colors.grey[600], fontSize: 12),
                      ),
                      value: entry.key,
                      groupValue: languageController.currentLanguageCode,
                      activeColor: Colors.orange,
                      onChanged: (value) {
                        if (value != null) {
                          languageController.changeLanguage(value);
                        }
                      },
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 24),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const Icon(Icons.info_outline, color: Colors.orange),
                        const SizedBox(width: 8),
                        Text(
                          l10n.info,
                          style: const TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Text(
                      languageController.isFrench
                          ? 'Le français est la langue par défaut de ProSartisan. '
                                'Vous pouvez changer la langue à tout moment dans les paramètres.'
                          : 'French is the default language for ProSartisan. '
                                'You can change the language at any time in settings.',
                      style: TextStyle(color: Colors.grey[700], height: 1.4),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 24),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const Icon(
                          Icons.format_list_bulleted,
                          color: Colors.orange,
                        ),
                        const SizedBox(width: 8),
                        Text(
                          languageController.isFrench ? 'Formats' : 'Formats',
                          style: const TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w600,
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    _buildFormatExample(
                      languageController.isFrench ? 'Devise' : 'Currency',
                      languageController.formatCurrency(1000000),
                    ),
                    const SizedBox(height: 8),
                    _buildFormatExample(
                      languageController.isFrench ? 'Date' : 'Date',
                      languageController.formatDate(DateTime.now()),
                    ),
                    const SizedBox(height: 8),
                    _buildFormatExample(
                      languageController.isFrench
                          ? 'Date et heure'
                          : 'Date & Time',
                      languageController.formatDateTime(DateTime.now()),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildFormatExample(String label, String example) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          label,
          style: TextStyle(
            color: Colors.grey[700],
            fontWeight: FontWeight.w500,
          ),
        ),
        Text(
          example,
          style: const TextStyle(
            fontWeight: FontWeight.w600,
            color: Colors.orange,
          ),
        ),
      ],
    );
  }
}
