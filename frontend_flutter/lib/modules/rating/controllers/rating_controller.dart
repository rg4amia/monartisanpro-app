import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:get/get.dart';

import '../../../data/repositories/evaluation_repository.dart';

class RatingController extends GetxController {
  final EvaluationRepository _repo = EvaluationRepository();

  final selectedNote = 0.obs;
  final commentaire = ''.obs;
  final fiabilite = 0.obs;
  final integrite = 0.obs;
  final qualite = 0.obs;
  final reactivite = 0.obs;
  final isLoading = false.obs;

  final targetName = ''.obs;
  final targetRole = 'artisan'.obs; // 'artisan' | 'livreur' | 'fournisseur'
  final targetSubtitle = ''.obs;

  int? missionId;
  int? orderId;
  late int evalueId;

  @override
  void onInit() {
    super.onInit();
    final args = Get.arguments as Map?;
    missionId = args?['missionId'] as int?;
    orderId = args?['orderId'] as int?;
    evalueId = (args?['evalueId'] as int?) ?? 0;
    targetName.value = (args?['targetName'] as String?) ?? 'Intervenant';
    targetRole.value = (args?['targetRole'] as String?) ?? 'artisan';
    targetSubtitle.value = (args?['targetSubtitle'] as String?) ?? '';
  }

  void setNote(int note) {
    selectedNote.value = note;

    if (fiabilite.value == 0) fiabilite.value = note;
    if (integrite.value == 0) integrite.value = note;
    if (qualite.value == 0) qualite.value = note;
    if (reactivite.value == 0) reactivite.value = note;
  }

  void setCriterion(String criterion, int note) {
    switch (criterion) {
      case 'fiabilite':
        fiabilite.value = note;
        break;
      case 'integrite':
        integrite.value = note;
        break;
      case 'qualite':
        qualite.value = note;
        break;
      case 'reactivite':
        reactivite.value = note;
        break;
    }
  }

  String get screenTitle {
    switch (targetRole.value) {
      case 'livreur':
        return 'Évaluer le livreur';
      case 'fournisseur':
        return 'Évaluer la quincaillerie';
      case 'artisan':
      default:
        return 'Évaluer l\'artisan';
    }
  }

  IconData get roleIcon {
    switch (targetRole.value) {
      case 'livreur':
        return Icons.local_shipping_rounded;
      case 'fournisseur':
        return Icons.storefront_rounded;
      case 'artisan':
      default:
        return Icons.handyman_rounded;
    }
  }

  Color get roleColor {
    switch (targetRole.value) {
      case 'livreur':
        return const Color(0xFFF1C40F);
      case 'fournisseur':
        return const Color(0xFF10B981);
      case 'artisan':
      default:
        return const Color(0xFFE67E22);
    }
  }

  String get criterionFiabiliteLabel {
    switch (targetRole.value) {
      case 'livreur':
        return 'Ponctualité & Délais';
      case 'fournisseur':
        return 'Disponibilité & Stocks';
      case 'artisan':
      default:
        return 'Fiabilité & Délais';
    }
  }

  String get criterionIntegriteLabel {
    switch (targetRole.value) {
      case 'livreur':
        return 'Intégrité & Professionnalisme';
      case 'fournisseur':
        return 'Transparence & Prix';
      case 'artisan':
      default:
        return 'Intégrité & Respect du devis';
    }
  }

  String get criterionQualiteLabel {
    switch (targetRole.value) {
      case 'livreur':
        return 'Soin du colis & État de livraison';
      case 'fournisseur':
        return 'Qualité des matériaux fournis';
      case 'artisan':
      default:
        return 'Qualité du travail & Finitions';
    }
  }

  String get criterionReactiviteLabel {
    switch (targetRole.value) {
      case 'livreur':
        return 'Courtoisie & Communication';
      case 'fournisseur':
        return 'Rapidité & Accueil';
      case 'artisan':
      default:
        return 'Réactivité & Disponibilité';
    }
  }

  Future<void> submit() async {
    if (selectedNote.value == 0) {
      Get.snackbar(
        'Note requise',
        'Veuillez sélectionner une note globale en étoiles.',
        snackPosition: SnackPosition.TOP,
        backgroundColor: const Color(0xFFEF4444),
        colorText: Colors.white,
      );
      return;
    }

    if (evalueId <= 0) {
      Get.snackbar(
        'Intervenant non identifié',
        'Impossible de déterminer l\'identifiant de l\'intervenant à évaluer.',
        snackPosition: SnackPosition.TOP,
        backgroundColor: const Color(0xFFEF4444),
        colorText: Colors.white,
      );
      return;
    }

    final resolvedFiabilite =
        fiabilite.value == 0 ? (selectedNote.value > 0 ? selectedNote.value : 5) : fiabilite.value;
    final resolvedIntegrite =
        integrite.value == 0 ? (selectedNote.value > 0 ? selectedNote.value : 5) : integrite.value;
    final resolvedQualite =
        qualite.value == 0 ? (selectedNote.value > 0 ? selectedNote.value : 5) : qualite.value;
    final resolvedReactivite =
        reactivite.value == 0 ? (selectedNote.value > 0 ? selectedNote.value : 5) : reactivite.value;

    final resolvedNote = selectedNote.value > 0
        ? selectedNote.value
        : ((resolvedFiabilite + resolvedIntegrite + resolvedQualite + resolvedReactivite) / 4).round().clamp(1, 5);

    isLoading.value = true;
    try {
      await _repo.submit(
        missionId: missionId,
        orderId: orderId,
        evalueId: evalueId,
        note: resolvedNote,
        commentaire: commentaire.value,
        fiabilite: resolvedFiabilite,
        integrite: resolvedIntegrite,
        qualite: resolvedQualite,
        reactivite: resolvedReactivite,
      );

      Get.back(result: true);
      Get.snackbar(
        'Avis enregistré !',
        'Merci pour votre retour. Le Score ProsArtisan a été mis à jour.',
        snackPosition: SnackPosition.TOP,
        backgroundColor: const Color(0xFF10B981),
        colorText: Colors.white,
        duration: const Duration(seconds: 4),
      );
    } on DioException catch (e) {
      Get.snackbar(
        'Erreur',
        _extractMessage(e),
        snackPosition: SnackPosition.TOP,
        backgroundColor: const Color(0xFFEF4444),
        colorText: Colors.white,
        duration: const Duration(seconds: 5),
      );
    } catch (_) {
      Get.snackbar(
        'Erreur',
        'Une erreur est survenue lors de l\'envoi de votre évaluation.',
        snackPosition: SnackPosition.TOP,
        backgroundColor: const Color(0xFFEF4444),
        colorText: Colors.white,
      );
    } finally {
      isLoading.value = false;
    }
  }

  String _extractMessage(DioException e) {
    final data = e.response?.data;
    if (data is Map) {
      if (data['message'] is String && (data['message'] as String).isNotEmpty) {
        return data['message'] as String;
      }
      if (data['errors'] is Map) {
        final errors = data['errors'] as Map;
        if (errors.isNotEmpty) {
          final firstError = errors.values.first;
          if (firstError is List && firstError.isNotEmpty) {
            return firstError.first.toString();
          }
          return firstError.toString();
        }
      }
    }
    return e.message ?? 'Impossible d\'envoyer l\'évaluation.';
  }
}

