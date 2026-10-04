import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/notification_model.dart';
import '../controllers/notification_preferences_controller.dart';

/// Réglages des notifications : ce que l'utilisateur reçoit, rubrique par
/// rubrique, et son accord aux offres et nouveautés.
class NotificationPreferencesScreen
    extends GetView<NotificationPreferencesController> {
  const NotificationPreferencesScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.arrow_back, color: Colors.black87),
          onPressed: () => Get.back(),
        ),
        title: const Text(
          'Préférences de notification',
          style: TextStyle(
            color: Colors.black87,
            fontSize: 18,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      body: Obx(() {
        if (controller.isLoading.value && !controller.isLoaded.value) {
          return const Center(
            child: CircularProgressIndicator(
              valueColor: AlwaysStoppedAnimation<Color>(AppColors.primary),
            ),
          );
        }

        // Jamais d'interrupteurs par défaut à la place d'une réponse du
        // serveur : ils afficheraient des réglages inventés.
        if (!controller.isLoaded.value) {
          return _LoadError(
            message: controller.errorMsg.value ??
                'Vos préférences n\'ont pas pu être chargées.',
            onRetry: controller.load,
          );
        }

        return ListView(
          padding: const EdgeInsets.all(16),
          children: [
            if (controller.errorMsg.value != null) ...[
              _ErrorBanner(message: controller.errorMsg.value!),
              const SizedBox(height: 12),
            ],
            const _SectionTitle('Offres et nouveautés'),
            _Card(
              child: _SwitchRow(
                title: 'Recevoir les offres et nouveautés',
                subtitle:
                    'Promotions et nouveautés de ProsArtisan, en notification '
                    'uniquement. Désactivé tant que vous ne l\'acceptez pas.',
                value: controller.promotionalPush.value,
                onChanged: controller.isSaving.value
                    ? null
                    : controller.setPromotionalPush,
              ),
            ),
            const SizedBox(height: 20),
            const _SectionTitle('Par rubrique'),
            if (controller.domains.isEmpty)
              const _Card(
                child: Text(
                  'Aucune rubrique à régler pour votre profil.',
                  style: TextStyle(color: Colors.black54, fontSize: 14),
                ),
              )
            else
              for (final domain in controller.domains) ...[
                _DomainCard(domain: domain, controller: controller),
                const SizedBox(height: 10),
              ],
            const SizedBox(height: 10),
            Text(
              'Les paiements reçus, les alertes de sécurité, l\'ouverture d\'un '
              'litige et les codes de validation vous parviennent toujours. '
              'Une notification coupée reste visible dans la liste de '
              'l\'application.',
              style: TextStyle(
                color: Colors.grey[600],
                fontSize: 12,
                height: 1.5,
              ),
            ),
          ],
        );
      }),
    );
  }
}

class _DomainCard extends StatelessWidget {
  const _DomainCard({required this.domain, required this.controller});

  final NotificationDomainPreference domain;
  final NotificationPreferencesController controller;

  @override
  Widget build(BuildContext context) {
    final saving = controller.isSaving.value;

    return _Card(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            domain.label,
            style: const TextStyle(
              color: Colors.black87,
              fontSize: 15,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 6),
          _SwitchRow(
            key: Key('push_${domain.key}'),
            title: 'Notifications',
            subtitle: domain.pushEditable
                ? (domain.essential
                    ? 'Les messages essentiels de cette rubrique restent envoyés.'
                    : null)
                : 'Messages essentiels : toujours envoyés.',
            value: domain.push,
            onChanged: domain.pushEditable && !saving
                ? (value) => controller.setDomainPush(domain.key, value)
                : null,
          ),
          if (domain.smsEditable)
            _SwitchRow(
              key: Key('sms_${domain.key}'),
              title: 'SMS',
              value: domain.sms,
              onChanged: saving
                  ? null
                  : (value) => controller.setDomainSms(domain.key, value),
            ),
        ],
      ),
    );
  }
}

class _SwitchRow extends StatelessWidget {
  const _SwitchRow({
    super.key,
    required this.title,
    this.subtitle,
    required this.value,
    required this.onChanged,
  });

  final String title;
  final String? subtitle;
  final bool value;
  final ValueChanged<bool>? onChanged;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: const TextStyle(color: Colors.black87, fontSize: 14),
              ),
              if (subtitle != null) ...[
                const SizedBox(height: 2),
                Text(
                  subtitle!,
                  style: TextStyle(
                    color: Colors.grey[600],
                    fontSize: 12,
                    height: 1.4,
                  ),
                ),
              ],
            ],
          ),
        ),
        const SizedBox(width: 12),
        Switch(
          value: value,
          onChanged: onChanged,
          activeThumbColor: AppColors.primary,
        ),
      ],
    );
  }
}

class _SectionTitle extends StatelessWidget {
  const _SectionTitle(this.label);

  final String label;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(left: 4, bottom: 8),
      child: Text(
        label.toUpperCase(),
        style: TextStyle(
          color: Colors.grey[600],
          fontSize: 12,
          fontWeight: FontWeight.w700,
          letterSpacing: 0.8,
        ),
      ),
    );
  }
}

class _Card extends StatelessWidget {
  const _Card({required this.child});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: child,
    );
  }
}

class _ErrorBanner extends StatelessWidget {
  const _ErrorBanner({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.dangerSoft,
        borderRadius: BorderRadius.circular(12),
      ),
      child: Text(
        message,
        style: const TextStyle(color: AppColors.danger, fontSize: 13),
      ),
    );
  }
}

class _LoadError extends StatelessWidget {
  const _LoadError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(32),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(Icons.cloud_off_outlined, size: 56, color: Colors.grey[400]),
            const SizedBox(height: 16),
            Text(
              message,
              textAlign: TextAlign.center,
              style: TextStyle(color: Colors.grey[700], fontSize: 14),
            ),
            const SizedBox(height: 16),
            OutlinedButton(onPressed: onRetry, child: const Text('Réessayer')),
          ],
        ),
      ),
    );
  }
}
