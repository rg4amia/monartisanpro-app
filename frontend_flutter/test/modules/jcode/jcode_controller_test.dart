import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/cache/cache_store.dart';
import 'package:frontend_flutter/core/network/api_client.dart';
import 'package:frontend_flutter/data/models/supplier_model.dart';
import 'package:frontend_flutter/data/models/supplier_product_model.dart';
import 'package:frontend_flutter/modules/jcode/controllers/jcode_controller.dart';

import '../../helpers/fake_http_adapter.dart';
import '../../helpers/getx_snackbar_harness.dart';
import '../../helpers/test_helpers.dart';

SupplierProductModel _product({
  int id = 1,
  int stockQuantity = 10,
  int unitPrice = 5000,
}) =>
    SupplierProductModel(
      id: id,
      supplierId: 1,
      name: 'Sac de ciment',
      unitPrice: unitPrice,
      stockQuantity: stockQuantity,
      isActive: true,
    );

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
    // Les catalogues fournisseur/devis sont mis en cache (Hive) indépendamment
    // du stockage de session : sans purge, un test suivant retrouverait les
    // données du test précédent au lieu d'appeler le réseau simulé.
    await CacheStore.wipeAll();
  });

  group('JcodeController — composition du panier de matériaux', () {
    test('draftTotal additionne les sous-totaux des articles', () {
      final controller = JcodeController()
        ..addCustomItem(name: 'Sable', quantity: 2, unitPrice: 3000)
        ..addCustomItem(name: 'Gravier', quantity: 1, unitPrice: 7000);

      expect(controller.draftTotal, 13000);
    });

    testWidgets('addCatalogProduct ajoute un nouvel article catalogue', (tester) async {
      final controller = JcodeController();

      await runControllerAction(
        tester,
        () async => controller.addCatalogProduct(_product()),
      );

      expect(controller.draftItems, hasLength(1));
      expect(controller.draftItems.single.isCatalog, isTrue);
      expect(controller.draftItems.single.quantity, 1);
    });

    testWidgets('addCatalogProduct incrémente la quantité si déjà présent', (tester) async {
      final controller = JcodeController();
      final product = _product();

      await runControllerAction(tester, () async {
        controller.addCatalogProduct(product);
        controller.addCatalogProduct(product);
      });

      expect(controller.draftItems, hasLength(1));
      expect(controller.draftItems.single.quantity, 2);
    });

    testWidgets('addCatalogProduct refuse un article en rupture de stock', (tester) async {
      final controller = JcodeController();

      await runControllerAction(
        tester,
        () async => controller.addCatalogProduct(_product(stockQuantity: 0)),
      );

      expect(controller.draftItems, isEmpty);
    });

    testWidgets('updateDraftQuantity retire l\'article à quantité nulle', (tester) async {
      final controller = JcodeController()
        ..addCustomItem(name: 'Sable', quantity: 2, unitPrice: 3000);
      final item = controller.draftItems.single;

      await runControllerAction(
        tester,
        () async => controller.updateDraftQuantity(item, 0),
      );

      expect(controller.draftItems, isEmpty);
    });

    testWidgets('updateDraftQuantity refuse de dépasser le stock catalogue', (tester) async {
      final controller = JcodeController()
        ..supplierProducts.add(_product(stockQuantity: 3))
        ..addCatalogProduct(_product(stockQuantity: 3));
      final item = controller.draftItems.single;

      await runControllerAction(
        tester,
        () async => controller.updateDraftQuantity(item, 5),
      );

      expect(controller.draftItems.single.quantity, 1);
    });

    test('removeDraftItem retire l\'article ciblé', () {
      final controller = JcodeController()
        ..addCustomItem(name: 'Sable', quantity: 2, unitPrice: 3000);
      final item = controller.draftItems.single;

      controller.removeDraftItem(item);

      expect(controller.draftItems, isEmpty);
    });

    test('resetComposer efface fournisseur, catalogue et brouillon', () {
      final controller = JcodeController()
        ..addCustomItem(name: 'Sable', quantity: 2, unitPrice: 3000)
        ..supplierProducts.add(_product());

      controller.resetComposer();

      expect(controller.draftItems, isEmpty);
      expect(controller.supplierProducts, isEmpty);
      expect(controller.selectedSupplier.value, isNull);
    });
  });

  group('JcodeController.loadSuppliers', () {
    testWidgets('charge la liste des fournisseurs agréés', (tester) async {
      adapter.on(
        'GET',
        '/fournisseurs',
        const CannedResponse(
          statusCode: 200,
          body: {
            'data': [
              {
                'id': 1,
                'name': 'Quincaillerie Koffi',
                'phone': '+2250700000001',
                'shop_name': 'Quincaillerie Koffi',
                'active_products_count': 5,
              },
            ],
          },
        ),
      );
      final controller = JcodeController();

      await runControllerAction(tester, () => controller.loadSuppliers());

      expect(controller.suppliers, hasLength(1));
      expect(controller.suppliers.first.shopName, 'Quincaillerie Koffi');
      expect(controller.isSuppliersLoading.value, isFalse);
    });

    testWidgets('signale une erreur réseau sans planter', (tester) async {
      final controller = JcodeController();

      await runControllerAction(tester, () => controller.loadSuppliers());

      expect(controller.suppliers, isEmpty);
      expect(controller.isSuppliersLoading.value, isFalse);
    });
  });

  group('JcodeController.selectSupplier', () {
    testWidgets('vide le panier catalogue quand on désélectionne', (tester) async {
      final controller = JcodeController()..addCatalogProduct(_product());

      await runControllerAction(tester, () => controller.selectSupplier(null));

      expect(controller.draftItems, isEmpty);
      expect(controller.supplierProducts, isEmpty);
    });

    testWidgets('charge les articles du fournisseur sélectionné', (tester) async {
      adapter.on(
        'GET',
        '/fournisseurs/9/articles',
        const CannedResponse(
          statusCode: 200,
          body: {
            'data': [
              {
                'id': 1,
                'supplier_id': 9,
                'name': 'Sac de ciment',
                'unit_price': 5000,
                'stock_quantity': 10,
                'is_active': true,
              },
            ],
          },
        ),
      );
      final controller = JcodeController();

      await runControllerAction(
        tester,
        () => controller.selectSupplier(
          _supplier(id: 9),
        ),
      );

      expect(controller.supplierProducts, hasLength(1));
      expect(controller.isCatalogLoading.value, isFalse);
    });

    testWidgets('vide le catalogue et alerte en cas d\'échec réseau', (tester) async {
      final controller = JcodeController();

      await runControllerAction(
        tester,
        () => controller.selectSupplier(_supplier(id: 10)),
      );

      expect(controller.supplierProducts, isEmpty);
      expect(controller.isCatalogLoading.value, isFalse);
    });
  });

  group('JcodeController.generateJcodeForDraft', () {
    testWidgets('refuse sans fournisseur sélectionné', (tester) async {
      final controller = JcodeController()
        ..addCustomItem(name: 'Sable', quantity: 1, unitPrice: 3000);

      await runControllerAction(tester, () => controller.generateJcodeForDraft(1));

      expect(adapter.requests, isEmpty);
      expect(controller.activeJcode.value, isNull);
    });

    testWidgets('refuse un panier vide', (tester) async {
      final controller = JcodeController()..selectedSupplier.value = _supplier(id: 1);

      await runControllerAction(tester, () => controller.generateJcodeForDraft(1));

      expect(adapter.requests, isEmpty);
    });

    testWidgets('génère le J-Code et réinitialise le composeur en cas de succès', (tester) async {
      adapter.on(
        'POST',
        '/jcodes',
        const CannedResponse(
          statusCode: 200,
          body: {
            'data': {
              'id': 1,
              'mission_id': 1,
              'artisan_id': 2,
              'fournisseur_id': 1,
              'code': 'PA-AB12',
              'montant': 3000,
              'statut': 'actif',
              'expires_at': '2026-01-01T00:00:00Z',
            },
          },
        ),
      );
      final controller = JcodeController()
        ..selectedSupplier.value = _supplier(id: 1)
        ..addCustomItem(name: 'Sable', quantity: 1, unitPrice: 3000);

      await runControllerAction(tester, () => controller.generateJcodeForDraft(1));

      expect(controller.activeJcode.value?.code, 'PA-AB12');
      expect(controller.draftItems, isEmpty);
      expect(controller.isLoading.value, isFalse);
    });

    testWidgets('signale une erreur réseau sans planter', (tester) async {
      final controller = JcodeController()
        ..selectedSupplier.value = _supplier(id: 1)
        ..addCustomItem(name: 'Sable', quantity: 1, unitPrice: 3000);

      await runControllerAction(tester, () => controller.generateJcodeForDraft(1));

      expect(controller.activeJcode.value, isNull);
      expect(controller.isLoading.value, isFalse);
    });
  });

  group('JcodeController.loadActiveJcode', () {
    testWidgets('charge le J-Code actif courant', (tester) async {
      adapter.on(
        'GET',
        '/jcodes/active',
        const CannedResponse(
          statusCode: 200,
          body: {
            'data': {
              'id': 1,
              'mission_id': 1,
              'artisan_id': 2,
              'code': 'PA-CD34',
              'montant': 5000,
              'statut': 'actif',
              'expires_at': '2026-01-01T00:00:00Z',
            },
          },
        ),
      );
      final controller = JcodeController();

      await runControllerAction(tester, controller.loadActiveJcode);

      expect(controller.activeJcode.value?.code, 'PA-CD34');
      expect(controller.isLoading.value, isFalse);
    });

    testWidgets('aucun J-Code actif ⇒ reste à null sans erreur', (tester) async {
      adapter.on(
        'GET',
        '/jcodes/active',
        const CannedResponse(statusCode: 200, body: {'data': null}),
      );
      final controller = JcodeController();

      await runControllerAction(tester, controller.loadActiveJcode);

      expect(controller.activeJcode.value, isNull);
    });

    test('propage l\'erreur réseau (non interceptée par le contrôleur)', () async {
      final controller = JcodeController();

      await expectLater(controller.loadActiveJcode(), throwsA(anything));
      expect(controller.isLoading.value, isFalse);
    });
  });

  group('JcodeController.loadJcodeForScanning', () {
    testWidgets('charge le J-Code visé par son identifiant', (tester) async {
      adapter.on(
        'GET',
        '/jcodes/PA-AB12',
        const CannedResponse(
          statusCode: 200,
          body: {
            'data': {
              'id': 1,
              'mission_id': 1,
              'artisan_id': 2,
              'code': 'PA-AB12',
              'montant': 3000,
              'statut': 'actif',
              'expires_at': '2026-01-01T00:00:00Z',
            },
          },
        ),
      );
      final controller = JcodeController();

      await runControllerAction(
        tester,
        () => controller.loadJcodeForScanning('PA-AB12'),
      );

      expect(controller.scannedJcode.value?.code, 'PA-AB12');
      expect(controller.isFetchingJcode.value, isFalse);
    });

    testWidgets('J-Code introuvable ⇒ alerte sans planter', (tester) async {
      final controller = JcodeController();

      await runControllerAction(
        tester,
        () => controller.loadJcodeForScanning('PA-INTROUVABLE'),
      );

      expect(controller.scannedJcode.value, isNull);
      expect(controller.isFetchingJcode.value, isFalse);
    });
  });

  group('JcodeController.scanJcode — vérification anti-fraude GPS', () {
    testWidgets('scan accepté dans le rayon autorisé', (tester) async {
      adapter.on(
        'POST',
        '/jcodes/PA-AB12/scan',
        const CannedResponse(
          statusCode: 200,
          body: {'distance_metres': 42, 'statut': 'utilise'},
        ),
      );
      final controller = JcodeController();

      await runControllerAction(
        tester,
        () => controller.scanJcode('PA-AB12', 5.34, -4.02),
      );

      expect(controller.scanResult.value?['statut'], 'utilise');
      expect(controller.isScanning.value, isFalse);
      expect(tester.takeException(), isNull);
    });

    testWidgets('scan bloqué à plus de 100m ⇒ alerte sans exception, statut inchangé', (tester) async {
      adapter.on(
        'POST',
        '/jcodes/PA-AB12/scan',
        const CannedResponse(statusCode: 422, body: {'message': 'Distance GPS > 100m : scan refusé.'}),
      );
      final controller = JcodeController();

      await runControllerAction(
        tester,
        () => controller.scanJcode('PA-AB12', 5.9, -4.9),
      );

      expect(controller.scanResult.value, isNull);
      expect(controller.isScanning.value, isFalse);
      expect(tester.takeException(), isNull);
    });
  });

  group('JcodeController.submitPartialServe', () {
    testWidgets('enregistre une livraison partielle en cas de succès', (tester) async {
      adapter.on(
        'POST',
        '/jcodes/PA-AB12/scan',
        const CannedResponse(
          statusCode: 200,
          body: {'statut': 'partiellement_utilise'},
        ),
      );
      final controller = JcodeController();

      await runControllerAction(
        tester,
        () => controller.submitPartialServe(
          identifier: 'PA-AB12',
          lat: 5.34,
          lng: -4.02,
          servedItems: const [
            {'id': 1, 'quantity_served': 1},
          ],
        ),
      );

      expect(controller.scanResult.value?['statut'], 'partiellement_utilise');
      expect(controller.isScanning.value, isFalse);
    });

    testWidgets('relance l\'exception au chemin appelant en cas d\'échec', (tester) async {
      final controller = JcodeController();

      // `tester.runAsync` ne laisse jamais une exception de [action]
      // atteindre son propre appelant : elle est systématiquement rapportée
      // via `FlutterError.reportError` (échec de test à part entière), et
      // son Future se termine sur `null`. Pour vérifier un `rethrow`, on
      // l'intercepte donc soi-même *dans* l'action passée au harnais.
      Object? caught;
      await runControllerAction(tester, () async {
        try {
          await controller.submitPartialServe(
            identifier: 'PA-AB12',
            lat: 5.34,
            lng: -4.02,
            servedItems: const [],
          );
        } catch (e) {
          caught = e;
        }
      });

      expect(caught, isNotNull);
      expect(controller.isScanning.value, isFalse);
    });
  });

  group('JcodeController — catalogue fournisseur', () {
    testWidgets('loadMyCatalogProducts charge les articles du fournisseur connecté', (tester) async {
      adapter.on(
        'GET',
        '/supplier-products',
        const CannedResponse(
          statusCode: 200,
          body: {
            'data': [
              {
                'id': 1,
                'supplier_id': 1,
                'name': 'Sac de ciment',
                'unit_price': 5000,
                'stock_quantity': 10,
                'is_active': true,
              },
            ],
          },
        ),
      );
      final controller = JcodeController();

      await runControllerAction(tester, controller.loadMyCatalogProducts);

      expect(controller.myProducts, hasLength(1));
      expect(controller.isCatalogLoading.value, isFalse);
    });

    testWidgets('saveSupplierProduct crée un nouvel article puis recharge le catalogue', (tester) async {
      adapter
        ..on(
          'POST',
          '/supplier-products',
          const CannedResponse(
            statusCode: 200,
            body: {
              'data': {
                'id': 2,
                'supplier_id': 1,
                'name': 'Fer à béton',
                'unit_price': 8000,
                'stock_quantity': 20,
                'is_active': true,
              },
            },
          ),
        )
        ..on(
          'GET',
          '/supplier-products',
          const CannedResponse(
            statusCode: 200,
            body: {
              'data': [
                {
                  'id': 2,
                  'supplier_id': 1,
                  'name': 'Fer à béton',
                  'unit_price': 8000,
                  'stock_quantity': 20,
                  'is_active': true,
                },
              ],
            },
          ),
        );
      final controller = JcodeController();

      await runControllerAction(
        tester,
        () => controller.saveSupplierProduct(
          name: 'Fer à béton',
          unitPrice: 8000,
          stockQuantity: 20,
        ),
      );

      expect(controller.myProducts, hasLength(1));
      expect(controller.isSavingProduct.value, isFalse);
    });

    testWidgets('saveSupplierProduct relance l\'exception en cas d\'échec réseau', (tester) async {
      final controller = JcodeController();

      Object? caught;
      await runControllerAction(tester, () async {
        try {
          await controller.saveSupplierProduct(
            name: 'Fer à béton',
            unitPrice: 8000,
            stockQuantity: 20,
          );
        } catch (e) {
          caught = e;
        }
      });

      expect(caught, isNotNull);

      expect(controller.isSavingProduct.value, isFalse);
    });

    testWidgets('archiveSupplierProduct retire l\'article puis recharge le catalogue', (tester) async {
      adapter
        ..on('DELETE', '/supplier-products/1', const CannedResponse(statusCode: 200))
        ..on(
          'GET',
          '/supplier-products',
          const CannedResponse(statusCode: 200, body: {'data': []}),
        );
      final controller = JcodeController();

      await runControllerAction(
        tester,
        () => controller.archiveSupplierProduct(_product()),
      );

      expect(controller.myProducts, isEmpty);
      expect(controller.isSavingProduct.value, isFalse);
    });
  });

  group('JcodeController.importMaterialsFromMission', () {
    testWidgets('importe les lignes matériaux du devis accepté', (tester) async {
      adapter.on(
        'GET',
        '/missions/1/devis',
        const CannedResponse(
          statusCode: 200,
          body: {
            'data': [
              {
                'id': 1,
                'mission_id': 1,
                'artisan_id': 2,
                'statut': 'accepte',
                'created_at': '2026-01-01T00:00:00Z',
                'lignes_json': [
                  {'type': 'mat', 'description': 'Ciment', 'montant': 15000},
                  {'type': 'mo', 'description': 'Pose', 'montant': 20000},
                ],
                'jalons_json': [],
              },
            ],
          },
        ),
      );
      final controller = JcodeController();

      await runControllerAction(
        tester,
        () => controller.importMaterialsFromMission(1),
      );

      expect(controller.draftItems, hasLength(1));
      expect(controller.draftItems.single.name, 'Ciment');
      expect(controller.isImportingDevis.value, isFalse);
    });

    testWidgets('devis sans ligne matériaux ⇒ alerte, panier inchangé', (tester) async {
      adapter.on(
        'GET',
        '/missions/2/devis',
        const CannedResponse(
          statusCode: 200,
          body: {
            'data': [
              {
                'id': 2,
                'mission_id': 2,
                'artisan_id': 2,
                'statut': 'accepte',
                'created_at': '2026-01-01T00:00:00Z',
                'lignes_json': [
                  {'type': 'mo', 'description': 'Pose', 'montant': 20000},
                ],
                'jalons_json': [],
              },
            ],
          },
        ),
      );
      final controller = JcodeController();

      await runControllerAction(
        tester,
        () => controller.importMaterialsFromMission(2),
      );

      expect(controller.draftItems, isEmpty);
    });

    testWidgets('aucun devis pour la mission ⇒ alerte sans planter', (tester) async {
      adapter.on(
        'GET',
        '/missions/3/devis',
        const CannedResponse(statusCode: 200, body: {'data': []}),
      );
      final controller = JcodeController();

      await runControllerAction(
        tester,
        () => controller.importMaterialsFromMission(3),
      );

      expect(controller.draftItems, isEmpty);
      expect(controller.isImportingDevis.value, isFalse);
    });
  });
}

SupplierModel _supplier({int id = 1}) => SupplierModel(
      id: id,
      name: 'Quincaillerie Koffi',
      phone: '+2250700000001',
      shopName: 'Quincaillerie Koffi',
      activeProductsCount: 5,
    );
