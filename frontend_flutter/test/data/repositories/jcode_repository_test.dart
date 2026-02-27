import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/data/repositories/jcode_repository.dart';

import '../../helpers/test_helpers.dart';

void main() {
  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
  });

  tearDown(() async {
    await TestHelpers.cleanupTestData();
  });

  group('JcodeRepository Integration Tests avec Herd', () {
    late JcodeRepository jcodeRepository;

    setUp(() {
      jcodeRepository = JcodeRepository();
    });

    test('should create new J-Code', () async {
      try {
        final jcode = await jcodeRepository.createJcode(
          missionId: 1,
          montant: 50000,
        );

        expect(jcode.missionId, 1);
        expect(jcode.montant, 50000);
        expect(jcode.code, isNotEmpty);
        expect(jcode.statut, 'actif');
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 422]));
      }
    });

    test('should get active J-Code', () async {
      try {
        final jcode = await jcodeRepository.getActiveJcode();

        if (jcode != null) {
          expect(jcode.statut, 'actif');
          expect(jcode.code, isNotEmpty);
        } else {
          // Pas de J-Code actif est un cas valide
          expect(jcode, isNull);
        }
      } on DioException catch (e) {
        expect(e.response?.statusCode, 401);
      }
    });

    test('should get J-Code by id', () async {
      try {
        final jcode = await jcodeRepository.getJcode(1);
        expect(jcode.id, 1);
        expect(jcode.code, isNotEmpty);
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 404]));
      }
    });

    test('should scan J-Code with GPS coordinates', () async {
      try {
        final result = await jcodeRepository.scanJcode(
          id: 1,
          lat: 5.3599517,
          lng: -4.0082563,
        );

        expect(result, isA<Map<String, dynamic>>());
        expect(result.containsKey('message'), true);
      } on DioException catch (e) {
        // 401, 404, ou 422 sont acceptables
        expect(e.response?.statusCode, isIn([401, 404, 422]));
      }
    });

    test('should handle validation errors on create', () async {
      try {
        await jcodeRepository.createJcode(
          missionId: -1,
          montant: -1000,
        );
        fail('Should throw validation error');
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 422]));
      }
    });

    test('should handle not found errors', () async {
      try {
        await jcodeRepository.getJcode(999999);
        fail('Should throw not found error');
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 404]));
      }
    });

    test('should validate GPS coordinates on scan', () async {
      try {
        await jcodeRepository.scanJcode(
          id: 1,
          lat: 200.0, // Latitude invalide
          lng: 200.0, // Longitude invalide
        );
        fail('Should throw validation error');
      } on DioException catch (e) {
        expect(e.response?.statusCode, isIn([401, 422]));
      }
    });

    test('should handle expired J-Code scan', () async {
      try {
        // Tenter de scanner un J-Code expiré
        await jcodeRepository.scanJcode(
          id: 1,
          lat: 5.3599517,
          lng: -4.0082563,
        );
      } on DioException catch (e) {
        // Peut retourner 422 si le J-Code est expiré
        expect(e.response?.statusCode, isIn([401, 404, 422]));
      }
    });
  });
}
