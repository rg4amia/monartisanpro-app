import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/core/storage/storage_service.dart';

import '../../helpers/test_helpers.dart';

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

  group('ApiClient Tests', () {
    late ApiClient apiClient;

    setUp(() {
      apiClient = ApiClient();
    });

    test('should have correct base URL from Herd', () {
      expect(
        apiClient.dio.options.baseUrl,
        anyOf([
          contains('backend-proartisan.test'),
          contains('127.0.0.1:8000'),
          contains('10.0.2.2:8000'),
        ]),
      );
    });

    test('should have correct default headers', () {
      final headers = apiClient.dio.options.headers;
      expect(headers['Accept'], 'application/json');
      expect(headers['Content-Type'], 'application/json');
    });

    test('should have correct timeout configuration', () {
      expect(apiClient.dio.options.connectTimeout, isNotNull);
      expect(apiClient.dio.options.receiveTimeout, isNotNull);
    });

    test('should be singleton instance', () {
      final instance1 = ApiClient();
      final instance2 = ApiClient();
      expect(identical(instance1, instance2), true);
    });
  });

  group('StorageService Tests', () {
    const testToken = 'test_token_12345';

    test('should save and retrieve token', () async {
      await StorageService.saveToken(testToken);
      final retrieved = await StorageService.getToken();
      expect(retrieved, testToken);
    });

    test('should clear token', () async {
      await StorageService.saveToken(testToken);
      await StorageService.clearToken();
      final retrieved = await StorageService.getToken();
      expect(retrieved, isNull);
    });

    test('should return null when no token exists', () async {
      await StorageService.clearToken();
      final retrieved = await StorageService.getToken();
      expect(retrieved, isNull);
    });
  });
}
