import 'package:flutter/foundation.dart';
import 'package:get/get.dart';
import 'package:onesignal_flutter/onesignal_flutter.dart';

import '../../app/routes/app_routes.dart';
import '../../modules/chat/views/chat_screen.dart';
import '../../modules/main_tab/controllers/main_tab_controller.dart';
import '../storage/storage_service.dart';

class NotificationService extends GetxService {
  static NotificationService get to => Get.find();

  @override
  void onInit() {
    super.onInit();
    _initOneSignal();
  }

  void _initOneSignal() {
    try {
      // Activer les logs en mode debug
      if (kDebugMode) {
        OneSignal.Debug.setLogLevel(OSLogLevel.verbose);
      }

      // Initialiser OneSignal
      const appId = '00d061c8-977b-405a-a207-e2d87846670b';
      OneSignal.initialize(appId);

      // Demander la permission de recevoir des notifications
      OneSignal.Notifications.requestPermission(true);

      // Gestion des notifications reçues lorsque l'application est au premier plan
      OneSignal.Notifications.addForegroundWillDisplayListener((event) {
        if (StorageService.areNotificationsEnabled()) {
          event.notification.display();
        } else {
          event.preventDefault();
        }
      });

      // Écouter les clics sur les notifications push reçues
      OneSignal.Notifications.addClickListener((event) {
        final data = event.notification.additionalData;
        if (data != null) {
          routeToTarget(data);
        }
      });
    } catch (e) {
      debugPrint("Erreur lors de l'initialisation de OneSignal: $e");
    }
  }

  /// Redirige l'utilisateur vers la vue correspondante selon les données de la notification.
  void routeToTarget(Map<String, dynamic> data) {
    try {
      final type = (data['type'] as String?)?.toLowerCase() ?? '';

      final devisIdStr = data['devisId'] ?? data['devis_id'];
      final missionIdStr = data['missionId'] ?? data['mission_id'];
      final litigeIdStr = data['litigeId'] ?? data['litige_id'];

      final devisId =
          devisIdStr != null ? int.tryParse(devisIdStr.toString()) : null;
      final missionId =
          missionIdStr != null ? int.tryParse(missionIdStr.toString()) : null;
      final litigeId =
          litigeIdStr != null ? int.tryParse(litigeIdStr.toString()) : null;

      final role = StorageService.getRole() ?? 'client';

      // 0. Message de chantier : on ouvre directement la discussion concernée.
      // Sans ce cas, la notification n'ouvrait rien et l'utilisateur devait
      // parcourir toutes ses missions pour retrouver celle dont il venait de
      // recevoir un message.
      if (type.contains('chat') || type.contains('message')) {
        if (missionId != null) {
          Get.to(() => ChatScreen(missionId: missionId));
        }
      }
      // 1. Redirection pour les devis/propositions
      else if (type.contains('devis') || type.contains('quote')) {
        // Seul le client peut consulter/valider un devis sur DevisReviewScreen
        // (Règle d'or 37 : exclusivité client). Un artisan notifié (ex : son
        // devis a été refusé) est renvoyé vers le suivi de sa mission, où
        // DevisSection lui montre l'état du devis sans action qui échouerait.
        if (role == 'client' && devisId != null) {
          Get.toNamed(Routes.devisReview, arguments: devisId);
        } else if (missionId != null) {
          Get.toNamed(Routes.missionTracking, arguments: missionId);
        }
      }
      // 2. Redirection pour les missions ou jalons
      else if (type.contains('mission') || type.contains('jalon')) {
        if (missionId != null) {
          Get.toNamed(Routes.missionTracking, arguments: missionId);
        }
      }
      // 3. Redirection pour les paiements / finances / wallet
      else if (type.contains('payment') ||
          type.contains('wallet') ||
          type.contains('finance')) {
        _switchToMainTab(3); // Profil/Settings contient les infos financières
      }
      // 4. Redirection pour les J-Codes
      else if (type.contains('jcode')) {
        if (role == 'artisan') {
          _switchToMainTab(2); // Onglet J-Code pour artisan
        } else {
          Get.toNamed(Routes.jcode);
        }
      }
      // 5a. Assignation comme juré : dossier anonymisé de l'espace juré,
      // jamais la fiche litige des parties (noms et téléphones, Chantier 12).
      else if (type.contains('jury')) {
        if (litigeId != null) {
          Get.toNamed(
            Routes.juryDossierDetail,
            arguments: {'litigeId': litigeId},
          );
        } else {
          Get.toNamed(Routes.juryDossiers);
        }
      }
      // 5b. Redirection pour les litiges
      else if (type.contains('litige')) {
        if (litigeId != null) {
          Get.toNamed(
            Routes.litigeDetail,
            arguments: {'litigeId': litigeId},
          );
        } else if (missionId != null) {
          Get.toNamed(Routes.missionTracking, arguments: missionId);
        }
      }
      // 6. Redirection pour le recrutement (offres, candidatures, engagements)
      else if (type.contains('recruitment')) {
        final engagementIdStr = data['recruitmentEngagementId'] ??
            data['recruitment_engagement_id'];
        final engagementId = engagementIdStr != null
            ? int.tryParse(engagementIdStr.toString())
            : null;

        if (engagementId != null) {
          Get.toNamed(Routes.recruitmentEngagement, arguments: engagementId);
        } else if (role == 'artisan') {
          Get.toNamed(Routes.recruitmentOffers);
        } else {
          Get.toNamed(Routes.recruitmentPublish);
        }
      }
    } catch (e) {
      debugPrint('Erreur lors de la redirection de la notification: $e');
    }
  }

  void _switchToMainTab(int index) {
    Get.until(
      (route) => Get.currentRoute == Routes.mainTab || Get.currentRoute == '/',
    );
    try {
      final mainTabController = Get.find<MainTabController>();
      mainTabController.changeTab(index);
    } catch (_) {
      Get.offAllNamed(Routes.mainTab);
      Future.delayed(const Duration(milliseconds: 100), () {
        if (Get.isRegistered<MainTabController>()) {
          Get.find<MainTabController>().changeTab(index);
        }
      });
    }
  }
}
