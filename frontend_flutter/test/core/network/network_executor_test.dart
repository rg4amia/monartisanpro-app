import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/network_executor.dart';

DioException _dioError({int? status, DioExceptionType? type}) {
  final req = RequestOptions(path: '/test');
  return DioException(
    requestOptions: req,
    type: type ?? DioExceptionType.badResponse,
    response: status == null
        ? null
        : Response<dynamic>(requestOptions: req, statusCode: status),
  );
}

Response<dynamic> _ok() => Response<dynamic>(
      requestOptions: RequestOptions(path: '/test'),
      data: 'ok',
    );

void main() {
  group('NetworkExecutor.run', () {
    test('retourne immédiatement en cas de succès (1 appel)', () async {
      var calls = 0;
      final res = await NetworkExecutor.run(() async {
        calls++;
        return _ok();
      });
      expect(calls, 1);
      expect(res.data, 'ok');
    });

    test('rejoue sur connectionError puis réussit', () async {
      var calls = 0;
      final res = await NetworkExecutor.run(
        () async {
          calls++;
          if (calls < 3) {
            throw _dioError(type: DioExceptionType.connectionError);
          }
          return _ok();
        },
        retryDelay: Duration.zero,
      );
      expect(calls, 3);
      expect(res.data, 'ok');
    });

    test('épuise maxRetries puis relance la dernière erreur', () async {
      var calls = 0;
      await expectLater(
        NetworkExecutor.run(
          () async {
            calls++;
            throw _dioError(type: DioExceptionType.connectionError);
          },
          maxRetries: 3,
          retryDelay: Duration.zero,
        ),
        throwsA(isA<DioException>()),
      );
      expect(calls, 3);
    });

    test('ne rejoue jamais sur une erreur 4xx métier (400)', () async {
      var calls = 0;
      await expectLater(
        NetworkExecutor.run(
          () async {
            calls++;
            throw _dioError(status: 400);
          },
          retryDelay: Duration.zero,
        ),
        throwsA(isA<DioException>()),
      );
      expect(calls, 1);
    });

    test('rejoue sur 429 (rate limit)', () async {
      var calls = 0;
      await expectLater(
        NetworkExecutor.run(
          () async {
            calls++;
            throw _dioError(status: 429);
          },
          maxRetries: 3,
          retryDelay: Duration.zero,
        ),
        throwsA(isA<DioException>()),
      );
      expect(calls, 3);
    });

    test('rejoue sur 408 (request timeout)', () async {
      var calls = 0;
      await expectLater(
        NetworkExecutor.run(
          () async {
            calls++;
            throw _dioError(status: 408);
          },
          maxRetries: 2,
          retryDelay: Duration.zero,
        ),
        throwsA(isA<DioException>()),
      );
      expect(calls, 2);
    });
  });
}
