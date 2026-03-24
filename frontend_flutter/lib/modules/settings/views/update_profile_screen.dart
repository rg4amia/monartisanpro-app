import 'dart:io';
import 'package:flutter/material.dart';
import 'package:get/get.dart';
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
          if (!controller.isArtisan.value) {
            return const SizedBox.shrink();
          }

          return Column(
            children: [
              const SizedBox(height: 24),
              _NightModeCard(controller: controller),
            ],
          );
        }),
      ],
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
    return Obx(() => Container(
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
                    : (value) =>
                        controller.nightInterventionsEnabled.value = value,
              ),
            ],
          ),
        ));
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
      child: Obx(() => ElevatedButton(
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
          )),
    );
  }
}
