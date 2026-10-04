import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/services/notification_service.dart';
import 'package:frontend_flutter/data/models/notification_model.dart';
import 'package:frontend_flutter/data/repositories/notification_repository.dart';
import 'package:frontend_flutter/modules/notifications/controllers/notification_preferences_controller.dart';
import 'package:frontend_flutter/modules/notifications/controllers/notifications_controller.dart';
import 'package:frontend_flutter/modules/notifications/views/notification_preferences_screen.dart';
import 'package:get/get.dart';

NotificationModel _notification(
  int id, {
  bool read = false,
  String? domain = 'missions',
}) =>
    NotificationModel(
      id: id,
      type: 'mission',
      title: 'Titre $id',
      message: 'Texte',
      isRead: read,
      createdAt: '2026-10-04T10:00:00Z',
      domain: domain,
    );

const _domains = [
  NotificationDomain(key: 'missions', label: 'Missions et devis', unread: 4),
  NotificationDomain(key: 'finances', label: 'Retraits', unread: 3),
];

NotificationDomainPreference _preference(
  String key, {
  bool push = true,
  bool sms = false,
  bool pushEditable = true,
  bool smsEditable = false,
  bool essential = false,
}) =>
    NotificationDomainPreference(
      key: key,
      label: 'Rubrique $key',
      push: push,
      sms: sms,
      pushEditable: pushEditable,
      smsEditable: smsEditable,
      essential: essential,
    );

class _FakeRepository extends NotificationRepository {
  _FakeRepository({this.preferences});

  bool fail = false;
  int lastPage = 1;
  NotificationPreferences? preferences;

  final requestedDomains = <String?>[];
  final requestedPages = <int>[];
  final savedPromotional = <bool?>[];
  final savedDomains = <Map<String, Map<String, bool>>?>[];
  final markedRead = <int>[];

  @override
  Future<NotificationPage> fetchPage({
    int page = 1,
    String? domain,
    int perPage = 30,
  }) async {
    requestedDomains.add(domain);
    requestedPages.add(page);
    if (fail) throw Exception('panne');

    return NotificationPage(
      // Deux notifications chargées, alors que sept sont non lues en tout.
      items: [_notification(page * 10), _notification(page * 10 + 1)],
      currentPage: page,
      lastPage: lastPage,
      unread: 7,
      domains: _domains,
    );
  }

  @override
  Future<void> markRead(int id) async => markedRead.add(id);

  @override
  Future<void> markAllRead() async {}

  @override
  Future<NotificationPreferences> getPreferences() async {
    if (fail) throw Exception('panne');
    return preferences!;
  }

