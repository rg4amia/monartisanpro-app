import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/services/device_fingerprint_service.dart';
import '../../helpers/test_helpers.dart';

void main() {
  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
  });

  group('DeviceFingerprintService', () {
    test('getFingerprint returns a stable non-empty identifier', () {
      final service = DeviceFingerprintService();
      final fp1 = service.getFingerprint();
      final fp2 = service.getFingerprint();

      expect(fp1.isNotEmpty, isTrue);
      expect(fp1, equals(fp2));
    });

    test('getHeaders contains telemetry and anti-collusion headers', () {
      final service = DeviceFingerprintService();
      final headers = service.getHeaders();

      expect(headers.containsKey('X-Device-Fingerprint'), isTrue);
      expect(headers.containsKey('X-App-Installation-Id'), isTrue);
      expect(headers.containsKey('X-Device-Model'), isTrue);
      expect(headers['X-Device-Fingerprint'], equals(service.getFingerprint()));
    });
  });
}
