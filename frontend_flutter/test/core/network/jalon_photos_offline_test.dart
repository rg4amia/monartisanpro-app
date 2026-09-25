import 'dart:io';

import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/core/network/sync_service.dart';
import 'package:frontend_flutter/data/repositories/mission_repository.dart';
import 'package:get/get.dart' hide Response, FormData, MultipartFile;

class MockApiClient extends ApiClient {
  DioException? exceptionToThrow;

  MockApiClient() : super.withDio(Dio());

  @override
  Future<Response> postMultipart(String path, FormData formData) async {
    if (exceptionToThrow != null) {
      throw exceptionToThrow!;
    }
    return Response(
      requestOptions: RequestOptions(path: path),
      statusCode: 200,
    );
  }
}

class FakeSyncService extends SyncService {
  final List<QueuedRequest> enqueued = [];

  @override
  Future<void> enqueueMultipartRequest(
    String url, {
    Map<String, dynamic>? data,
    Map<String, String>? filePaths,
  }) async {
    enqueued.add(
      QueuedRequest(
        id: 'mock-${enqueued.length + 1}',
        method: 'POST',
        url: url,
        data: data,
        timestamp: DateTime.now(),
        isMultipart: true,
        filePaths: filePaths,
      ),
    );
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('QueuedRequest Multipart Serialization', () {
    test('sérialise et désérialise fidèlement une requête multipart avec fichiers', () {
      final original = QueuedRequest(
        id: 'req-multi-1',
        method: 'POST',
        url: '/jalons/12/photos',
        data: const {
          'photos[0][latitude]': 5.3484,
          'photos[0][longitude]': -4.0192,
          'photos[0][description]': 'Preuve pose de câbles',
        },
        timestamp: DateTime.parse('2026-09-25T10:00:00Z'),
        isMultipart: true,
        filePaths: const {
          'photos[0][photo]': '/data/user/0/cache/cable_proof.jpg',
        },
      );

      final json = original.toJson();
      final restored = QueuedRequest.fromJson(json);

      expect(restored.id, original.id);
      expect(restored.method, 'POST');
      expect(restored.url, '/jalons/12/photos');
      expect(restored.isMultipart, isTrue);
      expect(restored.data?['photos[0][latitude]'], 5.3484);
      expect(restored.data?['photos[0][description]'], 'Preuve pose de câbles');
      expect(restored.filePaths?['photos[0][photo]'], '/data/user/0/cache/cable_proof.jpg');
    });
  });

  group('MissionRepository Offline Photo Queueing', () {
    late FakeSyncService fakeSyncService;
    late MockApiClient mockApiClient;
    late File tempFile;

    setUp(() async {
      Get.reset();
      fakeSyncService = FakeSyncService();
      Get.put<SyncService>(fakeSyncService);

      mockApiClient = MockApiClient();

      // Créer un vrai fichier temporaire pour que MultipartFile.fromFile réussisse
      tempFile = File('${Directory.systemTemp.path}/test_offline_photo.jpg');
      await tempFile.writeAsString('fake_image_content');
    });

    tearDown(() async {
      Get.reset();
      if (await tempFile.exists()) {
        await tempFile.delete();
      }
    });

    test('met en file d attente lors d un timeout ou rupture réseau et retourne false', () async {
      mockApiClient.exceptionToThrow = DioException(
        requestOptions: RequestOptions(path: '/jalons/12/photos'),
        type: DioExceptionType.connectionTimeout,
      );

      final repo = MissionRepository(client: mockApiClient);

      final localFiles = [
        {
          'url': tempFile.path,
          'lat': 5.3484,
          'lng': -4.0192,
          'description': 'Pose compteur électrique',
        },
      ];

      final result = await repo.uploadJalonPhotos(12, localFiles);

      expect(result, isFalse);
      expect(fakeSyncService.enqueued.length, 1);
      final enqueuedReq = fakeSyncService.enqueued.first;
      expect(enqueuedReq.url, '/jalons/12/photos');
      expect(enqueuedReq.isMultipart, isTrue);
      expect(enqueuedReq.filePaths?['photos[0][photo]'], tempFile.path);
      expect(enqueuedReq.data?['photos[0][latitude]'], 5.3484);
      expect(enqueuedReq.data?['photos[0][description]'], 'Pose compteur électrique');
    });

    test('propage les erreurs métier (ex: 422 jalon déjà payé) sans mise en file', () async {
      mockApiClient.exceptionToThrow = DioException(
        requestOptions: RequestOptions(path: '/jalons/12/photos'),
        type: DioExceptionType.badResponse,
        response: Response(
          requestOptions: RequestOptions(path: '/jalons/12/photos'),
          statusCode: 422,
          data: {'message': 'Jalon déjà validé ou payé'},
        ),
      );

      final repo = MissionRepository(client: mockApiClient);

      final localFiles = [
        {
          'url': tempFile.path,
          'lat': 5.3484,
          'lng': -4.0192,
        },
      ];

      expect(
        () => repo.uploadJalonPhotos(12, localFiles),
        throwsA(isA<DioException>()),
      );
      expect(fakeSyncService.enqueued, isEmpty);
    });
  });
}