  @override
  Future<NotificationPreferences> updatePreferences({
    bool? promotionalPush,
    Map<String, Map<String, bool>>? domains,
  }) async {
    savedPromotional.add(promotionalPush);
    savedDomains.add(domains);
    if (fail) throw Exception('panne');

    return NotificationPreferences(
      promotionalPush: promotionalPush ?? preferences!.promotionalPush,
      domains: preferences!.domains
          .map(
            (d) => domains?[d.key] == null
                ? d
                : d.copyWith(
                    push: domains![d.key]!['push'],
                    sms: domains[d.key]!['sms'],
                  ),
          )
          .toList(),
    );
  }
}

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  tearDown(Get.reset);

  group('NotificationPage', () {
    test('lit le nombre de non lues et les rubriques transmis par le serveur',
        () {
      final page = NotificationPage.fromResponse({
        'data': [
          {'id': 1, 'type': 'mission', 'domain': 'missions', 'isRead': false},
        ],
        'meta': {
          'current_page': 1,
          'last_page': 3,
          'unread': 12,
          'domains': [
            {'key': 'missions', 'label': 'Missions et devis', 'unread': 2},
          ],
        },
      });

      expect(page.unread, 12);
      expect(page.hasMore, isTrue);
      expect(page.items.single.domain, 'missions');
      expect(page.domains.single.label, 'Missions et devis');
    });

    test('une ancienne réponse sans rubriques reste lisible', () {
      final page = NotificationPage.fromResponse({
        'data': [
          {'id': 1, 'type': 'payment', 'isRead': false},
          {'id': 2, 'type': 'payment', 'isRead': true},
        ],
        'meta': {'total': 2},
      });

      expect(page.domains, isEmpty);
      expect(page.hasMore, isFalse);
      expect(page.items.first.domain, isNull);
    });

    test('une réponse sans liste est une erreur, jamais une liste vide', () {
      expect(
        () => NotificationPage.fromResponse({'success': false}),
        throwsFormatException,
      );
    });
  });

  group('NotificationPreferences', () {
    test("l'accord aux offres n'est jamais supposé", () {
      final preferences = NotificationPreferences.fromResponse({
        'data': {
          'domains': [
            {'key': 'missions', 'label': 'Missions', 'push_editable': true},
          ],
        },
      });

      expect(preferences.promotionalPush, isFalse);
      expect(preferences.domains.single.push, isTrue);
      expect(preferences.domains.single.smsEditable, isFalse);
    });

    test('une réponse sans rubriques est une erreur', () {
      expect(
        () => NotificationPreferences.fromResponse({'data': {}}),
        throwsFormatException,
      );
      expect(
        () => NotificationPreferences.fromResponse({'success': true}),
        throwsFormatException,
      );
    });
  });

  group('NotificationsController', () {
    test('le badge porte sur toutes les notifications, pas sur la page',
        () async {
      final controller = NotificationsController(repository: _FakeRepository());

      await controller.load();

      expect(controller.notifications, hasLength(2));
      expect(controller.unreadCount, 7);
      expect(controller.domains.map((d) => d.key), ['missions', 'finances']);
    });

    test('un onglet demande sa rubrique au serveur', () async {
      final repo = _FakeRepository();
      final controller = NotificationsController(repository: repo);

      await controller.load();
      await controller.selectTab('finances');
      await controller.selectTab(NotificationsController.allTab);

      expect(repo.requestedDomains, [null, 'finances', null]);
    });

    test('« Voir plus » ajoute la page suivante', () async {
      final repo = _FakeRepository()..lastPage = 2;
      final controller = NotificationsController(repository: repo);

      await controller.load();
      expect(controller.hasMore.value, isTrue);

      await controller.loadMore();

      expect(repo.requestedPages, [1, 2]);
      expect(controller.notifications.map((n) => n.id), [10, 11, 20, 21]);
      expect(controller.hasMore.value, isFalse);
    });

    test('une panne garde la liste affichée et s\'annonce', () async {
      final repo = _FakeRepository();
      final controller = NotificationsController(repository: repo);
      await controller.load();

      repo.fail = true;
      await controller.load();

      expect(controller.notifications, hasLength(2));
      expect(controller.unreadCount, 7);
      expect(controller.errorMsg.value, isNotNull);
    });

    test('lire une notification décompte le badge et sa rubrique', () async {
      final repo = _FakeRepository();
      final controller = NotificationsController(repository: repo);
      await controller.load();

      await controller.markRead(10);
      // Une seconde lecture de la même notification ne décompte rien.
      await controller.markRead(10);

      expect(repo.markedRead, [10, 10]);
      expect(controller.notifications.first.isRead, isTrue);
      expect(controller.unreadCount, 6);
      expect(controller.domains.first.unread, 3);
    });

    test('tout marquer comme lu remet les compteurs à zéro', () async {
      final controller = NotificationsController(repository: _FakeRepository());
      await controller.load();

      await controller.markAllRead();

      expect(controller.unreadCount, 0);
      expect(controller.domains.every((d) => d.unread == 0), isTrue);
      expect(controller.notifications.every((n) => n.isRead), isTrue);
    });
  });

  group('NotificationPreferencesController', () {
    NotificationPreferences initial() => NotificationPreferences(
          promotionalPush: false,
          domains: [
            _preference('missions'),
            _preference('securite', pushEditable: false, essential: true),
          ],
        );

    test('couper une rubrique n\'envoie que ce réglage', () async {
      final repo = _FakeRepository(preferences: initial());
      final controller = NotificationPreferencesController(repository: repo);
      await controller.load();

      await controller.setDomainPush('missions', false);

      expect(repo.savedDomains.single, {
        'missions': {'push': false},
      });
      expect(repo.savedPromotional.single, isNull);
      expect(controller.domains.first.push, isFalse);
      expect(controller.errorMsg.value, isNull);
    });

    test('un enregistrement en échec remet le réglage précédent', () async {
      final repo = _FakeRepository(preferences: initial());
      final controller = NotificationPreferencesController(repository: repo);
      await controller.load();

      repo.fail = true;
      await controller.setDomainPush('missions', false);
      await controller.setPromotionalPush(true);

      expect(controller.domains.first.push, isTrue);
      expect(controller.promotionalPush.value, isFalse);
      expect(controller.errorMsg.value, isNotNull);
    });

    test('l\'accord aux offres est enregistré sur le serveur', () async {
      final repo = _FakeRepository(preferences: initial());
      final controller = NotificationPreferencesController(repository: repo);
      await controller.load();

      await controller.setPromotionalPush(true);

      expect(repo.savedPromotional.single, isTrue);
      expect(controller.promotionalPush.value, isTrue);
    });
  });

  group('NotificationPreferencesScreen', () {
    Future<void> pumpScreen(WidgetTester tester, _FakeRepository repo) async {
      Get.put(NotificationPreferencesController(repository: repo));
      await tester.pumpWidget(
        const GetMaterialApp(home: NotificationPreferencesScreen()),
      );
      await tester.pumpAndSettle();
    }

    testWidgets('une rubrique essentielle est verrouillée, le SMS absent',
        (tester) async {
      await pumpScreen(
        tester,
        _FakeRepository(
          preferences: NotificationPreferences(
            promotionalPush: false,
            domains: [
              _preference('missions'),
              _preference('securite', pushEditable: false, essential: true),
            ],
          ),
        ),
      );

      expect(find.text('Recevoir les offres et nouveautés'), findsOneWidget);
      expect(find.text('Rubrique missions'), findsOneWidget);
      expect(
        find.text('Messages essentiels : toujours envoyés.'),
        findsOneWidget,
      );
      expect(find.text('SMS'), findsNothing);

      Switch switchOf(String key) => tester.widget<Switch>(
            find.descendant(
              of: find.byKey(Key(key)),
              matching: find.byType(Switch),
            ),
          );

      expect(switchOf('push_missions').onChanged, isNotNull);
      expect(switchOf('push_securite').onChanged, isNull);
      expect(switchOf('push_securite').value, isTrue);
    });

    testWidgets('le SMS se règle quand un message courant part par SMS',
        (tester) async {
      await pumpScreen(
        tester,
        _FakeRepository(
          preferences: NotificationPreferences(
            promotionalPush: true,
            domains: [_preference('missions', sms: true, smsEditable: true)],
          ),
        ),
      );

      expect(find.byKey(const Key('sms_missions')), findsOneWidget);
    });

    testWidgets('une panne ne montre aucun interrupteur inventé',
        (tester) async {
      await pumpScreen(tester, _FakeRepository()..fail = true);

      expect(find.byType(Switch), findsNothing);
      expect(find.text('Réessayer'), findsOneWidget);
    });
  });

  group('NotificationService', () {
    test('lit l\'identifiant de la notification portée par un push', () {
      expect(
        NotificationService.notificationIdOf({'notification_id': 42}),
        42,
      );
      expect(
        NotificationService.notificationIdOf({'notification_id': '42'}),
        42,
      );
      // Une campagne groupée ne porte pas d'identifiant.
      expect(
        NotificationService.notificationIdOf({'campaign_id': 3}),
        isNull,
      );
      expect(
        NotificationService.notificationIdOf({'notification_id': null}),
        isNull,
      );
    });
  });
}
