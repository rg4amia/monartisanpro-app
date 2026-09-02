import 'dart:io';

import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../core/network/api_endpoints.dart';
import '../controllers/update_profile_controller.dart';

// ─── Design Tokens ────────────────────────────────────────────────────────────
abstract class _C {
  static const bg = Color(0xFFF8F9FA);
  static const surface = Colors.white;
  static const primary = Color(0xFF4F46E5);
  static const primaryLight = Color(0xFFEEF2FF);
  static const ink = Color(0xFF111827);
  static const muted = Color(0xFF6B7280);
  static const subtle = Color(0xFFE5E7EB);
}

class UpdateProfileScreen extends GetView<UpdateProfileController> {
  const UpdateProfileScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: _C.bg,
      body: SafeArea(
        child: Column(
          children: [
            _AppBar(),
            Expanded(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    _ProfileAvatar(controller: controller),
                    const SizedBox(height: 32),
                    _FormSection(controller: controller),
                  ],
                ),
              ),
            ),
            _BottomActions(controller: controller),
          ],
        ),
      ),
    );
  }
}

// ─── App Bar ──────────────────────────────────────────────────────────────────
class _AppBar extends StatelessWidget {
  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 20),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          GestureDetector(
            onTap: () => Get.back(),
            child: Container(
              width: 40,
              height: 40,
              decoration: BoxDecoration(
                color: _C.surface,
                borderRadius: BorderRadius.circular(12),
                border: Border.all(color: _C.subtle),
              ),
              child: const Icon(Icons.arrow_back, size: 20),
            ),
          ),
          const Text(
            'Modifier le profil',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w700,
              color: _C.ink,
            ),
          ),
          const SizedBox(width: 40),
        ],
      ),
    );
  }
}

// ─── Profile Avatar ───────────────────────────────────────────────────────────
class _ProfileAvatar extends StatelessWidget {
  final UpdateProfileController controller;
  const _ProfileAvatar({required this.controller});

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Stack(
        children: [
          Obx(() {
            final imagePath = controller.profileImagePath.value;
            return Container(
              width: 120,
              height: 120,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                border: Border.all(color: _C.subtle, width: 3),
                color: _C.primaryLight,
                image: imagePath != null
                    ? DecorationImage(
                        image: FileImage(File(imagePath)),
                        fit: BoxFit.cover,
                      )
                    : null,
              ),
              child: imagePath == null
                  ? Center(
                      child: Text(
                        _getInitial(controller.nameController.text),
                        style: const TextStyle(
                          fontSize: 48,
                          fontWeight: FontWeight.w700,
                          color: _C.primary,
                        ),
                      ),
                    )
                  : null,
            );
          }),
          Positioned(
            bottom: 0,
            right: 0,
            child: GestureDetector(
              onTap: controller.pickProfileImage,
              child: Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: _C.primary,
                  shape: BoxShape.circle,
                  border: Border.all(color: _C.surface, width: 3),
                ),
                child:
                    const Icon(Icons.camera_alt, color: Colors.white, size: 16),
              ),
            ),
          ),
        ],
      ),
    );
  }

  String _getInitial(String name) {
    if (name.isEmpty) return '?';
    return name[0].toUpperCase();
  }
}

