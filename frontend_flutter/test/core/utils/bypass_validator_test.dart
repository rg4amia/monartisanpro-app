import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/utils/bypass_validator.dart';

void main() {
  group('BypassValidator - doit ACCEPTER les descriptions légitimes', () {
    const valid = [
      'Remplacer deux fenêtres et trois volets en bois dans le séjour.',
      'Réfection de la toiture, budget estimé à 15 000 000 FCFA sur 8 semaines.',
      'Poser du carrelage 60x60 dans la cuisine et la salle de bain.',
      'Peinture de 4 chambres, 2 couches, teinte blanc cassé.',
      'Installer un climatiseur 1,5 CV au premier étage.',
      'Le quartier est calme, intervention en journée de préférence.',
      'Devis pour 12 points lumineux et 6 prises.',
    ];

    for (final text in valid) {
      test('« ${text.substring(0, text.length.clamp(0, 40))}… »', () {
        expect(BypassValidator.validate(text), isNull);
      });
    }

    test('valeur nulle ou vide', () {
      expect(BypassValidator.validate(null), isNull);
      expect(BypassValidator.validate('   '), isNull);
    });
  });

  group('BypassValidator - doit REJETER les contournements', () {
    const invalid = [
      'Appelez-moi au 0700000001 pour discuter.',
      'Mon numéro : +225 07 00 00 00 01',
      'Contactez-moi sur WhatsApp',
      'On peut voir ça sur Telegram directement',
      'Écris-moi à artisan@example.com',
      'Tel: 0102030405',
      'zero sept zero zero zero zero zero zero zero un',
      'Réglons ça hors application, ce sera plus simple.',
    ];

    for (final text in invalid) {
      test('« $text »', () {
        expect(BypassValidator.validate(text), isNotNull);
      });
    }
  });
}
