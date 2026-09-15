import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/theme/app_colors.dart';
import '../../../data/models/faq_model.dart';
import '../../../shared/widgets/loading_shimmer.dart';
import '../controllers/support_controller.dart';

class SupportScreen extends GetView<SupportController> {
  const SupportScreen({super.key});

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
          'Aide et support',
          style: TextStyle(
            color: Colors.black87,
            fontSize: 20,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      body: RefreshIndicator(
        onRefresh: () => controller.load(forceRefresh: true),
        child: Obx(() {
          if (controller.isLoading.value && controller.faqs.isEmpty) {
            return ListView(
              padding: const EdgeInsets.all(16),
              children: [LoadingShimmer.list(count: 5)],
            );
          }

          return ListView(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 32),
            children: [
              const _ContactSection(),
              const SizedBox(height: 20),
              _FaqSection(faqs: controller.faqs),
            ],
          );
        }),
      ),
    );
  }
}

class _ContactSection extends GetView<SupportController> {
  const _ContactSection();

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      final c = controller.contact.value;
      if (!c.hasWhatsapp && !c.hasPhone && !c.hasEmail) {
        return const SizedBox.shrink();
      }

      return Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(20),
          boxShadow: AppColors.cardShadow,
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Contacter le support',
              style: TextStyle(
                fontSize: 15,
                fontWeight: FontWeight.w700,
                color: AppColors.textPrimary,
              ),
            ),
            const SizedBox(height: 4),
            const Text(
              'Notre équipe vous répond 7j/7 depuis votre espace.',
              style: TextStyle(fontSize: 12, color: AppColors.textSecondary),
            ),
            const SizedBox(height: 14),
            Row(
              children: [
                if (c.hasWhatsapp)
                  Expanded(
                    child: _ContactButton(
                      icon: Icons.chat_bubble_rounded,
                      label: 'WhatsApp',
                      color: const Color(0xFF25D366),
                      onTap: controller.openWhatsapp,
                    ),
                  ),
                if (c.hasWhatsapp && (c.hasPhone || c.hasEmail))
                  const SizedBox(width: 10),
                if (c.hasPhone)
                  Expanded(
                    child: _ContactButton(
                      icon: Icons.call_rounded,
                      label: 'Appeler',
                      color: AppColors.primary,
                      onTap: controller.callSupport,
                    ),
                  ),
                if (c.hasPhone && c.hasEmail) const SizedBox(width: 10),
                if (c.hasEmail)
                  Expanded(
                    child: _ContactButton(
                      icon: Icons.mail_outline_rounded,
                      label: 'Email',
                      color: AppColors.info,
                      onTap: controller.emailSupport,
                    ),
                  ),
              ],
            ),
          ],
        ),
      );
    });
  }
}

class _ContactButton extends StatelessWidget {
  final IconData icon;
  final String label;
  final Color color;
  final VoidCallback onTap;

  const _ContactButton({
    required this.icon,
    required this.label,
    required this.color,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return Material(
      color: color.withValues(alpha: 0.1),
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        borderRadius: BorderRadius.circular(14),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(vertical: 12),
          child: Column(
            children: [
              Icon(icon, color: color, size: 22),
              const SizedBox(height: 6),
              Text(
                label,
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: color,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _FaqSection extends GetView<SupportController> {
  final List<FaqModel> faqs;

  const _FaqSection({required this.faqs});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Questions fréquentes',
          style: TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w700,
            color: AppColors.textPrimary,
          ),
        ),
        const SizedBox(height: 10),
        if (faqs.isEmpty)
          Container(
            padding: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              color: Colors.white,
              borderRadius: BorderRadius.circular(20),
              boxShadow: AppColors.cardShadow,
            ),
            child: const Center(
              child: Text(
                "Aucune question disponible pour l'instant. "
                'Contactez le support si besoin.',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 13, color: AppColors.textSecondary),
              ),
            ),
          )
        else
          ...faqs.map((faq) => _FaqTile(faq: faq)),
      ],
    );
  }
}

class _FaqTile extends GetView<SupportController> {
  final FaqModel faq;

  const _FaqTile({required this.faq});

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      final expanded = controller.expandedId.value == faq.id;
      return Container(
        margin: const EdgeInsets.only(bottom: 10),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          boxShadow: AppColors.cardShadow,
        ),
        child: Theme(
          data: Theme.of(context).copyWith(dividerColor: Colors.transparent),
          child: ExpansionTile(
            key: ValueKey(faq.id),
            initiallyExpanded: expanded,
            onExpansionChanged: (_) => controller.toggleExpanded(faq.id),
            tilePadding: const EdgeInsets.symmetric(
              horizontal: 16,
              vertical: 4,
            ),
            childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
            title: Text(
              faq.question,
              style: const TextStyle(
                fontSize: 13.5,
                fontWeight: FontWeight.w600,
                color: AppColors.textPrimary,
              ),
            ),
            subtitle: faq.categorie != null && faq.categorie!.isNotEmpty
                ? Padding(
                    padding: const EdgeInsets.only(top: 4),
                    child: Text(
                      faq.categorie!,
                      style: const TextStyle(
                        fontSize: 11,
                        color: AppColors.textMuted,
                      ),
                    ),
                  )
                : null,
            children: [
              Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  faq.reponse,
                  style: const TextStyle(
                    fontSize: 12.5,
                    color: AppColors.textSecondary,
                    height: 1.4,
                  ),
                ),
              ),
            ],
          ),
        ),
      );
    });
  }
}
