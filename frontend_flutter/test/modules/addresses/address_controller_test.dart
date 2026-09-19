import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/data/models/address_model.dart';
import 'package:frontend_flutter/modules/addresses/controllers/address_controller.dart';

import '../../helpers/fake_http_adapter.dart';
import '../../helpers/getx_snackbar_harness.dart';
import '../../helpers/test_helpers.dart';

/// Le checkout affichait une adresse fictive codée en dur au lieu du carnet
/// d'adresses réel du client connecté. Ces tests couvrent le contrôleur qui
/// remplace cette valeur figée : chargement du carnet, sélection de
/// l'adresse par défaut, et résilience réseau (Règle d'or 29 : jamais de
/// valeur inventée affichée comme réelle).
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
    await TestHelpers.cleanupTestData();
  });

  group('AddressController.loadAddresses', () {
    test('charge le carnet et sélectionne l\'adresse par défaut', () async {
      adapter.on(
        'GET',
        '/addresses',
        const CannedResponse(
          statusCode: 200,
          body: {
            'success': true,
            'data': [
              {
                'id': 1,
                'label': 'Bureau',
                'recipientName': 'Bamba Inza',
                'recipientPhone': '+2250707262811',
                'addressLine': 'Plateau, Rue du Commerce',
                'city': 'Abidjan',
                'isDefault': false,
                'location': null,
              },
              {
                'id': 2,
                'label': 'Domicile',
                'recipientName': 'Bamba Inza',
                'recipientPhone': '+2250707262811',
                'addressLine': 'Cocody Angré 8e Tranche',
                'city': 'Abidjan',
                'isDefault': true,
                'location': {'lat': 5.36, 'lng': -3.98},
              },
            ],
          },
        ),
      );

      final controller = AddressController();
      await controller.loadAddresses();

      expect(controller.addresses.length, 2);
      expect(controller.defaultAddress?.id, 2);
      expect(controller.selectedAddressId.value, 2);
      expect(controller.selectedAddress?.recipientName, 'Bamba Inza');
      expect(controller.isLoading.value, isFalse);
    });

    test('un carnet vide ne sélectionne aucune adresse fictive', () async {
      adapter.on(
        'GET',
        '/addresses',
        const CannedResponse(statusCode: 200, body: {'data': []}),
      );

      final controller = AddressController();
      await controller.loadAddresses();

      expect(controller.addresses, isEmpty);
      expect(controller.selectedAddress, isNull);
      expect(controller.defaultAddress, isNull);
    });

    testWidgets(
      'une panne réseau laisse isLoading à false et alerte sans lever d\'exception',
      (tester) async {
        // Aucune route enregistrée sur l'adaptateur ⇒ connectionError simulée.
        final controller = AddressController();

        await runControllerAction(tester, controller.loadAddresses);

        expect(controller.isLoading.value, isFalse);
        expect(controller.addresses, isEmpty);
        expect(tester.takeException(), isNull);
      },
    );
  });

  group('AddressController.createAddress', () {
    test('une création réussie recharge le carnet et sélectionne la nouvelle adresse',
        () async {
      adapter
        ..on(
          'GET',
          '/addresses',
          const CannedResponse(statusCode: 200, body: {'data': []}),
        )
        ..on(
          'POST',
          '/addresses',
          const CannedResponse(
            statusCode: 201,
            body: {
              'success': true,
              'data': {
                'id': 5,
                'label': 'Domicile',
                'recipientName': 'Bamba Inza',
                'recipientPhone': '+2250707262811',
                'addressLine': 'Cocody Angré 8e Tranche',
                'city': 'Abidjan',
                'isDefault': true,
                'location': null,
              },
            },
          ),
        );

      final controller = AddressController();
      await controller.loadAddresses();

      // La deuxième réponse GET /addresses (après création) doit refléter la
      // nouvelle adresse pour que le rechargement soit cohérent.
      adapter.on(
        'GET',
        '/addresses',
        const CannedResponse(
          statusCode: 200,
          body: {
            'data': [
              {
                'id': 5,
                'label': 'Domicile',
                'recipientName': 'Bamba Inza',
                'recipientPhone': '+2250707262811',
                'addressLine': 'Cocody Angré 8e Tranche',
                'city': 'Abidjan',
                'isDefault': true,
                'location': null,
              },
            ],
          },
        ),
      );

      final success = await controller.createAddress(
        const AddressModel(
          id: 0,
          recipientName: 'Bamba Inza',
          recipientPhone: '+2250707262811',
          addressLine: 'Cocody Angré 8e Tranche',
          city: 'Abidjan',
        ),
      );

      expect(success, isTrue);
      expect(controller.selectedAddressId.value, 5);
      expect(controller.addresses.single.recipientName, 'Bamba Inza');
    });
  });
}
