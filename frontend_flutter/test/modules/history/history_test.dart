import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/data/models/history_models.dart';
import 'package:frontend_flutter/data/repositories/history_repository.dart';
import 'package:frontend_flutter/modules/history/controllers/paged_history_controller.dart';
import 'package:frontend_flutter/modules/history/views/history_hub_screen.dart';
import 'package:frontend_flutter/modules/history/views/history_screens.dart';
import 'package:frontend_flutter/modules/missions/widgets/tracking/mission_state_history_section.dart';
import 'package:get/get.dart';
import 'package:intl/date_symbol_data_local.dart';

import '../../helpers/fake_http_adapter.dart';
import '../../helpers/test_helpers.dart';

HistoryPage<T> _pageOf<T>(List<T> items, {int current = 1, int last = 1}) =>
    HistoryPage<T>(
      items: items,
      currentPage: current,
      lastPage: last,
      total: items.length,
    );

class _FakeHistoryRepository extends HistoryRepository {
  _FakeHistoryRepository({
    this.litiges = const [],
    this.timeline = const [],
    this.fail = false,
  });

  final List<LitigeSummary> litiges;
  final List<MissionStateLine> timeline;
  final bool fail;
  final requestedStatuts = <String?>[];

  @override
  Future<HistoryPage<LitigeSummary>> myLitiges({
    String? statut,
    int page = 1,
  }) async {
    requestedStatuts.add(statut);
    if (fail) throw Exception('panne');

    return _pageOf(
      litiges.where((l) => statut == null || l.statut == statut).toList(),
    );
  }

  @override
  Future<List<MissionStateLine>> missionTimeline(int missionId) async {
    if (fail) throw Exception('panne');
    return timeline;
  }
}

