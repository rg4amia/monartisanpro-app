import 'package:dio/dio.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get_storage/get_storage.dart';

import '../test_config.dart';

/// Helper pour initialiser les dépendances de test
class TestHelpers {
  static Future<void> initializeTestEnvironment() async {
    TestWidgetsFlutterBinding.ensureInitialized();
    
    // Mock FlutterSecureStorage for tests
    FlutterSecureStorage.setMockInitialValues({});
    
    // Initialize GetStorage for tests
    await GetStorage.init();
  }

  static Dio createTestDio() {
    return Dio(
      BaseOptions(
        baseUrl: TestConfig.baseUrl,
        connectTimeout: TestConfig.timeout,
        receiveTimeout: TestConfig.timeout,
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
        },
      ),
    );
  }

  static Future<void> cleanupTestData() async {
    const storage = FlutterSecureStorage();
    await storage.deleteAll();
    
    // Clear GetStorage data
    final box = GetStorage();
    await box.erase();
  }

  static void expectSuccessResponse(Response response) {
    expect(response.statusCode, inInclusiveRange(200, 299));
  }

  static void expectValidationError(DioException error) {
    expect(error.response?.statusCode, 422);
  }

  static void expectUnauthorizedError(DioException error) {
    expect(error.response?.statusCode, 401);
  }

  static void expectNotFoundError(DioException error) {
    expect(error.response?.statusCode, 404);
  }
}
