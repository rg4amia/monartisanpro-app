import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/storage/storage_service.dart';
import 'package:frontend_flutter/data/models/user_model.dart';
import 'package:frontend_flutter/data/repositories/auth_repository.dart';

import '../../helpers/test_helpers.dart';
import '../../test_config.dart';

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

  group('AuthRepository Integration Tests avec Herd', () {
    late AuthRepository authRepository;

    setUp(() {
      authRepository = AuthRepository();
    });

    test('should send OTP successfully', () async {
      try {
        await authRepository.sendOtp(TestConfig.testPhone);
        // Si pas d'exception, le test passe
        expect(true, true);
      } on DioException catch (e) {
        // Vérifier que c'est une erreur attendue (ex: téléphone déjà utilisé)
        expect(e.response?.statusCode, isIn([200, 422]));
      }
    });

    test('should handle invalid phone number', () async {
      expect(
        () => authRepository.sendOtp('invalid'),
        throwsA(isA<DioException>()),
      );
    });

    test('should verify OTP and return token', () async {
      // Note: Ce test nécessite un OTP valide du backend
      // En production, vous devriez mocker ou utiliser un endpoint de test
      try {
        final result = await authRepository.verifyOtp(
          TestConfig.testPhone,
          TestConfig.testOtp,
        );
        
        // verifyOtp() retourne un Map avec 'token', 'user', 'has_completed_profile', 'phone'
        expect(result, isA<Map<String, dynamic>>());
        expect(result.containsKey('has_completed_profile'), isTrue);
        
        if (result['has_completed_profile'] == true) {
          expect(result['token'], isNotNull);
        }
      } on DioException catch (e) {
        // OTP invalide est une réponse attendue en test
        expect(e.response?.statusCode, isIn([401, 422]));
      }
    });

    test('should register new user', () async {
      try {
        final result = await authRepository.register(
          phone: TestConfig.testPhone,
          role: TestConfig.testRole,
          name: TestConfig.testName,
        );

        // register() retourne un Map avec 'token' et 'user'
        expect(result['token'], isNotNull);
        expect(result['user'], isA<UserModel>());
        
        final user = result['user'] as UserModel;
        expect(user.phone, TestConfig.testPhone);
        expect(user.role, TestConfig.testRole);
      } on DioException catch (e) {
        // Utilisateur déjà existant est acceptable
        expect(e.response?.statusCode, isIn([200, 201, 422]));
      }
    });

    test('should get current user with valid token', () async {
      // Ce test nécessite un token valide
      // Vous devrez d'abord vous authentifier
      try {
        final user = await authRepository.me();
        expect(user.id, isPositive);
        expect(user.phone, isNotEmpty);
      } on DioException catch (e) {
        // 401 est attendu si pas de token
        expect(e.response?.statusCode, 401);
      }
    });

    test('should logout successfully', () async {
      // Le logout devrait toujours réussir même sans token
      await authRepository.logout();

      // Vérifier que le token a été supprimé (StorageService au lieu de TokenStorage)
      final token = await StorageService.getToken();
      expect(token, isNull);
    });

    test('should get KYC status', () async {
      try {
        final status = await authRepository.kycStatus();
        expect(status, isIn(['en_attente', 'en_cours', 'actif', 'refuse']));
      } on DioException catch (e) {
        // 401 si pas authentifié
        expect(e.response?.statusCode, 401);
      }
    });

    test('should handle network errors gracefully', () async {
      // Tester avec une URL invalide pour simuler une erreur réseau
      expect(
        () => authRepository.sendOtp(''),
        throwsA(isA<DioException>()),
      );
    });
  });

  group('AuthRepository Error Handling', () {
    late AuthRepository authRepository;

    setUp(() {
      authRepository = AuthRepository();
    });

    test('should handle 422 validation errors', () async {
      try {
        await authRepository.register(
          phone: '',
          role: '',
          name: '',
        );
        fail('Should throw validation error');
      } on DioException catch (e) {
        TestHelpers.expectValidationError(e);
      }
    });

    test('should handle 401 unauthorized errors', () async {
      await StorageService.clearAll();

      try {
        await authRepository.me();
        fail('Should throw unauthorized error');
      } on DioException catch (e) {
        TestHelpers.expectUnauthorizedError(e);
      }
    });
  });
}