Widget _host(Widget child) => GetMaterialApp(home: child);

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
    await initializeDateFormatting('fr_FR', null);
  });

  tearDown(Get.reset);

  group('Lecture des réponses', () {
    test('une page lit ses lignes et sa pagination', () {
      final page = HistoryPage<LitigeSummary>.fromResponse(
        {
          'data': [
            {
              'id': 7,
              'missionId': 3,
              'motif': 'Malfaçon',
              'statut': 'resolu',
              'decision': 'mixte',
              'createdAt': '2026-09-01T10:00:00Z',
            },
            {'id': 8},
          ],
          'meta': {'current_page': 1, 'last_page': 3, 'total': 41},
        },
        LitigeSummary.fromJson,
      );

      expect(page.items, hasLength(2));
      expect(page.hasMore, isTrue);
      expect(page.total, 41);
      expect(page.items.first.statutLabel, 'Résolu');
      expect(page.items.first.decisionLabel, 'Responsabilité partagée');
      // Charge utile incomplète : valeurs par défaut, aucune exception.
      expect(page.items.last.motif, 'Litige');
      expect(page.items.last.createdAt, isNull);
    });

    test('une réponse sans liste est une panne, pas un historique vide', () {
      expect(
        () => HistoryPage<LitigeSummary>.fromResponse(
          {'message': 'Erreur'},
          LitigeSummary.fromJson,
        ),
        throwsFormatException,
      );
    });

    test('l\'auteur d\'un changement d\'état : nom, rôle seul, ou automatique',
        () {
      final byClient = MissionStateLine.fromJson({
        'id': 1,
        'role_label': 'Client',
        'user': {'id': 4, 'name': 'Awa Traoré', 'role': 'client'},
      });
      final byAdmin = MissionStateLine.fromJson({
        'id': 2,
        'role_label': 'Administrateur',
        'user': {'id': 9, 'name': null, 'role': 'admin'},
      });
      final automatic = MissionStateLine.fromJson({'id': 3});
      final rebuilt = MissionStateLine.fromJson({
        'id': 4,
        'reconstituted': true,
        'unknown_date': true,
      });

      expect(byClient.actorLabel, 'Awa Traoré (Client)');
      expect(byAdmin.actorLabel, 'Administrateur');
      expect(automatic.actorLabel, 'Automatique');
      expect(rebuilt.actorLabel, 'Auteur non conservé');
      expect(rebuilt.unknownDate, isTrue);
    });

    test('une course ne lit aucun code de retrait ou de réception', () {
      final record = DeliveryRecord.fromJson({
        'id': 12,
        'status': 'delivered',
        'delivery_cost': 1500,
        'delivery_city': 'Cocody',
        'supplier': {
          'name': 'Koné',
          'fournisseur_agree': {'nom_boutique': 'Quincaillerie du Plateau'},
        },
      });

      expect(record.fare, 1500);
      expect(record.supplierName, 'Quincaillerie du Plateau');
      expect(record.city, 'Cocody');
    });
  });

  group('HistoryRepository', () {
    late FakeHttpClientAdapter adapter;

    setUp(() {
      adapter = FakeHttpClientAdapter();
      ApiClient().dio.httpClientAdapter = adapter;
    });

    test('les courses passées sont demandées par statuts, page par page',
        () async {
      adapter.on(
        'GET',
        '/orders',
        const CannedResponse(
          statusCode: 200,
          body: {
            'data': [
              {'id': 5, 'status': 'delivered'},
            ],
            'meta': {'current_page': 2, 'last_page': 2, 'total': 21},
          },
        ),
      );

      final page = await HistoryRepository().deliveries(
        statuses: const ['delivered', 'cancelled'],
        page: 2,
      );

      expect(page.items.single.id, 5);
      expect(page.hasMore, isFalse);
      final query = adapter.requests.single.queryParameters;
      expect(query['status'], 'delivered,cancelled');
      expect(query['page'], 2);
      expect(query['per_page'], HistoryRepository.pageSize);
    });

    test('l\'historique d\'une mission lit les lignes du serveur', () async {
      adapter.on(
        'GET',
        '/missions/12/state-history',
        const CannedResponse(
          statusCode: 200,
          body: {
            'data': {
              'transitions': [
                {
                  'id': 1,
                  'from_state_label': 'Financée',
                  'to_state_label': 'En cours',
                },
              ],
            },
          },
        ),
      );

      final lines = await HistoryRepository().missionTimeline(12);

      expect(lines.single.toLabel, 'En cours');
    });

    test('une réponse sans lignes d\'historique est une panne', () async {
      adapter.on(
        'GET',
        '/missions/12/state-history',
        const CannedResponse(statusCode: 200, body: {'data': {}}),
      );

      expect(HistoryRepository().missionTimeline(12), throwsFormatException);
    });
  });

  group('PagedHistoryController', () {
    test('charge, complète page par page, puis filtre', () async {
      final calls = <(String?, int)>[];
      final controller = PagedHistoryController<int>((filter, page) async {
        calls.add((filter, page));
        return _pageOf(
          filter == null ? [page * 10, page * 10 + 1] : [99],
          current: page,
          last: filter == null ? 2 : 1,
        );
      });

      await controller.load();
      expect(controller.items, [10, 11]);
      expect(controller.hasMore.value, isTrue);

      await controller.loadMore();
      expect(controller.items, [10, 11, 20, 21]);
      expect(controller.hasMore.value, isFalse);

      await controller.loadMore();
      expect(calls, hasLength(2));

      await controller.setFilter('resolu');
      expect(controller.items, [99]);
      expect(calls.last, ('resolu', 1));
    });

    test('une panne garde les lignes déjà affichées et s\'annonce', () async {
      var fail = false;
      final controller = PagedHistoryController<int>((_, page) async {
        if (fail) throw Exception('panne');
        return _pageOf([1, 2], current: page, last: 2);
      });

      await controller.load();
      fail = true;
      await controller.loadMore();

      expect(controller.items, [1, 2]);
      expect(controller.errorMsg.value, isNotNull);
      expect(controller.isLoadingMore.value, isFalse);
    });
  });

  group('Écrans', () {
    const litiges = [
      LitigeSummary(id: 1, missionId: 12, motif: 'Malfaçon', statut: 'ouvert'),
      LitigeSummary(
        id: 2,
        missionId: 14,
        motif: 'Abandon de chantier',
        statut: 'resolu',
        decision: 'client',
      ),
    ];

    testWidgets('« Mes litiges » liste les litiges et filtre par état',
        (tester) async {
      final repo = _FakeHistoryRepository(litiges: litiges);
      await tester.pumpWidget(_host(MyLitigesScreen(repository: repo)));
      await tester.pumpAndSettle();

      expect(find.text('Malfaçon'), findsOneWidget);
      expect(find.text('Abandon de chantier'), findsOneWidget);
      expect(find.text('Décision : En faveur du client'), findsOneWidget);

      await tester.tap(find.text('Résolus'));
      await tester.pumpAndSettle();

      expect(find.text('Malfaçon'), findsNothing);
      expect(find.text('Abandon de chantier'), findsOneWidget);
      expect(repo.requestedStatuts, [null, 'resolu']);
    });

    testWidgets('sans litige, l\'écran le dit explicitement', (tester) async {
      await tester.pumpWidget(
        _host(MyLitigesScreen(repository: _FakeHistoryRepository())),
      );
      await tester.pumpAndSettle();

      expect(find.text('Aucun litige'), findsOneWidget);
    });

    testWidgets(
        'une panne s\'annonce avec « Réessayer », jamais « Aucun litige »',
        (tester) async {
      await tester.pumpWidget(
        _host(MyLitigesScreen(repository: _FakeHistoryRepository(fail: true))),
      );
      await tester.pumpAndSettle();

      expect(find.text('Réessayer'), findsOneWidget);
      expect(find.text('Aucun litige'), findsNothing);
    });

    testWidgets('l\'historique d\'une mission ne se charge qu\'à l\'ouverture',
        (tester) async {
      final repo = _FakeHistoryRepository(
        timeline: [
          MissionStateLine(
            id: 2,
            fromLabel: 'En litige',
            toLabel: 'Terminée',
            actorRole: 'Administrateur',
            reason: 'Litige arbitré',
            reconstituted: false,
            unknownDate: false,
            at: DateTime(2026, 10, 2, 10),
          ),
          const MissionStateLine(
            id: 1,
            fromLabel: 'Brouillon',
            toLabel: 'Financée',
            reconstituted: true,
            unknownDate: true,
          ),
        ],
      );
      await tester.pumpWidget(
        _host(
          Scaffold(
            body: SingleChildScrollView(
              child: MissionStateHistorySection(
                missionId: 12,
                repository: repo,
              ),
            ),
          ),
        ),
      );

      expect(find.text('En litige → Terminée'), findsNothing);

      await tester.tap(find.text('Historique de la mission'));
      await tester.pumpAndSettle();

      expect(find.text('En litige → Terminée'), findsOneWidget);
      expect(find.text('02/10/2026 10:00 · Administrateur'), findsOneWidget);
      expect(find.text('Litige arbitré'), findsOneWidget);
      expect(
        find.text('Date non conservée · Auteur non conservé'),
        findsOneWidget,
      );
      expect(find.textContaining('Reconstitué'), findsOneWidget);
    });

    testWidgets('une panne de l\'historique d\'une mission s\'annonce',
        (tester) async {
      await tester.pumpWidget(
        _host(
          Scaffold(
            body: MissionStateHistorySection(
              missionId: 12,
              repository: _FakeHistoryRepository(fail: true),
            ),
          ),
        ),
      );

      await tester.tap(find.text('Historique de la mission'));
      await tester.pumpAndSettle();

      expect(find.text('Réessayer'), findsOneWidget);
      expect(find.textContaining('Aucun changement'), findsNothing);
    });
  });

  group('Compléments (Chantier 21)', () {
    late FakeHttpClientAdapter adapter;

    setUp(() {
      adapter = FakeHttpClientAdapter();
      ApiClient().dio.httpClientAdapter = adapter;
    });

    testWidgets('un litige de commande affiche sa décision et son motif',
        (tester) async {
      adapter.on(
        'GET',
        '/orders/disputes',
        const CannedResponse(
          statusCode: 200,
          body: {
            'data': [
              {
                'id': 3,
                'order_id': 100,
                'reason': 'Colis incomplet',
                'statut': 'resolu',
                'statut_label': 'Résolu',
                'outcome_label': 'Réclamation du client acceptée',
                'resolution_note': 'Sacs manquants confirmés.',
                'opened_at': '2026-10-01T09:00:00Z',
                'resolved_at': '2026-10-02T09:00:00Z',
                'order_total': 20000,
              },
            ],
            'meta': {'current_page': 1, 'last_page': 1, 'total': 1},
          },
        ),
      );

      await tester.runAsync(() async {
        await tester.pumpWidget(_host(const OrderDisputesScreen()));
        await Future<void>.delayed(const Duration(milliseconds: 200));
      });
      await tester.pumpAndSettle();

      expect(find.text('Commande #100'), findsOneWidget);
      expect(find.text('Motif : Colis incomplet'), findsOneWidget);
      expect(
        find.text('Décision : Réclamation du client acceptée'),
        findsOneWidget,
      );
      expect(find.text('Sacs manquants confirmés.'), findsOneWidget);
    });

    test('l\'historique des versements est demandé par statut et par page',
        () async {
      adapter.on(
        'GET',
        '/payouts',
        const CannedResponse(
          statusCode: 200,
          body: {
            'data': [
              {
                'id': 8,
                'reference': 'PAY-8',
                'montant': 25000,
                'statut': 'verse',
                'statut_label': 'Versé',
                'paid_at': '2026-10-02T09:00:00Z',
              },
            ],
            'meta': {'current_page': 1, 'last_page': 4, 'total': 70},
          },
        ),
      );

      final page = await HistoryRepository().payouts(statut: 'verse');

      expect(page.items.single.isPaid, isTrue);
      expect(page.items.single.paidAt, isNotNull);
      expect(page.hasMore, isTrue);
      final query = adapter.requests.single.queryParameters;
      expect(query['statut'], 'verse');
      expect(query['per_page'], HistoryRepository.pageSize);
    });

    testWidgets('le Référent ouvre directement l\'historique d\'un chantier',
        (tester) async {
      final repo = _FakeHistoryRepository(
        timeline: const [
          MissionStateLine(
            id: 1,
            fromLabel: 'Financée',
            toLabel: 'En cours',
            actorRole: 'Client',
            reconstituted: false,
            unknownDate: true,
          ),
        ],
      );

      await tester.pumpWidget(
        _host(MissionHistoryScreen(missionId: 12, repository: repo)),
      );
      await tester.pumpAndSettle();

      expect(find.text('Chantier #12'), findsOneWidget);
      expect(find.text('Financée → En cours'), findsOneWidget);
      // Le Référent voit le rôle, jamais le nom d'une partie.
      expect(find.text('Date non conservée · Client'), findsOneWidget);
    });
  });

  group('« Mon historique »', () {
    List<String> titles(String role) =>
        historyEntriesFor(role).map((e) => e.title).toList();

    test('chaque rôle a ses rubriques', () {
      expect(titles('client'), [
        'Paiements',
        'Missions',
        'Litiges',
        'Commandes de matériaux',
        'Litiges de commandes',
      ]);
      expect(titles('artisan'), [
        'Paiements',
        'Versements',
        'Chantiers',
        'Litiges',
        'Bons matériels',
      ]);
      expect(titles('fournisseur'), [
        'Paiements',
        'Commandes',
        'Virements',
        'Litiges de commandes',
        'Litiges de chantiers',
      ]);
      expect(
        titles('livreur'),
        ['Gains', 'Courses', 'Retraits', 'Courses en litige'],
      );
      expect(titles('driver'), titles('livreur'));
      expect(titles('referent'), ['Inspections réalisées', 'Litiges']);
    });

    testWidgets('l\'écran affiche les rubriques du rôle', (tester) async {
      await tester.pumpWidget(_host(const HistoryHubScreen(role: 'referent')));

      expect(find.text('Mon historique'), findsOneWidget);
      expect(find.text('Inspections réalisées'), findsOneWidget);
      expect(find.text('Paiements'), findsNothing);
    });
  });
}
