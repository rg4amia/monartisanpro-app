import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/data/models/faq_model.dart';

void main() {
  group('FaqModel', () {
    test('parses a full payload', () {
      final faq = FaqModel.fromJson({
        'id': 3,
        'question': 'Comment fonctionne le séquestre ?',
        'reponse': 'Le montant est bloqué puis libéré jalon par jalon.',
        'categorie': 'Paiement',
        'ordre': 2,
      });

      expect(faq.id, 3);
      expect(faq.question, 'Comment fonctionne le séquestre ?');
      expect(faq.categorie, 'Paiement');
      expect(faq.ordre, 2);
    });

    test('falls back defensively on a partial payload', () {
      final faq = FaqModel.fromJson({'id': '7', 'question': 'Question ?'});

      expect(faq.id, 7);
      expect(faq.question, 'Question ?');
      expect(faq.reponse, '');
      expect(faq.categorie, isNull);
      expect(faq.ordre, 0);
    });

    test('does not throw on an empty payload', () {
      final faq = FaqModel.fromJson(const {});

      expect(faq.id, 0);
      expect(faq.question, '');
      expect(faq.reponse, '');
    });
  });
}
