import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/data/models/jcode_model.dart';
import 'package:frontend_flutter/data/models/jcode_redemption_model.dart';

void main() {
  group('JcodeModel - Multi-Supplier & Redemptions (Chantier 7)', () {
    test('deserializes multi-supplier J-Code with null fournisseur_id', () {
      final json = {
        'id': 42,
        'mission_id': 10,
        'artisan_id': 5,
        'fournisseur_id': null,
        'code': 'PA-77AA',
        'montant': 100000,
        'montant_consomme': 35000,
        'statut': 'partiellement_utilise',
        'expires_at': '2026-10-01T12:00:00Z',
        'is_multi_supplier': true,
        'redemptions': [
          {
            'id': 1,
            'jcode_id': 42,
            'fournisseur_id': 101,
            'montant': 20000,
            'scanned_at': '2026-09-26T09:00:00Z',
            'latitude': 5.305,
            'longitude': -4.005,
            'items': [
              {
                'item_id': 1,
                'name': 'Ciment CPJ',
                'quantity': 4,
                'unit_price': 5000,
                'subtotal': 20000,
              }
            ],
            'fournisseur': {
              'id': 101,
              'name': 'Moussa Fofana',
              'phone': '+2250701020304',
              'shop_name': 'Quincaillerie Treichville',
            },
          },
          {
            'id': 2,
            'jcode_id': 42,
            'fournisseur_id': 102,
            'montant': 15000,
            'scanned_at': '2026-09-26T10:30:00Z',
            'latitude': 5.290,
            'longitude': -3.950,
            'items': [
              {
                'item_id': 2,
                'name': 'Peinture Blanche 20L',
                'quantity': 1,
                'unit_price': 15000,
                'subtotal': 15000,
              }
            ],
            'fournisseur': {
              'id': 102,
              'name': 'Amadou Diallo',
              'phone': '+2250702030405',
              'shop_name': 'Quincaillerie Koumassi',
            },
          }
        ],
      };

      final jcode = JcodeModel.fromJson(json);

      expect(jcode.id, equals(42));
      expect(jcode.fournisseurId, isNull);
      expect(jcode.isMultiSupplier, isTrue);
      expect(jcode.isPartiallyUsed, isTrue);
      expect(jcode.isActive, isTrue);
      expect(jcode.montant, equals(100000));
      expect(jcode.montantConsomme, equals(35000));
      expect(jcode.montantRestant, equals(65000));
      expect(jcode.redemptions.length, equals(2));

      final firstRedemption = jcode.redemptions.first;
      expect(firstRedemption.id, equals(1));
      expect(firstRedemption.fournisseurId, equals(101));
      expect(firstRedemption.montant, equals(20000));
      expect(firstRedemption.latitude, equals(5.305));
      expect(firstRedemption.longitude, equals(-4.005));
      expect(firstRedemption.fournisseur?.shopName, equals('Quincaillerie Treichville'));

      final secondRedemption = jcode.redemptions.last;
      expect(secondRedemption.id, equals(2));
      expect(secondRedemption.fournisseurId, equals(102));
      expect(secondRedemption.montant, equals(15000));
      expect(secondRedemption.fournisseur?.shopName, equals('Quincaillerie Koumassi'));
    });

    test('serializes and deserializes JcodeRedemptionModel properly', () {
      final redemption = JcodeRedemptionModel(
        id: 7,
        jcodeId: 42,
        fournisseurId: 101,
        montant: 25000,
        recuPhotoUrl: 'https://example.com/receipt.jpg',
        latitude: 5.340,
        longitude: -4.010,
        items: const [
          {'name': 'Fers à béton', 'quantity': 5, 'subtotal': 25000}
        ],
        scannedAt: '2026-09-26T11:00:00Z',
      );

      final json = redemption.toJson();
      final reconstructed = JcodeRedemptionModel.fromJson(json);

      expect(reconstructed.id, equals(7));
      expect(reconstructed.jcodeId, equals(42));
      expect(reconstructed.fournisseurId, equals(101));
      expect(reconstructed.montant, equals(25000));
      expect(reconstructed.recuPhotoUrl, equals('https://example.com/receipt.jpg'));
      expect(reconstructed.latitude, equals(5.340));
      expect(reconstructed.longitude, equals(-4.010));
      expect(reconstructed.items.length, equals(1));
    });
  });
}
