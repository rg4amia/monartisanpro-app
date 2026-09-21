import 'package:flutter/material.dart';
import 'package:flutter_contacts/flutter_contacts.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';
import 'package:permission_handler/permission_handler.dart';

import '../../../core/theme/app_colors.dart';
import '../controllers/parrainage_controller.dart';

/// Ne garde que les chiffres (et un éventuel `+` initial) d'un numéro issu
/// du répertoire téléphonique : les contacts stockent souvent des espaces,
/// tirets ou parenthèses que le backend ne sait normaliser qu'après ce
/// nettoyage (`NormalizesIvorianPhone` ne retire que les espaces).
String _sanitizePhone(String raw) {
  final trimmed = raw.trim();
  final digits = trimmed.replaceAll(RegExp(r'[^0-9]'), '');
  return trimmed.startsWith('+') ? '+$digits' : digits;
}

/// Lecture défensive d'une valeur textuelle dans une Map issue du JSON API.
/// Ne jamais transtyper directement (`as String`) : une clé absente ou d'un
/// type inattendu ne doit jamais faire échouer la lecture des autres champs
/// (Règle d'or 28).
String _readString(
  Map<String, dynamic>? map,
  String key, [
  String fallback = '',
]) {
  if (map == null) return fallback;
  final value = map[key];
  if (value is String && value.isNotEmpty) return value;
  return fallback;
}

Map<String, dynamic>? _readMap(Map<String, dynamic>? map, String key) {
  if (map == null) return null;
  final value = map[key];
  if (value is Map) return value.cast<String, dynamic>();
  return null;
}

String _formatDate(String isoDate) {
  if (isoDate.isEmpty) return '';
  try {
    return DateFormat('dd/MM/yyyy').format(DateTime.parse(isoDate));
  } catch (_) {
    return '';
  }
}

class ParrainageScreen extends StatelessWidget {
  ParrainageScreen({super.key});

  final ParrainageController controller = Get.put(ParrainageController());
  final ValueNotifier<Contact?> _selectedContact = ValueNotifier<Contact?>(
    null,
  );

  Future<void> _pickContact() async {
    // `openExternalPick` délègue l'affichage au sélecteur système, mais
    // relit ensuite les numéros du contact choisi via le fournisseur de
    // contacts : sans cette permission, cette relecture lève une
    // `SecurityException` côté natif Android qui n'est pas récupérable
    // depuis Dart (crash de l'application, pas d'exception catchable ici).
    final status = await Permission.contacts.request();
    if (!status.isGranted) {
      Get.snackbar(
        'Permission requise',
        'L\'accès aux contacts est nécessaire pour choisir un filleul dans votre répertoire.',
      );
      return;
    }

    try {
      final contact = await FlutterContacts.openExternalPick();
      if (contact == null) return;
      if (contact.phones.isEmpty) {
        Get.snackbar(
          'Contact invalide',
          'Ce contact ne possède aucun numéro de téléphone.',
        );
        return;
      }
      _selectedContact.value = contact;
    } catch (_) {
      Get.snackbar('Erreur', 'Impossible d\'accéder au répertoire de contacts.');
    }
  }

