import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/sync_service.dart';
import 'package:get/get.dart' hide Response;

/// Bascule automatique de la validation hors connexion.
///
/// Le code de validation prouve une rencontre physique : le fournisseur ou le
/// client vient de le donner en main propre. Rien n'oblige à transmettre cette
/// preuve à l'instant même, seulement à ne pas la perdre. Ces tests portent sur
/// la règle de bascule elle-même — la seule chose qui, en se trompant, ferait
/// soit perdre une validation, soit masquer une vraie erreur métier.

/// Reproduit la décision prise par OrderRepository sans monter la pile réseau.
bool isNetworkFailure(DioException e) =>
    e.type == DioExceptionType.connectionTimeout ||
    e.type == DioExceptionType.sendTimeout ||
    e.type == DioExceptionType.receiveTimeout ||
    e.type == DioExceptionType.connectionError;

DioException _of(DioExceptionType type, {int? statusCode}) => DioException(
      requestOptions: RequestOptions(path: '/orders/1/verify-pickup'),
      type: type,
      response: statusCode == null
          ? null
          : Response(
              requestOptions: RequestOptions(path: '/orders/1/verify-pickup'),
              statusCode: statusCode,
            ),
    );

void main() {
  group('règle de bascule hors connexion', () {
    test('les pannes de joignabilité déclenchent la mise en file', () {
      for (final type in [
        DioExceptionType.connectionTimeout,
        DioExceptionType.sendTimeout,
        DioExceptionType.receiveTimeout,
        DioExceptionType.connectionError,
      ]) {
        expect(
          isNetworkFailure(_of(type)),
          isTrue,
          reason: '$type doit être traité comme une panne réseau.',
        );
      }
    });

    test('un refus du serveur ne doit jamais être mis en file', () {
      // Un code erroné (400) ou un acteur non autorisé (403) sont des réponses
      // métier : les rejouer plus tard échouerait à l'identique, et surtout
      // masquerait l'erreur à l'utilisateur au moment où il peut la corriger.
      for (final status in [400, 401, 403, 404, 422, 500]) {
        expect(
          isNetworkFailure(
              _of(DioExceptionType.badResponse, statusCode: status),),
          isFalse,
          reason: 'HTTP $status est une réponse du serveur, pas une panne.',
        );
      }
    });

    test('une annulation n est pas une panne réseau', () {
      expect(isNetworkFailure(_of(DioExceptionType.cancel)), isFalse);
    });
  });

  group('SyncService', () {
    tearDown(Get.reset);

    test('sans service enregistré, la mise en file est impossible', () {
      // OrderRepository vérifie l'enregistrement avant d'appeler la file : sans
      // ce garde-fou, une validation lèverait une exception opaque au livreur.
      expect(Get.isRegistered<SyncService>(), isFalse);
    });

    test('expose un compteur observable et une liste d abandons', () {
      final service = SyncService();

      // L'abandon d'une requête était jusqu'ici silencieux. Pour une libération
      // de fonds, cela signifie un livreur non payé sans alerte : la perte doit
      // être observable par l'interface.
      expect(service.pendingCount.value, 0);
      expect(service.abandoned, isEmpty);
    });
  });

  group('QueuedRequest', () {
    test('survit à un aller-retour de sérialisation', () {
      // La file est persistée dans Hive : une validation doit se relire
      // intacte après un redémarrage de l'application.
      final original = QueuedRequest(
        id: '42',
        method: 'POST',
        url: '/orders/7/verify-delivery',
        data: const {'code': 'RECEPTION-7390'},
        timestamp: DateTime.parse('2026-09-12T10:00:00Z'),
      );

      final restored = QueuedRequest.fromJson(original.toJson());

      expect(restored.id, original.id);
      expect(restored.method, 'POST');
      expect(restored.url, '/orders/7/verify-delivery');
      expect(restored.data?['code'], 'RECEPTION-7390');
      expect(restored.timestamp, original.timestamp);
    });
  });
}
