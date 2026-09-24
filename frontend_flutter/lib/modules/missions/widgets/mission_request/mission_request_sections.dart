import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../services/utils/service_icon_helper.dart';
import 'mission_request_colors.dart';

// ─── App Bar ──────────────────────────────────────────────────────────────────
class MissionRequestAppBar extends StatelessWidget {
  const MissionRequestAppBar({super.key});

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 16),
      child: Row(
        children: [
          GestureDetector(
            onTap: () => Get.back(),
            child: Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: MissionRequestColors.surface,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: MissionRequestColors.subtle),
              ),
              child: const Icon(Icons.arrow_back, size: 20),
            ),
          ),
          const Expanded(
            child: Text(
              'Créer une mission',
              textAlign: TextAlign.center,
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w700,
                color: MissionRequestColors.ink,
              ),
            ),
          ),
          const SizedBox(width: 40), // Balance the back button
        ],
      ),
    );
  }
}

// ─── Section Title ────────────────────────────────────────────────────────────
class MissionRequestSectionTitle extends StatelessWidget {
  final String title;
  const MissionRequestSectionTitle({super.key, required this.title});

  @override
  Widget build(BuildContext context) {
    return Text(
      title,
      style: const TextStyle(
        fontSize: 16,
        fontWeight: FontWeight.w700,
        color: MissionRequestColors.ink,
      ),
    );
  }
}

// ─── Category Chips ───────────────────────────────────────────────────────────
class SelectServiceButton extends StatelessWidget {
  final VoidCallback onTap;

  const SelectServiceButton({super.key, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: MissionRequestColors.surface,
          borderRadius: BorderRadius.circular(12),
          border: Border.all(color: MissionRequestColors.subtle, width: 2),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: const [
            Icon(
              Icons.add_circle_outline,
              color: MissionRequestColors.primary,
              size: 24,
            ),
            SizedBox(width: 12),
            Flexible(
              child: Text(
                'Sélectionner la catégorie de service',
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                  color: MissionRequestColors.primary,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class SelectedServiceCard extends StatelessWidget {
  final String category;
  final VoidCallback onChangeTap;

  const SelectedServiceCard({
    super.key,
    required this.category,
    required this.onChangeTap,
  });

  @override
  Widget build(BuildContext context) {
    final color = ServiceIconHelper.getSectorColor(category);
    final icon = ServiceIconHelper.getSectorIcon(category);

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: color.withValues(alpha: 0.3), width: 1.5),
      ),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: color,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: Colors.white, size: 24),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Catégorie sélectionnée',
                  style: TextStyle(
                    fontSize: 12,
                    color: MissionRequestColors.muted,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  category,
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: MissionRequestColors.ink,
                  ),
                ),
              ],
            ),
          ),
          // `IntrinsicWidth` : un bouton Material placé comme frère direct
          // d'un `Expanded` dans un `Row` fait planter le calcul de largeur
          // intrinsèque de RenderFlex (« BoxConstraints forces an infinite
          // width ») — bug Flutter connu dans ce contexte.
          IntrinsicWidth(
            child: TextButton(
              onPressed: onChangeTap,
              child: const Text(
                'Modifier',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: MissionRequestColors.primary,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Description Field ────────────────────────────────────────────────────────
class MissionDescriptionField extends StatelessWidget {
  final TextEditingController controller;
  const MissionDescriptionField({super.key, required this.controller});

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: MissionRequestColors.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: MissionRequestColors.subtle),
      ),
      child: TextField(
        controller: controller,
        maxLines: 6,
        maxLength: 500,
        decoration: const InputDecoration(
          hintText:
              'Veuillez fournir autant de détails que possible sur le problème...',
          hintStyle: TextStyle(color: MissionRequestColors.muted, fontSize: 14),
          border: InputBorder.none,
          contentPadding: EdgeInsets.all(16),
          counterStyle:
              TextStyle(fontSize: 12, color: MissionRequestColors.muted),
        ),
      ),
    );
  }
}

// ─── Location Card ────────────────────────────────────────────────────────────
class MissionLocationCard extends StatelessWidget {
  final String location;
  final String detail;
  final String? addressLabel;
  final VoidCallback onChangeTap;

  const MissionLocationCard({
    super.key,
    required this.location,
    required this.detail,
    this.addressLabel,
    required this.onChangeTap,
  });

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: MissionRequestColors.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: MissionRequestColors.subtle),
      ),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: addressLabel != null
                  ? const Color(0xFF24734F).withValues(alpha: 0.12)
                  : MissionRequestColors.primaryLight,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(
              addressLabel != null ? Icons.bookmark_rounded : Icons.location_on,
              color: addressLabel != null
                  ? const Color(0xFF24734F)
                  : MissionRequestColors.primary,
              size: 20,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                if (addressLabel != null && addressLabel!.isNotEmpty) ...[
                  Container(
                    margin: const EdgeInsets.only(bottom: 4),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 2,
                    ),
                    decoration: BoxDecoration(
                      color: const Color(0xFF24734F).withValues(alpha: 0.1),
                      borderRadius: BorderRadius.circular(6),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(
                          Icons.bookmark_outline,
                          size: 11,
                          color: Color(0xFF24734F),
                        ),
                        const SizedBox(width: 4),
                        Flexible(
                          child: Text(
                            addressLabel!,
                            overflow: TextOverflow.ellipsis,
                            style: const TextStyle(
                              fontSize: 11,
                              fontWeight: FontWeight.w600,
                              color: Color(0xFF24734F),
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
                Text(
                  location,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                    color: MissionRequestColors.ink,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  detail,
                  style: const TextStyle(
                    fontSize: 12,
                    color: MissionRequestColors.muted,
                  ),
                ),
              ],
            ),
          ),
          // `IntrinsicWidth` : un bouton Material placé comme frère direct
          // d'un `Expanded` dans un `Row` fait planter le calcul de largeur
          // intrinsèque de RenderFlex (« BoxConstraints forces an infinite
          // width ») — bug Flutter connu dans ce contexte.
          IntrinsicWidth(
            child: TextButton(
              onPressed: onChangeTap,
              child: const Text(
                'Modifier',
                style: TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w600,
                  color: MissionRequestColors.primary,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ─── Search Button ────────────────────────────────────────────────────────────
class MissionRequestSearchButton extends StatelessWidget {
  final VoidCallback onPressed;
  const MissionRequestSearchButton({super.key, required this.onPressed});

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: double.infinity,
      child: ElevatedButton(
        onPressed: onPressed,
        style: ElevatedButton.styleFrom(
          backgroundColor: MissionRequestColors.primary,
          foregroundColor: Colors.white,
          padding: const EdgeInsets.symmetric(vertical: 16),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: const [
            Icon(Icons.search, size: 20),
            SizedBox(width: 8),
            Text(
              'Rechercher des artisans',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600),
            ),
          ],
        ),
      ),
    );
  }
}
