import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/cache/cache_store.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/data/models/cancellation_preview.dart';
import 'package:frontend_flutter/data/models/jalon_model.dart';
import 'package:frontend_flutter/data/models/mission_model.dart';
import 'package:frontend_flutter/modules/missions/controllers/missions_controller.dart';
import 'package:frontend_flutter/modules/missions/widgets/tracking/bottom_actions.dart';
import 'package:frontend_flutter/modules/missions/widgets/tracking/cancel_mission_dialog.dart';
import 'package:get/get.dart';

import '../../helpers/fake_http_adapter.dart';
import '../../helpers/getx_snackbar_harness.dart';
import '../../helpers/test_helpers.dart';

/// Chantier 19 — cycle de vie des missions côté mobile : états du serveur,
/// validation finale, annulation avec aperçu des montants.

Map<String, dynamic> _missionJson(
  String status, {
  Map<String, dynamic> extra = const {},
}) =>
    {
      'id': 7,
      'client_id': 10,
      'artisan_id': 20,
      'status': status,
      'montant_total': 110000,
      'montant_materiaux': 0,
      'montant_mo': 110000,
      'created_at': '2026-10-01T10:00:00Z',
      ...extra,
    };

MissionModel _mission(String status, {Map<String, dynamic> extra = const {}}) =>
    MissionModel.fromJson(_missionJson(status, extra: extra));

