import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/services/media_compressor_service.dart';
import 'package:frontend_flutter/core/storage/storage_service.dart';
import 'package:frontend_flutter/modules/settings/controllers/settings_controller.dart';
import '../../helpers/test_helpers.dart';

void main() {
  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
  });

  group('MediaCompressorService', () {
    test('formatBytes formats bytes correctly into Ko and Mo', () {
      expect(MediaCompressorService.formatBytes(500), '500 o');
      expect(MediaCompressorService.formatBytes(1024), '1.0 Ko');
      expect(MediaCompressorService.formatBytes(512 * 1024), '512.0 Ko');
      expect(MediaCompressorService.formatBytes(1024 * 1024), '1.0 Mo');
      expect(MediaCompressorService.formatBytes(25 * 1024 * 1024), '25.0 Mo');
    });

    test('isSizeAllowed checks 25 Mo ceiling correctly', () {
      expect(MediaCompressorService.isSizeAllowed(10 * 1024 * 1024), isTrue);
      expect(MediaCompressorService.isSizeAllowed(25 * 1024 * 1024), isTrue);
      expect(MediaCompressorService.isSizeAllowed(26 * 1024 * 1024), isFalse);
    });

    test('Data saver setting persists and defaults to true', () {
      expect(StorageService.isDataSaverEnabled(), isTrue);
      StorageService.setDataSaverEnabled(false);
      expect(StorageService.isDataSaverEnabled(), isFalse);
      StorageService.setDataSaverEnabled(true);
      expect(StorageService.isDataSaverEnabled(), isTrue);
    });

    test('SettingsController reflects and toggles data saver state', () {
      StorageService.setDataSaverEnabled(true);
      final controller = SettingsController();
      expect(controller.dataSaverEnabled.value, isTrue);

      controller.toggleDataSaver(false);
      expect(controller.dataSaverEnabled.value, isFalse);
      expect(StorageService.isDataSaverEnabled(), isFalse);

      controller.toggleDataSaver(true);
      expect(controller.dataSaverEnabled.value, isTrue);
      expect(StorageService.isDataSaverEnabled(), isTrue);
    });
  });
}