// ─── Form Section ─────────────────────────────────────────────────────────────
class _FormSection extends StatelessWidget {
  final UpdateProfileController controller;
  const _FormSection({required this.controller});

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Informations personnelles',
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.w700,
            color: _C.ink,
          ),
        ),
        const SizedBox(height: 16),
        _InputField(
          label: 'Nom complet',
          controller: controller.nameController,
          icon: Icons.person_outline,
          hint: 'Entrez votre nom complet',
        ),
        const SizedBox(height: 16),
        _InputField(
          label: 'Numéro de téléphone',
          controller: controller.phoneController,
          icon: Icons.phone_outlined,
          hint: 'Votre numéro de téléphone',
          enabled: false,
        ),
        const SizedBox(height: 16),
        _InputField(
          label: 'E-mail (Optionnel)',
          controller: controller.emailController,
          icon: Icons.email_outlined,
          hint: 'votre.email@exemple.com',
          keyboardType: TextInputType.emailAddress,
        ),
        Obx(() {
          if (!controller.canEditLocation.value) {
            return const SizedBox.shrink();
          }

          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const SizedBox(height: 24),
              const Text(
                'Localisation géographique',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w700,
                  color: _C.ink,
                ),
              ),
              const SizedBox(height: 12),
              _LocationCard(controller: controller),
            ],
          );
        }),
        const SizedBox(height: 24),
        _MobileMoneyPayoutCard(controller: controller),
        Obx(() {
          if (!controller.isArtisan.value) {
            return const SizedBox.shrink();
          }

          return Column(
            children: [
              const SizedBox(height: 24),
              _CategorySelectionCard(controller: controller),
              const SizedBox(height: 24),
              _CnmciCard(controller: controller),
              const SizedBox(height: 24),
              _NightModeCard(controller: controller),
            ],
          );
        }),
      ],
    );
  }
}

class _CategorySelectionCard extends StatelessWidget {
  final UpdateProfileController controller;

  const _CategorySelectionCard({required this.controller});

  @override
  Widget build(BuildContext context) {
    final hasCategory = controller.selectedSectorName.value != null;
    final categoryText = hasCategory
        ? "${controller.selectedSectorName.value} — ${controller.selectedTradeName.value ?? 'Non spécifié'}"
        : 'Aucune catégorie définie';

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _C.subtle),
      ),
      child: Column(
        children: [
          Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: hasCategory
                      ? const Color(0xFFEEF2FF)
                      : const Color(0xFFF9FAFB),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(
                  Icons.build_circle_outlined,
                  color: hasCategory ? _C.primary : _C.muted,
                  size: 22,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      hasCategory
                          ? 'Catégorie active'
                          : 'Catégorie non configurée',
                      style: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                        color: _C.ink,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      categoryText,
                      style: const TextStyle(
                        fontSize: 13,
                        color: _C.muted,
                        height: 1.35,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          OutlinedButton.icon(
            onPressed: controller.selectCategoryAndSubcategory,
            icon: const Icon(Icons.category_outlined, size: 18),
            label: Text(
              hasCategory ? 'Modifier ma catégorie' : 'Choisir ma catégorie',
            ),
            style: OutlinedButton.styleFrom(
              minimumSize: const Size(double.infinity, 48),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(10),
              ),
              side: const BorderSide(color: _C.primary),
              foregroundColor: _C.primary,
            ),
          ),
        ],
      ),
    );
  }
}

class _LocationCard extends StatelessWidget {
  final UpdateProfileController controller;

  const _LocationCard({required this.controller});

  @override
  Widget build(BuildContext context) {
    final isSet = controller.selectedLatitude.value != null &&
        controller.selectedLongitude.value != null;

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _C.subtle),
      ),
      child: Column(
        children: [
          Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color:
                      isSet ? const Color(0xFFEEF2FF) : const Color(0xFFF9FAFB),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(
                  Icons.my_location,
                  color: isSet ? _C.primary : _C.muted,
                  size: 22,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      isSet ? 'Position configurée' : 'Emplacement non défini',
                      style: const TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                        color: _C.ink,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      controller.selectedAddress.value,
                      style: const TextStyle(
                        fontSize: 13,
                        color: _C.muted,
                        height: 1.35,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          OutlinedButton.icon(
            onPressed: controller.selectLocationOnMap,
            icon: const Icon(Icons.map_outlined, size: 18),
            label: Text(
              isSet
                  ? 'Modifier ma position'
                  : 'Définir ma position sur la carte',
            ),
            style: OutlinedButton.styleFrom(
              minimumSize: const Size(double.infinity, 48),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(10),
              ),
              side: const BorderSide(color: _C.primary),
              foregroundColor: _C.primary,
            ),
          ),
        ],
      ),
    );
  }
}

class _InputField extends StatelessWidget {
  final String label;
  final TextEditingController controller;
  final IconData icon;
  final String hint;
  final bool enabled;
  final TextInputType? keyboardType;

