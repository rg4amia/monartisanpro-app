import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/errors/app_exception.dart';
import 'package:frontend_flutter/core/errors/app_exception_mapper.dart';

DioException _dio({int? status, Object? data, DioExceptionType? type}) {
  final req = RequestOptions(path: '/x');
  return DioException(
    requestOptions: req,
    type: type ?? DioExceptionType.badResponse,
    response: status == null
        ? null
        : Response<dynamic>(
            requestOptions: req,
            statusCode: status,
            data: data,
          ),
  );
}

void main() {
  test('401 → AuthException(isExpired)', () {
    final e = toAppException(_dio(status: 401));
    expect(e, isA<AuthException>());
    expect((e as AuthException).isExpired, isTrue);
  });

  test('403 → AuthException', () {
    expect(toAppException(_dio(status: 403)), isA<AuthException>());
  });

  test('422 → ValidationException avec fieldErrors', () {
    final e = toAppException(_dio(status: 422, data: {
      'message': 'Champs invalides',
      'errors': {
        'phone': ['Le numéro est invalide'],
      },
    }));
    expect(e, isA<ValidationException>());
    final v = e as ValidationException;
    expect(v.message, 'Champs invalides');
    expect(v.fieldErrors['phone'], ['Le numéro est invalide']);
  });

  test('500 → NetworkException', () {
    final e = toAppException(_dio(status: 500));
    expect(e, isA<NetworkException>());
    expect((e as NetworkException).statusCode, 500);
  });

  test('connectionError → NetworkException', () {
    expect(
      toAppException(_dio(type: DioExceptionType.connectionError)),
      isA<NetworkException>(),
    );
  });

  test('erreur inconnue → UnexpectedException', () {
    expect(toAppException(ArgumentError('x')), isA<UnexpectedException>());
  });

  test('une AppException est renvoyée telle quelle', () {
    const original = CacheException('boom');
    expect(identical(toAppException(original), original), isTrue);
  });
}
