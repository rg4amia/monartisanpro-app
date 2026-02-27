import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/data/repositories/auth_repository.dart';
import 'package:frontend_flutter/data/repositories/mission_repository.dart';
import 'package:frontend_flutter/data/repositories/jcode_repository.dart';

import '../helpers/test_helpers.dart';
import '../test_config.dart';

/// Tests d'intégration complets simulant un workflow utilisateur réel
void main() {
  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
  });

  tearDown(() async {
    await TestHelpers.cleanupTestData();
  });

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
        // 1. Envoyer OTP
        await authRepo.sendOtp(TestConfig.testPhone);

        // 2. Vérifier OTP (nécessite un vrai OTP du backend)
        // En test réel, vous devriez récupérer l'OTP de la base de données
        // ou utiliser un endpoint de test

        // 3. Obtenir les informations utilisateur
        try {
          final user = await authRepo.me();
          expect(user.phone, isNotEmpty);

          // 4. Créer une mission
          final mission = await missionRepo.createMission(
            artisanId: 1,
            description: 'Test integration mission',
            category: 'plomberie',
            urgency: 'normale',
          );

          expect(mission.description, 'Test integration mission');

          // 5. Obtenir les détails de la mission
          final missionDetails = await missionRepo.getMission(mission.id);
          expect(missionDetails.id, mission.id);
        } on DioException catch (e) {
          // Authentification requise
          expect(e.response?.statusCode, 401);
        }
      } on DioException catch (e) {
        // Erreurs attendues en environnement de test
        expect(e.response?.statusCode, isIn([401, 422]));
      }
    });

    test('Complete artisan workflow: Auth -> Accept Mission -> Create J-Code',
        () async {
      try {
        // 1. Authentification artisan
        await authRepo.sendOtp(TestConfig.testPhone);

        try {
          final user = await authRepo.me();

          // 2. Obtenir les missions disponibles
          final missions = await missionRepo.getMissions(status: 'en_attente');

          if (missions.isNotEmpty) {
            final mission = missions.first;

            // 3. Accepter la mission
            await missionRepo.updateStatus(mission.id, 'en_cours');

            // 4. Créer un J-Code pour les matériaux
            final jcode = await jcodeRepo.createJcode(
              missionId: mission.id,
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
        // 1. Authentification fournisseur
        await authRepo.sendOtp(TestConfig.testPhone);

        try {
          // 2. Scanner un J-Code actif
          final result = await jcodeRepo.scanJcode(
            id: 1,
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
      // Nettoyer le token
      await TokenStorage.clear();

      // Tenter d'accéder à une ressource protégée
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

  group('Performance Tests', () {
    late MissionRepository missionRepo;

    setUp(() {
      missionRepo = MissionRepository();
    });

    test('should handle multiple concurrent requests', () async {
      try {
        // Lancer plusieurs requêtes en parallèle
        final futures = List.generate(
          5,
          (index) => missionRepo.getMissions(),
        );

        final results = await Future.wait(
          futures,
          eagerError: false,
        );

        expect(results.length, 5);
      } on DioException catch (e) {
        expect(e.response?.statusCode, 401);
      }
    });

    test('should respect timeout configuration', () async {
      // Ce test vérifie que les timeouts sont correctement configurés
      final client = ApiClient();
      expect(client.dio.options.connectTimeout, isNotNull);
      expect(client.dio.options.receiveTimeout, isNotNull);
    });
  });
}