  const _InputField({
    required this.label,
    required this.controller,
    required this.icon,
    required this.hint,
    this.enabled = true,
    this.keyboardType,
  });

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w600,
            color: _C.ink,
          ),
        ),
        const SizedBox(height: 8),
        Container(
          decoration: BoxDecoration(
            color: enabled ? _C.surface : _C.bg,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: _C.subtle),
          ),
          child: TextField(
            controller: controller,
            enabled: enabled,
            keyboardType: keyboardType,
            style: TextStyle(
              fontSize: 15,
              color: enabled ? _C.ink : _C.muted,
            ),
            decoration: InputDecoration(
              hintText: hint,
              hintStyle: const TextStyle(color: _C.muted),
              prefixIcon: Icon(icon, color: _C.muted, size: 20),
              border: InputBorder.none,
              contentPadding: const EdgeInsets.symmetric(
                horizontal: 16,
                vertical: 16,
              ),
            ),
          ),
        ),
      ],
    );
  }
}

class _NightModeCard extends StatelessWidget {
  final UpdateProfileController controller;

  const _NightModeCard({required this.controller});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _C.subtle),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: _C.primaryLight,
              borderRadius: BorderRadius.circular(12),
            ),
            child: const Icon(
              Icons.nightlight_round,
              color: _C.primary,
              size: 22,
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const Text(
                  'Interventions de nuit',
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: _C.ink,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  controller.isProfileLoading.value
                      ? 'Chargement de votre disponibilité actuelle...'
                      : 'Activez ce mode si vous acceptez les demandes entre 18h et 7h.',
                  style: const TextStyle(
                    fontSize: 13,
                    color: _C.muted,
                    height: 1.35,
                  ),
                ),
              ],
            ),
          ),
          Switch(
            value: controller.nightInterventionsEnabled.value,
            activeThumbColor: _C.primary,
            onChanged: controller.isProfileLoading.value
                ? null
                : (value) => controller.nightInterventionsEnabled.value = value,
          ),
        ],
      ),
    );
  }
}

// ─── Bottom Actions ───────────────────────────────────────────────────────────
class _BottomActions extends StatelessWidget {
  final UpdateProfileController controller;
  const _BottomActions({required this.controller});

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: _C.surface,
        border: Border(top: BorderSide(color: _C.subtle)),
        boxShadow: [
          BoxShadow(
            color: _C.ink.withValues(alpha: 0.04),
            blurRadius: 8,
            offset: const Offset(0, -2),
          ),
        ],
      ),
      child: Obx(
        () => ElevatedButton(
          onPressed:
              controller.isLoading.value || controller.isProfileLoading.value
                  ? null
                  : controller.updateProfile,
          style: ElevatedButton.styleFrom(
            backgroundColor: _C.primary,
            foregroundColor: Colors.white,
            minimumSize: const Size(double.infinity, 56),
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(12),
            ),
            elevation: 0,
          ),
          child: controller.isLoading.value || controller.isProfileLoading.value
              ? const SizedBox(
                  width: 20,
                  height: 20,
                  child: CircularProgressIndicator(
                    strokeWidth: 2,
                    valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                  ),
                )
              : const Text(
                  'Enregistrer les modifications',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w600,
                  ),
                ),
        ),
      ),
    );
  }
}

class _CnmciCard extends StatelessWidget {
  final UpdateProfileController controller;
  const _CnmciCard({required this.controller});

