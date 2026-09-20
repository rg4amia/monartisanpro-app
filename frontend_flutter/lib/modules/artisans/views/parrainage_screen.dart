import 'package:flutter/material.dart';
import 'package:get/get.dart';
import 'package:intl/intl.dart';

import '../../../core/theme/app_colors.dart';
import '../controllers/parrainage_controller.dart';

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
  final TextEditingController _nomController = TextEditingController();
  final TextEditingController _phoneController = TextEditingController();

  void _submit() async {
    final nom = _nomController.text.trim();
    final phone = _phoneController.text.trim();
    if (nom.isEmpty || phone.isEmpty) return;

    final success = await controller.addFilleul(phone, nom);
    if (success) {
      _nomController.clear();
      _phoneController.clear();
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
                    TextField(
                      controller: _nomController,
                      keyboardType: TextInputType.name,
                      textCapitalization: TextCapitalization.words,
                      decoration: InputDecoration(
                        hintText: 'Nom du filleul',
                        filled: true,
                        fillColor: Colors.white,
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: BorderSide(color: AppColors.border),
                        ),
                        enabledBorder: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: BorderSide(color: AppColors.border),
                        ),
                      ),
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Expanded(
                          child: TextField(
                            controller: _phoneController,
                            keyboardType: TextInputType.phone,
                            decoration: InputDecoration(
                              hintText: 'Numéro de téléphone (ex: 0700000000)',
                              filled: true,
                              fillColor: Colors.white,
                              border: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(12),
                                borderSide: BorderSide(color: AppColors.border),
                              ),
                              enabledBorder: OutlineInputBorder(
                                borderRadius: BorderRadius.circular(12),
                                borderSide: BorderSide(color: AppColors.border),
                              ),
                            ),
                          ),
                        ),
                        const SizedBox(width: 12),
                        SizedBox(
                          height: 56,
                          child: ElevatedButton(
                            onPressed:
                                controller.isSubmitting.value ? null : _submit,
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.primary,
                              foregroundColor: Colors.white,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                              ),
                            ),
                            child: controller.isSubmitting.value
                                ? const SizedBox(
                                    width: 24,
                                    height: 24,
                                    child: CircularProgressIndicator(
                                      color: Colors.white,
                                      strokeWidth: 2,
                                    ),
                                  )
                                : const Icon(Icons.add),
                          ),
                        ),
                      ],
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
