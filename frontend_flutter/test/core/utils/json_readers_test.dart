import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/utils/json_readers.dart';

void main() {
  group('readInt', () {
    test('accepte entier, décimal et chaîne numérique', () {
      expect(readInt(12000), 12000);
      expect(readInt(12000.0), 12000);
      expect(readInt('12000'), 12000);
      expect(readInt(' 3.0 '), 3);
    });

    test('renvoie null sur absence ou valeur inexploitable', () {
      expect(readInt(null), isNull);
      expect(readInt('abc'), isNull);
      expect(readInt(<String, dynamic>{}), isNull);
    });
  });

  group('readDouble', () {
    test('accepte une moyenne ronde reçue en entier', () {
      expect(readDouble(3), 3.0);
      expect(readDouble('5.36'), 5.36);
      expect(readDouble(null), isNull);
      expect(readDouble('n/a'), isNull);
    });
  });

  group('readString', () {
    test('convertit les scalaires et refuse les structures', () {
      expect(readString('actif'), 'actif');
      expect(readString(42), '42');
      expect(readString(null), isNull);
      expect(readString(<dynamic>[]), isNull);
    });
  });

  group('readBool', () {
    test('accepte booléen, 1/0 et chaînes usuelles', () {
      expect(readBool(true), isTrue);
      expect(readBool(1), isTrue);
      expect(readBool(0), isFalse);
      expect(readBool('oui'), isTrue);
      expect(readBool('false'), isFalse);
      expect(readBool('peut-être'), isNull);
      expect(readBool(null), isNull);
    });
  });

  group('readMap / readList', () {
    test('un tableau associatif PHP vide ([]) ne passe pas pour une Map', () {
      expect(readMap(<dynamic>[]), isNull);
      expect(readMap({'a': 1}), {'a': 1});
      expect(readMap(<dynamic, dynamic>{1: 'x'}), {'1': 'x'});
    });

    test('readList ne renvoie que des listes', () {
      expect(readList([1, 2]), [1, 2]);
      expect(readList(<String, dynamic>{}), isNull);
      expect(readList(null), isNull);
    });
  });

  group('readMapList / readDataList / readApiMessage', () {
    test('readMapList ignore les éléments non objets et normalise les clés',
        () {
      expect(
        readMapList([
          {'id': 1},
          'bruit',
          <dynamic, dynamic>{'id': 2},
          null,
        ]),
        [
          {'id': 1},
          {'id': 2},
        ],
      );
      expect(readMapList(null), isEmpty);
    });

    test("readDataList lit l'enveloppe {data: [...]} comme le tableau nu", () {
      expect(
          readDataList({
            'data': [
              {'id': 1},
            ],
          }),
          [
            {'id': 1},
          ]);
      expect(
          readDataList([
            {'id': 2},
          ]),
          [
            {'id': 2},
          ]);
    });

    test('readDataList signale une réponse sans liste au lieu de la vider', () {
      expect(() => readDataList({'data': null}), throwsFormatException);
      expect(() => readDataList('<html>'), throwsFormatException);
      expect(() => readDataList(null), throwsFormatException);
    });

    test("readApiMessage n'extrait qu'un message non vide", () {
      expect(readApiMessage({'message': ' KYC non validé '}), 'KYC non validé');
      expect(readApiMessage({'message': null}), isNull);
      expect(readApiMessage({'message': ''}), isNull);
      expect(readApiMessage('Erreur 500'), isNull);
    });
  });
}