  Future<void> _submit() async {
    final contact = _selectedContact.value;
    if (contact == null || contact.phones.isEmpty) return;
    final nom = contact.displayName.trim();
    final phone = _sanitizePhone(contact.phones.first.number);
    if (nom.isEmpty || phone.isEmpty) return;

    final success = await controller.addFilleul(phone, nom);
    if (success) {
      _selectedContact.value = null;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text('Parrainage & Filleuls'),
        backgroundColor: Colors.white,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
        centerTitle: true,
      ),
      body: Obx(() {
        if (controller.isLoading.value && controller.filleuls.isEmpty) {
          return const Center(child: CircularProgressIndicator());
        }

        return Padding(
          padding: const EdgeInsets.all(24.0),
          child: CustomScrollView(
            slivers: [
              SliverToBoxAdapter(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    // Info Card
                    Container(
                      padding: const EdgeInsets.all(16),
                      decoration: BoxDecoration(
                        color: AppColors.secondary,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(
                          color: AppColors.primary.withValues(alpha: 0.3),
                        ),
                      ),
                      child: Row(
                        children: [
                          Icon(
                            Icons.shield,
                            color: AppColors.primary,
                            size: 40,
                          ),
                          const SizedBox(width: 16),
                          Expanded(
                            child: Text(
                              'En tant que Maître Artisan, vous pouvez parrainer des apprentis. Attention : en cas de litige perdu par votre filleul, votre Score ProsArtisan subira une pénalité de caution.',
                              style: TextStyle(
                                fontSize: 13,
                                color: AppColors.primary.withValues(alpha: 0.9),
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 24),

                    // Formulaire d'ajout
                    Text(
                      'Nouveau Filleul',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 12),
                    ValueListenableBuilder<Contact?>(
                      valueListenable: _selectedContact,
                      builder: (context, contact, _) {
                        return Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            if (contact == null)
                              OutlinedButton.icon(
                                onPressed: _pickContact,
                                icon: const Icon(Icons.contacts),
                                label: const Text(
                                  'Choisir un contact à parrainer',
                                ),
                                style: OutlinedButton.styleFrom(
                                  minimumSize: const Size.fromHeight(56),
                                  foregroundColor: AppColors.primary,
                                  side: BorderSide(color: AppColors.primary),
                                  shape: RoundedRectangleBorder(
                                    borderRadius: BorderRadius.circular(12),
                                  ),
                                ),
                              )
                            else
                              Container(
                                padding: const EdgeInsets.all(12),
                                decoration: BoxDecoration(
                                  color: Colors.white,
                                  borderRadius: BorderRadius.circular(12),
                                  border: Border.all(color: AppColors.border),
                                ),
                                child: Row(
                                  children: [
                                    CircleAvatar(
                                      backgroundColor: AppColors.background,
                                      child: Icon(
                                        Icons.person,
                                        color: AppColors.primary,
                                      ),
                                    ),
                                    const SizedBox(width: 12),
                                    Expanded(
                                      child: Column(
                                        crossAxisAlignment:
                                            CrossAxisAlignment.start,
                                        children: [
                                          Text(
                                            contact.displayName,
                                            style: TextStyle(
                                              fontWeight: FontWeight.bold,
                                              color: AppColors.textPrimary,
                                            ),
                                          ),
                                          Text(
                                            contact.phones.first.number,
                                            style: TextStyle(
                                              color: AppColors.textSecondary,
                                              fontSize: 13,
                                            ),
                                          ),
                                        ],
                                      ),
                                    ),
                                    IconButton(
                                      icon: const Icon(Icons.close),
                                      tooltip: 'Changer de contact',
                                      onPressed: () =>
                                          _selectedContact.value = null,
                                    ),
                                  ],
                                ),
                              ),
                            const SizedBox(height: 12),
                            Obx(
                              () => SizedBox(
                                height: 48,
                                child: ElevatedButton.icon(
                                  onPressed:
                                      (contact == null ||
                                          controller.isSubmitting.value)
                                      ? null
                                      : _submit,
                                  icon: controller.isSubmitting.value
                                      ? const SizedBox(
                                          width: 18,
                                          height: 18,
                                          child: CircularProgressIndicator(
                                            color: Colors.white,
                                            strokeWidth: 2,
                                          ),
                                        )
                                      : const Icon(Icons.send),
                                  label: const Text('Inviter ce contact'),
                                  style: ElevatedButton.styleFrom(
                                    backgroundColor: AppColors.primary,
                                    foregroundColor: Colors.white,
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(12),
                                    ),
                                  ),
                                ),
                              ),
                            ),
                          ],
                        );
                      },
                    ),
                    const SizedBox(height: 32),
                    Text(
                      'Vos Filleuls Actifs',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 12),
                  ],
                ),
              ),

              // Liste
              controller.filleuls.isEmpty
                  ? const SliverFillRemaining(
                      hasScrollBody: false,
                      child: Center(
                        child: Text(
                          'Aucun filleul parrainé pour le moment.',
                          style: TextStyle(color: AppColors.textSecondary),
                        ),
                      ),
                    )
                  : SliverList(
                      delegate: SliverChildBuilderDelegate(
                        (context, index) {
                          final f = controller.filleuls[index];
                          final filleul = _readMap(f, 'filleul');
                          final isInscrit = filleul != null;
                          final statut = _readString(f, 'statut');
                          final isEnAttenteInscription =
                              statut == 'en_attente_inscription';

                          final name = isInscrit
                              ? _readString(filleul, 'name', 'Inconnu')
                              : _readString(f, 'filleul_nom', 'Filleul invité');
                          final phone = isInscrit
                              ? _readString(filleul, 'phone')
                              : _readString(f, 'filleul_phone');

                          final showCaution =
                              isInscrit || statut == 'actif';
                          final createdAt = _readString(f, 'created_at');

                          return Container(
                            margin: const EdgeInsets.only(bottom: 12),
                            padding: const EdgeInsets.all(16),
                            decoration: BoxDecoration(
                              color: Colors.white,
                              borderRadius: BorderRadius.circular(16),
                              border: Border.all(color: AppColors.border),
                            ),
                            child: Row(
                              children: [
                                CircleAvatar(
                                  backgroundColor: AppColors.background,
                                  child: Icon(
                                    Icons.person,
                                    color: AppColors.primary,
                                  ),
                                ),
                                const SizedBox(width: 16),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        name,
                                        style: TextStyle(
                                          fontWeight: FontWeight.bold,
                                          color: AppColors.textPrimary,
                                        ),
                                      ),
                                      Text(
                                        phone,
                                        style: TextStyle(
                                          color: AppColors.textSecondary,
                                          fontSize: 13,
                                        ),
                                      ),
                                      if (isEnAttenteInscription) ...[
                                        const SizedBox(height: 6),
                                        Container(
                                          padding: const EdgeInsets.symmetric(
                                            horizontal: 8,
                                            vertical: 4,
                                          ),
                                          decoration: BoxDecoration(
                                            color: AppColors.warning
                                                .withValues(alpha: 0.15),
                                            borderRadius:
                                                BorderRadius.circular(20),
                                          ),
                                          child: Text(
                                            'En attente d\'inscription',
                                            style: TextStyle(
                                              color: AppColors.warning,
                                              fontWeight: FontWeight.bold,
                                              fontSize: 11,
                                            ),
                                          ),
                                        ),
                                      ],
                                    ],
                                  ),
                                ),
                                Column(
                                  crossAxisAlignment: CrossAxisAlignment.end,
                                  children: [
                                    if (showCaution)
                                      Text(
                                        'Caution: ${f['score_caution']} pts',
                                        style: TextStyle(
                                          color: AppColors.warning,
                                          fontWeight: FontWeight.bold,
                                          fontSize: 12,
                                        ),
                                      ),
                                    const SizedBox(height: 4),
                                    Text(
                                      _formatDate(createdAt),
                                      style: TextStyle(
                                        color: AppColors.textSecondary,
                                        fontSize: 11,
                                      ),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          );
                        },
                        childCount: controller.filleuls.length,
                      ),
                    ),
            ],
          ),
        );
      }),
    );
  }
}
