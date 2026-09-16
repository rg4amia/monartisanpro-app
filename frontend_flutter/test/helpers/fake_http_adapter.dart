import 'dart:convert';
import 'dart:typed_data';

import 'package:dio/dio.dart';

/// Une réponse HTTP en boîte, prête à être rejouée par [FakeHttpClientAdapter].
class CannedResponse {
  const CannedResponse({required this.statusCode, this.body = const {}});

  final int statusCode;
  final Object? body;
}

/// Remplace le transport réseau réel de [Dio] par des réponses préparées à
/// l'avance, indexées par méthode + chemin de requête. Permet de tester les
/// contrôleurs qui utilisent directement le singleton `ApiClient()` sans
/// dépendre d'un serveur backend réel ni d'une librairie de mock externe.
///
/// Usage :
/// ```dart
/// final adapter = FakeHttpClientAdapter()
///   ..on('GET', '/wallet/balance', const CannedResponse(statusCode: 200, body: {...}));
/// ApiClient().dio.httpClientAdapter = adapter;
/// ```
class FakeHttpClientAdapter implements HttpClientAdapter {
  final Map<String, CannedResponse> _routes = {};
  final List<RequestOptions> requests = [];

  void on(String method, String path, CannedResponse response) {
    _routes[_key(method, path)] = response;
  }

  // `ApiClient` retire le `/` initial du chemin avant l'envoi réel de la
  // requête (voir `_DynamicBaseUrlInterceptor`) : on normalise donc les deux
  // côtés pour que `on('GET', '/wallets/balance', ...)` corresponde bien à
  // la requête effectivement observée par l'adaptateur.
  String _key(String method, String path) =>
      '${method.toUpperCase()} ${path.startsWith('/') ? path.substring(1) : path}';

  @override
  Future<ResponseBody> fetch(
    RequestOptions options,
    Stream<Uint8List>? requestStream,
    Future<void>? cancelFuture,
  ) async {
    requests.add(options);

    final response = _routes[_key(options.method, options.path)];
    if (response == null) {
      throw DioException(
        requestOptions: options,
        type: DioExceptionType.connectionError,
        message: 'Aucune réponse simulée enregistrée pour '
            '${options.method} ${options.path}',
      );
    }

    final body = response.body is String
        ? response.body as String
        : jsonEncode(response.body);

    return ResponseBody.fromString(
      body,
      response.statusCode,
      headers: {
        Headers.contentTypeHeader: [Headers.jsonContentType],
      },
    );
  }

  @override
  void close({bool force = false}) {}
}
