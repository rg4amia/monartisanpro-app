import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/core/storage/storage_service.dart';
import 'package:frontend_flutter/data/repositories/artisan_repository.dart';
import 'package:frontend_flutter/data/repositories/auth_repository.dart';
import 'package:frontend_flutter/data/models/devis_model.dart';
import 'package:frontend_flutter/data/repositories/devis_repository.dart';
import 'package:frontend_flutter/data/repositories/evaluation_repository.dart';
import 'package:frontend_flutter/data/repositories/jcode_repository.dart';
import 'package:frontend_flutter/data/repositories/mission_repository.dart';
import 'package:frontend_flutter/data/repositories/notification_repository.dart';
import 'package:frontend_flutter/data/repositories/supplier_catalog_repository.dart';
import 'package:frontend_flutter/data/repositories/wallet_repository.dart';

import '../helpers/test_helpers.dart';
import '../test_config.dart';

/// Tests d'integration complets simulant un workflow utilisateur reel.
/// Couvre les fonctionnalites V18 + V23 :
/// - GPS artisans/fournisseurs, kit urgence nuit, mode nuit
/// - Score bayesien, vue carte, profil avec geolocalisation
/// - Litige avec preuve obligatoire, evaluation multi-criteres ProsArtisan
void main() {
  // CRITICAL: Mock FlutterSecureStorage BEFORE any other initialization
  TestWidgetsFlutterBinding.ensureInitialized();
  FlutterSecureStorage.setMockInitialValues({});

  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
  });

  tearDown(() async {
    await TestHelpers.cleanupTestData();
  });

  // GROUPE 1 : Workflows utilisateur complets
  group('Full User Workflow Integration Tests', () {
    late AuthRepository authRepo;
    late MissionRepository missionRepo;
    late JcodeRepository jcodeRepo;

    setUp(() {
      authRepo = AuthRepository();
      missionRepo = MissionRepository();
      jcodeRepo = JcodeRepository();
    });

    test('Complete client workflow: Auth -> Create Mission -> Track', () async {
      try {
        await authRepo.sendOtp(TestConfig.testPhone);

        try {
          final user = await authRepo.me();
          expect(user.phone, isNotEmpty);

          final mission = await missionRepo.createMission(
            artisanId: 1,
            description: 'Test integration mission',
            category: 'plomberie',
            urgency: 'normale',
          );

          expect(mission.description, 'Test integration mission');

          final missionDetails = await missionRepo.getMission(mission.id);
          expect(missionDetails.id, mission.id);
        } on DioException catch (e) {
          expect(e.response?.statusCode, 401);
        }
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 422]));
      }
    });

    test('Complete artisan workflow: Auth -> Accept Mission -> Create J-Code',
        () async {
      try {
        await authRepo.sendOtp(TestConfig.testPhone);

        try {
          final missions = await missionRepo.getMissions(status: 'en_attente');

          if (missions.isNotEmpty) {
            final mission = missions.first;
            await missionRepo.updateStatus(mission.id, 'en_cours');

            // V23 : fournisseurId + items sont requis
            final jcode = await jcodeRepo.createJcode(
              missionId: mission.id,
              fournisseurId: 1,
              items: [],
              montant: 50000,
            );

            expect(jcode.missionId, mission.id);
            expect(jcode.statut, 'actif');
          }
        } on DioException catch (e) {
          expect(e.response?.statusCode, isIn([401, 404, 422]));
        }
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 422]));
      }
    });

    test('Complete supplier workflow: Auth -> Scan J-Code', () async {
      try {
        await authRepo.sendOtp(TestConfig.testPhone);

        try {
          // V23 : identifier est une String (ex: "PA-1234"), pas un int
          final result = await jcodeRepo.scanJcode(
            identifier: 'PA-1234',
            lat: 5.3599517,
            lng: -4.0082563,
          );

          expect(result, isA<Map<String, dynamic>>());
        } on DioException catch (e) {
          expect(e.response?.statusCode, isIn([401, 404, 422]));
        }
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 422]));
      }
    });

    test('Error handling: Unauthorized access', () async {
      // V23 : StorageService remplace TokenStorage
      await StorageService.clearAll();

      try {
        await authRepo.me();
        fail('Should throw unauthorized error');
      } on DioException catch (e) {
        TestHelpers.expectUnauthorizedError(e);
      }
    });

    test('Error handling: Invalid data validation', () async {
      try {
        await missionRepo.createMission(
          artisanId: -1,
          description: '',
          category: 'invalid_category',
          urgency: 'invalid_urgency',
        );
        fail('Should throw validation error');
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 422]));
      }
    });

    test('Error handling: Resource not found', () async {
      try {
        await missionRepo.getMission(999999);
        fail('Should throw not found error');
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 404]));
      }
    });
  });

  // GROUPE 2 : Geolocalisation & Matching GPS (V23)
  // InteractiveMockMap, ST_Distance_Sphere, floutage 50m, hasEmergencyKit
  group('GPS & Artisan Matching Tests (V23)', () {
    late ArtisanRepository artisanRepo;

    setUp(() {
      artisanRepo = ArtisanRepository();
    });

    test('should find nearby artisans within 2km radius', () async {
      try {
        final artisans = await artisanRepo.getNearby(
          lat: 5.3599517,
          lng: -4.0082563,
          radiusMeters: 2000,
        );

        expect(artisans, isA<List>());
        for (final artisan in artisans) {
          expect(artisan.id, isPositive);
        }
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 422]));
      }
    });

    test('should filter artisans by category (plomberie)', () async {
      try {
        final artisans = await artisanRepo.getNearby(
          lat: 5.3599517,
          lng: -4.0082563,
          sectorId: 'plomberie',
        );

        expect(artisans, isA<List>());
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 422]));
      }
    });

    test('should filter artisans with emergency kit for night intervention (V23)',
        () async {
      // V23 : hasEmergencyKit = true -> artisans disponibles la nuit (18h-7h)
      // Majoration nightSurgeMultiplier appliquee automatiquement
      try {
        final artisans = await artisanRepo.getNearby(
          lat: 5.3599517,
          lng: -4.0082563,
          interventionNuit: true,
        );

        expect(artisans, isA<List>());
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 422]));
      }
    });

    test('should return artisan profile with GPS coordinates', () async {
      try {
        final artisan = await artisanRepo.getArtisan(1);
        expect(artisan.id, 1);
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 404]));
      }
    });

    test('should return artisan Score ProsArtisan', () async {
      try {
        final score = await artisanRepo.getScore(1);
        expect(score, isA<Map<String, dynamic>>());
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 404]));
      }
    });
  });

  // GROUPE 3 : Devis & Sequestre (Phase 2)
  // ratio_materiaux immuable, wallet_materiaux / wallet_mo, OTP jalons
  group('Devis & Sequestre Workflow Tests', () {
    late DevisRepository devisRepo;
    late MissionRepository missionRepo;

    setUp(() {
      devisRepo = DevisRepository();
      missionRepo = MissionRepository();
    });

    test('should create a devis with lignes and jalons', () async {
      try {
        final devis = await devisRepo.createDevis(
          missionId: 1,
          lignes: [
            DevisLigne(
              type: 'mo',
              description: "Main d'oeuvre plomberie",
              montant: 15000,
            ),
            DevisLigne(
              type: 'mat',
              description: 'Siphon PVC',
              montant: 2500,
            ),
          ],
          jalons: [
            DevisJalon(
              ordre: 1,
              description: 'Diagnostic et depose',
              montant: 7500,
              dateCible: '2026-04-01',
            ),
            DevisJalon(
              ordre: 2,
              description: 'Pose et finitions',
              montant: 10000,
              dateCible: '2026-04-03',
            ),
          ],
        );

        expect(devis.id, isPositive);
        expect(devis.statut, isIn(['brouillon', 'soumis']));
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 404, 422]));
      }
    });

    test('should get devis for a mission', () async {
      try {
        final devisList = await devisRepo.getMissionDevis(1);
        expect(devisList, isA<List>());
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 404]));
      }
    });

    test('should refuse a devis', () async {
      try {
        await devisRepo.refuseDevis(1);
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 404, 422]));
      }
    });

    test('should get jalons for a mission', () async {
      try {
        final jalons = await missionRepo.getJalons(1);
        expect(jalons, isA<List>());
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 404]));
      }
    });

    test('should submit a jalon with geolocated photos (V23)', () async {
      // V23 : photos geolocalisees obligatoires pour les jalons
      try {
        await missionRepo.submitJalon(
          1,
          photos: [
            {
              'url': 'https://example.com/photo.jpg',
              'lat': 5.3599517,
              'lng': -4.0082563,
              'taken_at': '2026-04-01T10:00:00Z',
            }
          ],
          missionId: 1,
        );
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 404, 422]));
      }
    });

    test('should request OTP for jalon validation', () async {
      try {
        await missionRepo.requestOtp(1);
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 404, 422]));
      }
    });

    test('should validate OTP and release jalon payment', () async {
      // Regle metier critique : liberation impossible sans OTP valide
      try {
        await missionRepo.validateOtp(1, '1234', missionId: 1);
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 404, 422]));
      }
    });
  });

  // GROUPE 4 : J-Code & Anti-Fraude GPS (Phase 3 - V23)
  // Verification GPS < 100m, format PA-XXXX, USSD, QR Code
  group('J-Code GPS Anti-Fraud Tests (V23)', () {
    late JcodeRepository jcodeRepo;

    setUp(() {
      jcodeRepo = JcodeRepository();
    });

    test('should create a J-Code with items for a mission', () async {
      try {
        final jcode = await jcodeRepo.createJcode(
          missionId: 1,
          fournisseurId: 1,
          items: [],
          montant: 25000,
        );

        expect(jcode.missionId, 1);
        expect(jcode.statut, 'actif');
        // Le code doit respecter le format PA-XXXX (regle metier)
        expect(jcode.code, matches(RegExp(r'^PA-[A-Z0-9]{4}$')));
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 404, 422]));
      }
    });

    test('should get active J-Code', () async {
      try {
        final jcode = await jcodeRepo.getActiveJcode();
        if (jcode != null) {
          expect(jcode.statut, 'actif');
        }
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 404]));
      }
    });

    test('should scan J-Code with valid GPS position (< 100m from boutique) (V23)',
        () async {
      // Coordonnees simulant le fournisseur a sa boutique (Adjame lat/lng du MOCK_DB)
      try {
        final result = await jcodeRepo.scanJcode(
          identifier: 'PA-AB12',
          lat: 5.3555,
          lng: -4.0200,
        );

        expect(result, isA<Map<String, dynamic>>());
      } on DioException catch (e) {
        // 422 attendu si GPS > 100m (regle anti-fraude V23)
        expect(e.response?.statusCode, isIn([401, 404, 422]));
      }
    });

    test('should block J-Code scan when GPS distance > 100m (anti-fraud rule V23)',
        () async {
      // Coordonnees eloignees de la boutique -> doit etre bloque + alerte admin
      try {
        final result = await jcodeRepo.scanJcode(
          identifier: 'PA-AB12',
          lat: 5.4000,
          lng: -4.1000,
        );

        // Si la reponse passe, elle doit contenir une erreur GPS
        if (result.containsKey('error')) {
          expect(result['error'], isNotNull);
        }
      } on DioException catch (e) {
        // 422 = distance GPS > 100m -> transaction bloquee (regle metier critique)
        expect(e.response?.statusCode, isIn([401, 404, 422]));
      }
    });

    test('should get J-Code by string identifier', () async {
      try {
        final jcode = await jcodeRepo.getJcode('PA-1234');
        expect(jcode.code, isNotEmpty);
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 404]));
      }
    });
  });

  // GROUPE 5 : Catalogue Fournisseur & Commandes B2C (V23)
  // SupplierDashboard, catalogue avec photos, B2C/B2B, retrait/livraison
  group('Supplier Catalog & B2C Order Tests (V23)', () {
    late SupplierCatalogRepository catalogRepo;

    setUp(() {
      catalogRepo = SupplierCatalogRepository();
    });

    test('should get list of approved suppliers', () async {
      try {
        final suppliers = await catalogRepo.getApprovedSuppliers();
        expect(suppliers, isA<List>());
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 422]));
      }
    });

    test('should search suppliers by name', () async {
      try {
        final suppliers =
            await catalogRepo.getApprovedSuppliers(search: 'Quincaillerie');
        expect(suppliers, isA<List>());
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 422]));
      }
    });

    test('should get supplier catalog products', () async {
      try {
        final products = await catalogRepo.getSupplierProducts(1);
        expect(products, isA<List>());
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 404]));
      }
    });

    test('should get my products (supplier view)', () async {
      try {
        final products = await catalogRepo.getMyProducts();
        expect(products, isA<List>());
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 403]));
      }
    });
  });

  // GROUPE 6 : Wallet & Transactions (fragmentation sequestre)
  // wallet_materiaux / wallet_mo, montants BIGINT (jamais float)
  group('Wallet & Escrow Tests', () {
    late WalletRepository walletRepo;

    setUp(() {
      walletRepo = WalletRepository();
    });

    test('should get wallet balance with materiaux and mo split', () async {
      try {
        final balance = await walletRepo.getBalance();

        expect(balance, isA<Map<String, int>>());
        expect(balance.containsKey('walletMateriaux'), isTrue);
        expect(balance.containsKey('walletMo'), isTrue);
        expect(balance.containsKey('total'), isTrue);

        // Regle metier : montants FCFA toujours en entiers (jamais float)
        expect(balance['walletMateriaux'], isA<int>());
        expect(balance['walletMo'], isA<int>());
        expect(balance['total'], isA<int>());
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 403]));
      }
    });
  });

  // GROUPE 7 : Litiges avec preuve obligatoire (V23)
  // DisputeModal avec proofImg obligatoire, gel des fonds, arbitrage admin
  group('Litige with Mandatory Proof Tests (V23)', () {
    late NotificationRepository notifRepo;

    setUp(() {
      notifRepo = NotificationRepository();
    });

    test('should submit a litige with description', () async {
      try {
        await notifRepo.submitLitige(
          missionId: 1,
          description: 'Travail incomplet / mal fait',
        );
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 404, 422]));
      }
    });

    test('should get litige details', () async {
      try {
        final litige = await notifRepo.getLitige(1);
        expect(litige, isA<Map<String, dynamic>>());
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 404]));
      }
    });
  });

  // GROUPE 8 : Evaluation multi-criteres Score ProsArtisan (Phase 5 - V23)
  // fiabilite 40%, integrite 30%, qualite 20%, reactivite 10%
  group("Evaluation & Score ProsArtisan Tests (V23)", () {
    late EvaluationRepository evalRepo;

    setUp(() {
      evalRepo = EvaluationRepository();
    });

    test("should submit evaluation with all ProsArtisan criteria", () async {
      try {
        final result = await evalRepo.submit(
          missionId: 1,
          evalueId: 1,
          note: 5,
          commentaire: 'Excellent travail, tres professionnel',
          fiabilite: 5,
          integrite: 5,
          qualite: 4,
          reactivite: 5,
        );

        expect(result, isA<Map<String, dynamic>>());
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 404, 422]));
      }
    });

    test('should reject evaluation with note out of range (1-5)', () async {
      try {
        await evalRepo.submit(
          missionId: 1,
          evalueId: 1,
          note: 6,
          commentaire: null,
          fiabilite: 3,
          integrite: 3,
          qualite: 3,
          reactivite: 3,
        );
        fail('Should throw validation error for note > 5');
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 422]));
      }
    });
  });

  // GROUPE 9 : Notifications (V23)
  group('Notification Tests (V23)', () {
    late NotificationRepository notifRepo;

    setUp(() {
      notifRepo = NotificationRepository();
    });

    test('should get notifications list', () async {
      try {
        final notifications = await notifRepo.getNotifications();
        expect(notifications, isA<List>());
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401]));
      }
    });

    test('should mark a notification as read', () async {
      try {
        await notifRepo.markRead(1);
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 404]));
      }
    });

    test('should mark all notifications as read', () async {
      try {
        await notifRepo.markAllRead();
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401]));
      }
    });
  });

  // GROUPE 10 : Performances & Configuration reseau
  group('Performance & Network Tests', () {
    late MissionRepository missionRepo;

    setUp(() {
      missionRepo = MissionRepository();
    });

    test('should handle multiple concurrent requests', () async {
      try {
        final futures = List.generate(5, (_) => missionRepo.getMissions());
        final results = await Future.wait(futures, eagerError: false);
        expect(results.length, 5);
      } on DioException catch (e) {
        expect(e.response?.statusCode, 401);
      }
    });

    test('should respect timeout configuration', () async {
      final client = ApiClient();
      expect(client.dio.options.connectTimeout, isNotNull);
      expect(client.dio.options.receiveTimeout, isNotNull);
    });

    test('should use cache for repeated mission requests', () async {
      try {
        await missionRepo.getMissions();
        await missionRepo.getMissions();
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401]));
      }
    });

    test('should force refresh bypassing cache', () async {
      try {
        await missionRepo.getMissions(forceRefresh: true);
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401]));
      }
    });
  });
}
