import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/core/network/api_endpoints.dart';

import '../../helpers/test_helpers.dart';
import '../../test_config.dart';

void main() {
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
        contains('backend-proartisan.test'),
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

  group('TokenStorage Tests', () {
    const testToken = 'test_token_12345';

    test('should save and retrieve token', () async {
      await TokenStorage.save(testToken);
      final retrieved = await TokenStorage.get();
      expect(retrieved, testToken);
    });

    test('should clear token', () async {
      await TokenStorage.save(testToken);
      await TokenStorage.clear();
      final retrieved = await TokenStorage.get();
      expect(retrieved, isNull);
    });

    test('should return null when no token exists', () async {
      await TokenStorage.clear();
      final retrieved = await TokenStorage.get();
      expect(retrieved, isNull);
    });
  });
}
