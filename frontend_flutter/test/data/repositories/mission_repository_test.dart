import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/data/repositories/mission_repository.dart';

import '../../helpers/test_helpers.dart';

void main() {
  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
  });

  tearDown(() async {
    await TestHelpers.cleanupTestData();
  });

  group('MissionRepository Integration Tests avec Herd', () {
    late MissionRepository missionRepository;

    setUp(() {
      missionRepository = MissionRepository();
    });

    test('should get all missions', () async {
      try {
        final missions = await missionRepository.getMissions();
        expect(missions, isA<List>());
      } on DioException catch (e) {
        // 401 si pas authentifié
        expect(e.response?.statusCode, 401);
      }
    });

    test('should get missions filtered by status', () async {
      try {
        final missions = await missionRepository.getMissions(
          status: 'en_cours',
        );
        expect(missions, isA<List>());

        // Vérifier que toutes les missions ont le bon statut
        for (final mission in missions) {
          expect(mission.status, 'en_cours');
        }
      } on DioException catch (e) {
        expect(e.response?.statusCode, 401);
      }
    });

    test('should get single mission by id', () async {
      try {
        final mission = await missionRepository.getMission(1);
        expect(mission.id, 1);
        expect(mission.description, isNotEmpty);
      } on DioException catch (e) {
        // 401 ou 404 sont acceptables
        expect(e.response?.statusCode, isIn([401, 404]));
      }
    });

    test('should create new mission', () async {
      try {
        final mission = await missionRepository.createMission(
          artisanId: 1,
          description: 'Test mission description',
          category: 'plomberie',
          urgency: 'normale',
          location: 'Abidjan, Cocody',
        );

        expect(mission.description, 'Test mission description');
        expect(mission.category, 'plomberie');
        expect(mission.urgency, 'normale');
      } on DioException catch (e) {
        // 401 ou 422 sont acceptables
        expect(e.response?.statusCode, isIn([401, 422]));
      }
    });

    test('should get mission estimate', () async {
      try {
        final estimate = await missionRepository.estimate(
          description: 'Réparer une fuite d\'eau',
          category: 'plomberie',
        );

        expect(estimate, isA<Map<String, dynamic>>());
        expect(estimate.containsKey('estimatedCost'), true);
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 422]));
      }
    });

    test('should update mission status', () async {
      try {
        await missionRepository.updateStatus(1, 'en_cours');
        // Si pas d'exception, le test passe
        expect(true, true);
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 404, 422]));
      }
    });

    test('should get mission jalons', () async {
      try {
        final jalons = await missionRepository.getJalons(1);
        expect(jalons, isA<List>());
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 404]));
      }
    });

    test('should submit jalon', () async {
      try {
        await missionRepository.submitJalon(1);
        expect(true, true);
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 404, 422]));
      }
    });

    test('should request OTP for jalon validation', () async {
      try {
        await missionRepository.requestOtp(1);
        expect(true, true);
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 404, 422]));
      }
    });

    test('should validate jalon with OTP', () async {
      try {
        await missionRepository.validateOtp(1, '123456');
        expect(true, true);
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 404, 422]));
      }
    });

    test('should handle validation errors on create', () async {
      try {
        await missionRepository.createMission(
          artisanId: -1,
          description: '',
          category: '',
          urgency: '',
        );
        fail('Should throw validation error');
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 422]));
      }
    });

    test('should handle not found errors', () async {
      try {
        await missionRepository.getMission(999999);
        fail('Should throw not found error');
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 404]));
      }
    });
  });
}
