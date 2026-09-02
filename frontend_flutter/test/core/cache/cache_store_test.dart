import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/cache/cache_store.dart';

import '../../helpers/test_helpers.dart';

class _Item {
  _Item(this.id, this.label);
  final int id;
  final String label;

  Map<String, dynamic> toJson() => {'id': id, 'label': label};
  static _Item fromJson(Map<String, dynamic> j) =>
      _Item(j['id'] as int, j['label'] as String);
}

void main() {
  setUpAll(() async {
    await TestHelpers.initializeTestEnvironment();
  });

  CacheStore<_Item> newStore([String name = 'test_cache']) => CacheStore<_Item>(
        boxName: name,
        fromJson: _Item.fromJson,
        toJson: (i) => i.toJson(),
      );

  test('round-trip d\'un objet', () async {
    final store = newStore('rt_one');
    await store.init();

    await store.writeOne('k', _Item(1, 'a'));
    final got = store.peekOne('k', ignoreExpiration: true);

    expect(got, isNotNull);
    expect(got!.id, 1);
    expect(got.label, 'a');
  });

  test('round-trip d\'une liste', () async {
    final store = newStore('rt_list');
    await store.init();

    await store.writeList('k', [_Item(1, 'a'), _Item(2, 'b')]);
    final got = store.peekList('k', ignoreExpiration: true);

    expect(got, hasLength(2));
    expect(got!.map((e) => e.label), ['a', 'b']);
  });

  test('expiration : peek renvoie null après le TTL', () async {
    final store = newStore('exp');
    await store.init();

    await store.writeOne('k', _Item(1, 'a'));

    expect(store.isFresh('k', const Duration(minutes: 5)), isTrue);
    expect(store.isFresh('k', Duration.zero), isFalse);
    expect(store.peekOne('k', ttl: Duration.zero), isNull);
    expect(store.peekOne('k', ignoreExpiration: true), isNotNull);
  });

  test('cacheFirst : sert le cache frais sans appeler fetch', () async {
    final store = newStore('cf');
    await store.init();
    await store.writeOne('k', _Item(9, 'cached'));

    var fetched = false;
    final res = await store.readOne(
      key: 'k',
      ttl: const Duration(minutes: 5),
      fetch: () async {
        fetched = true;
        return _Item(0, 'network');
      },
    );

    expect(fetched, isFalse);
    expect(res.label, 'cached');
  });

  test('fallback : réseau KO + cache périmé → renvoie le cache périmé',
      () async {
    final store = newStore('fb');
    await store.init();
    await store.writeOne('k', _Item(7, 'stale'));

    final res = await store.readOne(
      key: 'k',
      ttl: Duration.zero, // cache considéré périmé
      fetch: () async => throw Exception('offline'),
    );

    expect(res.label, 'stale');
  });

  test('fallback : réseau KO + pas de cache → propage l\'erreur', () async {
    final store = newStore('fb2');
    await store.init();

    await expectLater(
      store.readList(
        key: 'absent',
        fetch: () async => throw Exception('offline'),
      ),
      throwsA(isA<Exception>()),
    );
  });

  test('invalidate supprime l\'entrée', () async {
    final store = newStore('inv');
    await store.init();
    await store.writeOne('k', _Item(1, 'a'));

    await store.invalidate('k');
    expect(store.peekOne('k', ignoreExpiration: true), isNull);
  });
}