Future<void> _pumpActions(
  WidgetTester tester, {
  required String role,
  required MissionModel mission,
  JalonModel? nextPendingJalon,
}) {
  return tester.pumpWidget(
    GetMaterialApp(
      home: Scaffold(
        body: BottomActions(
          role: role,
          mission: mission,
          devis: null,
          nextPendingJalon: nextPendingJalon,
          nextSubmittedJalon: null,
        ),
      ),
    ),
  );
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();
  FlutterSecureStorage.setMockInitialValues({});

  late FakeHttpClientAdapter adapter;

  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
  });

  setUp(() {
    adapter = FakeHttpClientAdapter();
    ApiClient().dio.httpClientAdapter = adapter;
  });

  tearDown(() async {
    Get.reset();
    await TestHelpers.cleanupTestData();
    await CacheStore.wipeAll();
  });

  group('MissionModel — états du serveur', () {
    test('conserve l\'état du serveur à côté du libellé hérité', () {
      final mission = _mission(
        'pending_approval',
        extra: {
          'statusLabel': 'En attente de validation client',
          'finalApprovalDeadline': '2026-10-06T10:00:00Z',
        },
      );

      expect(mission.fsmStatus, 'pending_approval');
      expect(mission.status, 'en_cours');
      expect(mission.statusLabel, 'En attente de validation client');
      expect(mission.awaitsFinalApproval, isTrue);
      expect(mission.finalApprovalDeadline, isNotNull);
      expect(mission.canBeCancelled, isFalse);
    });

    test('distingue les trois états ramenés à « en_attente »', () {
      for (final state in [
        'draft',
        'pending_artisan_acceptance',
        'pending_funding',
      ]) {
        final mission = _mission(state);
        expect(mission.status, 'en_attente');
        expect(mission.fsmStatus, state);
        expect(mission.canBeCancelled, isTrue, reason: state);
      }
    });

    test('n\'autorise l\'annulation que tant que le chantier n\'a pas commencé',
        () {
      expect(_mission('funded_locked').canBeCancelled, isTrue);
      expect(_mission('in_progress').canBeCancelled, isFalse);
      expect(_mission('completed').canBeCancelled, isFalse);
      expect(_mission('disputed').canBeCancelled, isFalse);
      expect(_mission('cancelled').canBeCancelled, isFalse);
      expect(_mission('cancelled').isCancelled, isTrue);
    });

    test('tolère une charge utile sans les nouveaux champs', () {
      final mission = _mission('draft');

      expect(mission.statusLabel, isNull);
      expect(mission.artisanResponseDeadline, isNull);
      expect(mission.finalApprovalDeadline, isNull);
      expect(mission.cancellationPenalty, isNull);
      expect(mission.cancellationRefund, isNull);
    });

    test('lit pénalité et remboursement, entiers ou décimaux', () {
      final mission = _mission(
        'cancelled',
        extra: {'cancellationPenalty': 7700, 'cancellationRefund': 102300.0},
      );

      expect(mission.cancellationPenalty, 7700);
      expect(mission.cancellationRefund, 102300);
    });

    test('survit à un aller-retour par le cache', () {
      final restored = MissionModel.fromJson(
        _mission(
          'pending_approval',
          extra: {'statusLabel': 'En attente de validation client'},
        ).toJson(),
      );

      expect(restored.statusLabel, 'En attente de validation client');
    });
  });

  group('CancellationPreview', () {
    test('lit l\'aperçu du serveur', () {
      final preview = CancellationPreview.fromJson({
        'allowed': true,
        'funded': true,
        'escrow': 110000,
        'penalty_rate': 7,
        'penalty': 7700,
        'refund': 102300,
        'reason': null,
      });

      expect(preview.allowed, isTrue);
      expect(preview.penaltyRate, 7.0);
      expect(preview.penalty, 7700);
      expect(preview.refund, 102300);
    });

    test('une charge incomplète n\'autorise rien', () {
      final preview = CancellationPreview.fromJson(const {});

      expect(preview.allowed, isFalse);
      expect(preview.funded, isFalse);
      expect(preview.refund, 0);
    });
  });

  group('BottomActions', () {
    testWidgets('le client valide la fin du chantier en attente de validation',
        (tester) async {
      await _pumpActions(
        tester,
        role: 'client',
        mission: _mission('pending_approval'),
      );

      expect(find.text('Valider la fin du chantier'), findsOneWidget);
      expect(find.text('Annuler'), findsNothing);
    });

    testWidgets('le client peut annuler une demande non payée', (tester) async {
      await _pumpActions(tester, role: 'client', mission: _mission('draft'));

      expect(find.text('Annuler'), findsOneWidget);
    });

    testWidgets('le client peut annuler une mission financée non commencée',
        (tester) async {
      await _pumpActions(
        tester,
        role: 'client',
        mission: _mission('funded_locked'),
      );

      expect(find.text('Annuler'), findsOneWidget);
      expect(find.text('Signaler un litige'), findsOneWidget);
    });

    testWidgets('l\'annulation n\'est plus proposée une fois le chantier lancé',
        (tester) async {
      await _pumpActions(
        tester,
        role: 'client',
        mission: _mission('in_progress'),
      );

      expect(find.text('Annuler'), findsNothing);
      expect(find.text('Signaler un litige'), findsOneWidget);
    });

    testWidgets('l\'artisan ne voit plus de bouton « Demarrer »',
        (tester) async {
      final jalon = JalonModel.fromJson({
        'id': 1,
        'mission_id': 7,
        'ordre': 1,
        'description': 'Dépose',
        'montant': 55000,
        'statut': 'en_attente',
      });

      await _pumpActions(
        tester,
        role: 'artisan',
        mission: _mission('funded_locked'),
        nextPendingJalon: jalon,
      );

      expect(find.text('Demarrer'), findsNothing);
      expect(find.text('Démarrer'), findsNothing);
      // Le chantier démarre à la soumission de la première étape.
      expect(find.text('Valider l\'étape'), findsOneWidget);
    });

    testWidgets('l\'artisan n\'a jamais de bouton d\'annulation',
        (tester) async {
      await _pumpActions(
        tester,
        role: 'artisan',
        mission: _mission('funded_locked'),
      );

      expect(find.text('Annuler'), findsNothing);
    });
  });

  group('CancelMissionDialog', () {
    Future<void> pumpDialog(WidgetTester tester, CancellationPreview preview) {
      return tester.pumpWidget(
        GetMaterialApp(
          home: Scaffold(body: CancelMissionDialog(preview: preview)),
        ),
      );
    }

    testWidgets('affiche les montants calculés par le serveur', (tester) async {
      await pumpDialog(
        tester,
        const CancellationPreview(
          allowed: true,
          funded: true,
          escrow: 110000,
          penaltyRate: 7,
          penalty: 7700,
          refund: 102300,
        ),
      );

      expect(find.text('Annuler la mission ?'), findsOneWidget);
      expect(find.text('Pénalité (7 %)'), findsOneWidget);
      expect(find.textContaining('110'), findsWidgets);
      // L'espace des milliers est une espace insécable.
      expect(find.textContaining(RegExp(r'7\s700')), findsOneWidget);
      expect(find.textContaining(RegExp(r'102\s300')), findsOneWidget);
      expect(find.text('Annuler la mission'), findsOneWidget);
    });

    testWidgets('annonce une annulation sans frais avant tout paiement',
        (tester) async {
      await pumpDialog(
        tester,
        const CancellationPreview(
          allowed: true,
          funded: false,
          escrow: 0,
          penaltyRate: 0,
          penalty: 0,
          refund: 0,
        ),
      );

      expect(find.textContaining('sans frais'), findsOneWidget);
      expect(find.textContaining('Pénalité'), findsNothing);
    });

    testWidgets('explique le refus et ne propose pas de confirmer',
        (tester) async {
      await pumpDialog(
        tester,
        const CancellationPreview(
          allowed: false,
          funded: true,
          escrow: 110000,
          penaltyRate: 7,
          penalty: 7700,
          refund: 102300,
          reason:
              'Une étape du chantier a déjà été soumise : cette mission ne peut plus être annulée. En cas de désaccord, ouvrez un litige.',
        ),
      );

      expect(find.text('Annulation impossible'), findsOneWidget);
      expect(find.textContaining('ouvrez un litige'), findsOneWidget);
      expect(find.text('Annuler la mission'), findsNothing);
      expect(find.text('Fermer'), findsOneWidget);
    });
  });

  group('MissionsController', () {
    testWidgets(
        '« Voir plus » ajoute la page suivante de la liste des missions',
        (tester) async {
      List<Map<String, dynamic>> page(int from, int count) => [
            for (var id = from; id < from + count; id++)
              {..._missionJson('in_progress'), 'id': id},
          ];

      adapter.on(
        'GET',
        '/missions',
        CannedResponse(
          statusCode: 200,
          body: {'success': true, 'data': page(100, 20)},
        ),
      );
      final controller = MissionsController();

      await runControllerAction(
        tester,
        () => controller.loadMissions(status: 'all', isRefresh: true),
      );
      expect(controller.missions, hasLength(20));
      expect(controller.hasMoreMissions.value, isTrue);

      adapter.on(
        'GET',
        '/missions',
        CannedResponse(
          statusCode: 200,
          body: {'success': true, 'data': page(120, 3)},
        ),
      );
      await runControllerAction(tester, controller.loadMoreMissions);

      expect(controller.missions, hasLength(23));
      // Page incomplète : il n'y a plus rien à charger.
      expect(controller.hasMoreMissions.value, isFalse);
      final last =
          adapter.requests.lastWhere((r) => r.path.endsWith('missions'));
      expect(last.queryParameters['page'], 2);
    });

    testWidgets('cancelMission envoie le motif et garde la mission annulée',
        (tester) async {
      adapter
        ..on(
          'POST',
          '/missions/7/cancel',
          CannedResponse(
            statusCode: 200,
            body: {
              'success': true,
              'data': _missionJson(
                'cancelled',
                extra: {
                  'statusLabel': 'Annulée',
                  'cancellationPenalty': 7700,
                  'cancellationRefund': 102300,
                },
              ),
            },
          ),
        )
        ..on(
          'GET',
          '/missions',
          const CannedResponse(
            statusCode: 200,
            body: {'success': true, 'data': <dynamic>[]},
          ),
        );
      final controller = MissionsController();
      var done = false;

      await runControllerAction(tester, () async {
        done = await controller.cancelMission(7, reason: 'Travaux reportés');
      });

      expect(done, isTrue);
      expect(controller.currentMission.value?.fsmStatus, 'cancelled');
      expect(controller.currentMission.value?.cancellationRefund, 102300);
      final request =
          adapter.requests.firstWhere((r) => r.path.endsWith('cancel'));
      expect(request.data, {'reason': 'Travaux reportés'});
    });

    testWidgets('cancelMission remonte le refus du serveur', (tester) async {
      adapter.on(
        'POST',
        '/missions/7/cancel',
        const CannedResponse(
          statusCode: 422,
          body: {
            'success': false,
            'message':
                'Le chantier a commencé : cette mission ne peut plus être annulée. En cas de désaccord, ouvrez un litige.',
          },
        ),
      );
      final controller = MissionsController();
      var done = true;

      await runControllerAction(tester, () async {
        done = await controller.cancelMission(7);
      });

      expect(done, isFalse);
      expect(controller.errorMsg.value, contains('ouvrez un litige'));
    });

    testWidgets('loadCancellationPreview lit l\'aperçu du serveur',
        (tester) async {
      adapter.on(
        'GET',
        '/missions/7/cancellation-preview',
        const CannedResponse(
          statusCode: 200,
          body: {
            'success': true,
            'data': {
              'allowed': true,
              'funded': true,
              'escrow': 110000,
              'penalty_rate': 7,
              'penalty': 7700,
              'refund': 102300,
            },
          },
        ),
      );
      final controller = MissionsController();
      CancellationPreview? preview;

      await runControllerAction(tester, () async {
        preview = await controller.loadCancellationPreview(7);
      });

      expect(preview?.refund, 102300);
    });

    testWidgets('loadCancellationPreview renvoie null si le serveur échoue',
        (tester) async {
      final controller = MissionsController();
      CancellationPreview? preview;

      await runControllerAction(tester, () async {
        preview = await controller.loadCancellationPreview(7);
      });

      expect(preview, isNull);
    });
  });
}