  @override
  Widget build(BuildContext context) {
    final status = controller.cnmciStatus.value;
    final localPath = controller.cnmciCardImagePath.value;
    final remoteUrl = controller.cnmciCardUrl.value;

    Color badgeBg = const Color(0xFFF3F4F6);
    Color badgeText = const Color(0xFF4B5563);
    String label = 'Non renseigné';
    IconData icon = Icons.info_outline;

    if (status == 'en_attente') {
      badgeBg = const Color(0xFFFEF3C7);
      badgeText = const Color(0xFFD97706);
      label = 'Validation en cours';
      icon = Icons.hourglass_empty;
    } else if (status == 'valide') {
      badgeBg = const Color(0xFFD1FAE5);
      badgeText = const Color(0xFF059669);
      label = 'Artisan Certifié CNMCI';
      icon = Icons.verified;
    } else if (status == 'rejete') {
      badgeBg = const Color(0xFFFEE2E2);
      badgeText = const Color(0xFFDC2626);
      label = 'Certification rejetée';
      icon = Icons.cancel_outlined;
    }

    Widget imageWidget;
    if (localPath != null) {
      imageWidget = Image.file(
        File(localPath),
        fit: BoxFit.cover,
        width: double.infinity,
        height: 160,
      );
    } else if (remoteUrl != null && remoteUrl.isNotEmpty) {
      final serverBase =
          ApiEndpoints.baseUrl.replaceAll('/api/v1', '').replaceAll('/v1', '');
      final fullUrl =
          remoteUrl.startsWith('http') ? remoteUrl : '$serverBase$remoteUrl';
      imageWidget = Image.network(
        fullUrl,
        fit: BoxFit.cover,
        width: double.infinity,
        height: 160,
        errorBuilder: (context, error, stackTrace) {
          return const Center(child: Icon(Icons.broken_image, size: 40));
        },
      );
    } else {
      imageWidget = const SizedBox.shrink();
    }

    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: _C.surface,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _C.subtle),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: const BoxDecoration(
                  color: Color(0xFFFDF8EE),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.card_membership_outlined,
                  color: Color(0xFFD97706),
                  size: 22,
                ),
              ),
              const SizedBox(width: 14),
              const Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Affiliation CNMCI',
                      style: TextStyle(
                        fontSize: 15,
                        fontWeight: FontWeight.w700,
                        color: _C.ink,
                      ),
                    ),
                    SizedBox(height: 2),
                    Text(
                      'Chambre Nationale des Métiers de CI',
                      style: TextStyle(
                        fontSize: 12,
                        color: _C.muted,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          if (status != 'non_renseigne')
            Padding(
              padding: const EdgeInsets.only(bottom: 16),
              child: Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                decoration: BoxDecoration(
                  color: badgeBg,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Row(
                  children: [
                    Icon(icon, color: badgeText, size: 16),
                    const SizedBox(width: 8),
                    Expanded(
                      child: Text(
                        label,
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                          color: badgeText,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          _InputField(
            label: 'Numéro de carte artisan',
            controller: controller.cnmciNumberController,
            icon: Icons.numbers_outlined,
            hint: 'Ex: CNM-XXXXXXXX',
          ),
          const SizedBox(height: 16),
          const Text(
            'Photo de la carte artisan',
            style: TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w700,
              color: _C.ink,
            ),
          ),
          const SizedBox(height: 8),
          if (localPath == null && (remoteUrl == null || remoteUrl.isEmpty))
            GestureDetector(
              onTap: controller.pickCnmciCardImage,
              child: Container(
                height: 120,
                decoration: BoxDecoration(
                  color: const Color(0xFFF9FAFB),
                  borderRadius: BorderRadius.circular(12),
                  border:
                      Border.all(color: _C.subtle, style: BorderStyle.solid),
                ),
                child: Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(
                        Icons.add_a_photo_outlined,
                        color: _C.primary,
                        size: 28,
                      ),
                      const SizedBox(height: 8),
                      const Text(
                        'Joindre la photo de la carte',
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w600,
                          color: _C.primary,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            )
          else
            ClipRRect(
              borderRadius: BorderRadius.circular(12),
              child: Stack(
                children: [
                  imageWidget,
                  Positioned(
                    top: 8,
                    right: 8,
                    child: GestureDetector(
                      onTap: controller.pickCnmciCardImage,
                      child: Container(
                        padding: const EdgeInsets.all(6),
                        decoration: const BoxDecoration(
                          color: Colors.black54,
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(
                          Icons.edit,
                          color: Colors.white,
                          size: 16,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }
}

// ─── Mobile Money Payout Card ────────────────────────────────────────────────
class _MobileMoneyPayoutCard extends StatelessWidget {
  final UpdateProfileController controller;

  const _MobileMoneyPayoutCard({required this.controller});

  @override
  Widget build(BuildContext context) {
    return Obx(() {
      final isPro = controller.isProActor.value;
      final title =
          isPro ? 'Reversement Mobile Money' : 'Moyen de paiement Mobile Money';
      final subtitle = isPro
          ? 'Compte de réception de vos gains'
          : 'Paiements de devis & remboursements';
      final infoMessage = isPro
          ? 'Ce numéro sera automatiquement crédité par ProsArtisan dès validation de vos jalons, livraisons ou retraits de matériel.'
          : 'Ce numéro Mobile Money sera utilisé par défaut pour valider vos règlements et recevoir immédiatement vos remboursements en cas de litige.';

      return Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: _C.surface,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: _C.subtle),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 40,
                  height: 40,
                  decoration: BoxDecoration(
                    color: const Color(0xFFECFDF5),
                    borderRadius: BorderRadius.circular(10),
                  ),
                  child: Icon(
                    isPro
                        ? Icons.account_balance_wallet_rounded
                        : Icons.payments_rounded,
                    color: const Color(0xFF10B981),
                    size: 20,
                  ),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        title,
                        style: const TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                          color: _C.ink,
                        ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        subtitle,
                        style: const TextStyle(
                          fontSize: 12,
                          color: _C.muted,
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            const Text(
              'Opérateur de paiement',
              style: TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: _C.ink,
              ),
            ),
            const SizedBox(height: 8),
            Row(
              children: [
                _buildProviderChip(
                  'wave',
                  'Wave',
                  const Color(0xFF00A3FF),
                  controller.selectedPaymentProvider.value == 'wave',
                ),
                const SizedBox(width: 8),
                _buildProviderChip(
                  'orange_money',
                  'Orange',
                  const Color(0xFFFF7900),
                  controller.selectedPaymentProvider.value == 'orange_money',
                ),
                const SizedBox(width: 8),
                _buildProviderChip(
                  'mtn_money',
                  'MTN',
                  const Color(0xFFFFCC00),
                  controller.selectedPaymentProvider.value == 'mtn_money',
                ),
                const SizedBox(width: 8),
                _buildProviderChip(
                  'moov_money',
                  'Moov',
                  const Color(0xFF005BA6),
                  controller.selectedPaymentProvider.value == 'moov_money',
                ),
              ],
            ),
            const SizedBox(height: 16),
            _InputField(
              label: 'Numéro Mobile Money (10 chiffres)',
              controller: controller.paymentPhoneController,
              icon: Icons.phone_android_rounded,
              hint: 'Ex: 0701020304',
              keyboardType: TextInputType.phone,
            ),
            const SizedBox(height: 8),
            Container(
              padding: const EdgeInsets.all(10),
              decoration: BoxDecoration(
                color: const Color(0xFFF9FAFB),
                borderRadius: BorderRadius.circular(10),
                border: Border.all(color: const Color(0xFFE5E7EB)),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(
                    Icons.info_outline_rounded,
                    size: 16,
                    color: _C.muted,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      infoMessage,
                      style: const TextStyle(
                        fontSize: 11,
                        color: _C.muted,
                        height: 1.3,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      );
    });
  }

  Widget _buildProviderChip(
    String providerKey,
    String label,
    Color color,
    bool isSelected,
  ) {
    return Expanded(
      child: GestureDetector(
        onTap: () => controller.selectedPaymentProvider.value = providerKey,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 200),
          padding: const EdgeInsets.symmetric(vertical: 10),
          decoration: BoxDecoration(
            color: isSelected
                ? color.withValues(alpha: 0.12)
                : const Color(0xFFF3F4F6),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: isSelected ? color : Colors.transparent,
              width: 1.5,
            ),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Container(
                width: 8,
                height: 8,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  color: isSelected ? color : Colors.grey.shade400,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                label,
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: isSelected ? FontWeight.w700 : FontWeight.w500,
                  color: isSelected ? color : _C.ink,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
