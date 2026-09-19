import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/app/routes/app_routes.dart';
import 'package:frontend_flutter/data/models/address_model.dart';
import 'package:frontend_flutter/modules/addresses/controllers/address_controller.dart';
import 'package:frontend_flutter/modules/addresses/views/address_form_screen.dart';
import 'package:get/get.dart';

/// Capture les appels de sauvegarde plutôt que d'atteindre le réseau : ce
/// test vérifie le contrat entre l'écran et le contrôleur (quel `AddressModel`
/// est construit à partir des champs saisis), pas la couche HTTP elle-même
/// (déjà couverte par `address_controller_test.dart`).
class _SpyAddressController extends AddressController {
  AddressModel? lastCreated;
  int? lastUpdatedId;
  AddressModel? lastUpdated;
  bool nextResult = true;

  @override
  Future<void> loadAddresses() async {}

  @override
  Future<bool> createAddress(AddressModel address) async {
    lastCreated = address;
    return nextResult;
  }

  @override
  Future<bool> updateAddress(int id, AddressModel address) async {
    lastUpdatedId = id;
    lastUpdated = address;
    return nextResult;
  }
}

/// Remplace `LocationPickerScreen` (carte Yandex + GPS réel) par un écran qui
/// se ferme immédiatement avec une position et une adresse en boîte, pour
/// tester le bouton « Utiliser ma position actuelle » sans dépendance native.
class _FakeLocationPickerScreen extends StatefulWidget {
  const _FakeLocationPickerScreen();

  @override
  State<_FakeLocationPickerScreen> createState() =>
      _FakeLocationPickerScreenState();
}

class _FakeLocationPickerScreenState extends State<_FakeLocationPickerScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      Get.back(
        result: {
          'latitude': 5.36,
          'longitude': -3.98,
          'address': 'Cocody Angré, Abidjan',
        },
      );
    });
  }

  @override
  Widget build(BuildContext context) => const SizedBox.shrink();
}

/// Navigue vers `AddressFormScreen` via `Get.toNamed`, exactement comme le
/// fait `AddressListScreen` en production — un `GetPage.arguments` statique
/// ne peuple pas `Get.arguments` pour la route initiale, seule une véritable
/// navigation imperative le fait.
Future<void> _pumpForm(
  WidgetTester tester, {
  Object? arguments,
}) async {
  await tester.pumpWidget(
    GetMaterialApp(
      initialRoute: '/launcher',
      getPages: [
        GetPage(name: '/launcher', page: () => const SizedBox.shrink()),
        GetPage(
          name: Routes.addressForm,
          page: () => const AddressFormScreen(),
        ),
        GetPage(
          name: Routes.locationPicker,
          page: () => const _FakeLocationPickerScreen(),
        ),
      ],
    ),
  );
  await tester.pump();
  unawaited(Get.toNamed(Routes.addressForm, arguments: arguments));
  await tester.pumpAndSettle();
}

/// Le bouton de sauvegarde est en bas d'un `ListView` plus haut que le
/// viewport par défaut des tests (800×600) : au-delà de la zone de cache du
/// sliver, l'élément existe mais son `RenderBox` n'est pas encore rattaché à
/// une géométrie peinte, ce que les finders par défaut (`skipOffstage: true`)
/// traitent comme absent. `ensureVisible` fait défiler la liste jusqu'à lui
/// avant de le solliciter.
Future<void> _tapSubmit(WidgetTester tester) async {
  final finder = find.byKey(const Key('address_form_submit'), skipOffstage: false);
  await tester.ensureVisible(finder);
  await tester.pumpAndSettle();
  await tester.tap(finder);
  await tester.pump();
  await tester.pump();
}

void main() {
  setUp(() {
    Get.reset();
    Get.testMode = true;
  });
  tearDown(Get.reset);

  testWidgets('le mode création affiche un formulaire vierge', (tester) async {
    Get.put<AddressController>(_SpyAddressController());

    await _pumpForm(tester);
    await tester.pump();

    expect(find.text('Nouvelle adresse'), findsOneWidget);
    expect(find.text('Utiliser ma position actuelle'), findsOneWidget);
    expect(
      tester.widget<TextFormField>(find.byKey(const Key('address_form_name'))).controller!.text,
      isEmpty,
    );
  });

  testWidgets(
    'le mode édition préremplit les champs et retire le préfixe +225 du téléphone',
    (tester) async {
      Get.put<AddressController>(_SpyAddressController());
      final existing = const AddressModel(
        id: 9,
        label: 'Bureau',
        recipientName: 'Bamba Inza',
        recipientPhone: '+2250707262811',
        addressLine: 'Plateau, Rue du Commerce',
        city: 'Abidjan',
        isDefault: false,
      );

      await _pumpForm(tester, arguments: existing);
      await tester.pump();

      expect(find.text('Modifier l\'adresse'), findsOneWidget);
      expect(
        tester.widget<TextFormField>(find.byKey(const Key('address_form_name'))).controller!.text,
        'Bamba Inza',
      );
      expect(
        tester.widget<TextFormField>(find.byKey(const Key('address_form_phone'))).controller!.text,
        '0707262811',
      );
      expect(
        tester.widget<TextFormField>(find.byKey(const Key('address_form_address_line'))).controller!.text,
        'Plateau, Rue du Commerce',
      );
    },
  );

  testWidgets(
    'refuse la soumission tant que les champs obligatoires sont vides',
    (tester) async {
      final spy = Get.put<AddressController>(_SpyAddressController()) as _SpyAddressController;

      await _pumpForm(tester);
      await tester.pump();

      // La ville est prérenseignée à "Abidjan" (repli pratique pour la
      // majorité des utilisateurs) : on la vide pour couvrir son propre
      // message de validation plutôt que de la laisser masquer ce cas.
      await tester.enterText(find.byKey(const Key('address_form_city')), '');

      await _tapSubmit(tester);

      expect(find.text('Le nom du destinataire est requis'), findsOneWidget);
      expect(find.text('L\'adresse est requise'), findsOneWidget);
      expect(find.text('La ville est requise'), findsOneWidget);
      expect(spy.lastCreated, isNull);
    },
  );

  testWidgets(
    'une soumission valide construit le numéro complet et crée l\'adresse',
    (tester) async {
      final spy = Get.put<AddressController>(_SpyAddressController()) as _SpyAddressController;

      await _pumpForm(tester);
      await tester.pump();

      await tester.enterText(find.byKey(const Key('address_form_name')), 'Bamba Inza');
      await tester.enterText(find.byKey(const Key('address_form_phone')), '0707262811');
      await tester.enterText(
        find.byKey(const Key('address_form_address_line')),
        'Cocody Angré 8e Tranche',
      );
      // La ville est prérenseignée à "Abidjan" par défaut, on la laisse telle quelle.

      await _tapSubmit(tester);

      expect(spy.lastCreated, isNotNull);
      expect(spy.lastCreated!.recipientName, 'Bamba Inza');
      expect(spy.lastCreated!.recipientPhone, '+2250707262811');
      expect(spy.lastCreated!.addressLine, 'Cocody Angré 8e Tranche');
      expect(spy.lastCreated!.city, 'Abidjan');
    },
  );

  testWidgets(
    'une saisie de téléphone déjà préfixée +225 ne produit jamais un numéro doublé',
    (tester) async {
      final spy = Get.put<AddressController>(_SpyAddressController()) as _SpyAddressController;

      await _pumpForm(tester);
      await tester.pump();

      await tester.enterText(find.byKey(const Key('address_form_name')), 'Bamba Inza');
      await tester.enterText(find.byKey(const Key('address_form_phone')), '+2250707262811');
      await tester.enterText(
        find.byKey(const Key('address_form_address_line')),
        'Cocody Angré 8e Tranche',
      );

      await _tapSubmit(tester);

      expect(spy.lastCreated!.recipientPhone, '+2250707262811');
    },
  );

  testWidgets(
    'la modification d\'une adresse existante appelle updateAddress avec son id',
    (tester) async {
      final spy = Get.put<AddressController>(_SpyAddressController()) as _SpyAddressController;
      final existing = const AddressModel(
        id: 9,
        recipientName: 'Bamba Inza',
        recipientPhone: '+2250707262811',
        addressLine: 'Plateau, Rue du Commerce',
        city: 'Abidjan',
      );

      await _pumpForm(tester, arguments: existing);
      await tester.pump();

      await _tapSubmit(tester);

      expect(spy.lastUpdatedId, 9);
      expect(spy.lastUpdated, isNotNull);
      expect(spy.lastCreated, isNull);
    },
  );

  testWidgets(
    '"Utiliser ma position actuelle" remplit l\'adresse depuis le sélecteur de carte',
    (tester) async {
      Get.put<AddressController>(_SpyAddressController());

      await _pumpForm(tester);
      await tester.pump();

      await tester.tap(find.byKey(const Key('address_form_use_current_location')));
      await tester.pumpAndSettle();

      expect(find.text('Position sélectionnée sur la carte'), findsOneWidget);
      expect(
        tester.widget<TextFormField>(find.byKey(const Key('address_form_address_line'))).controller!.text,
        'Cocody Angré, Abidjan',
      );
    },
  );

  testWidgets(
    'la position choisie sur la carte est bien transmise à la création',
    (tester) async {
      final spy = Get.put<AddressController>(_SpyAddressController()) as _SpyAddressController;

      await _pumpForm(tester);
      await tester.pump();

      await tester.tap(find.byKey(const Key('address_form_use_current_location')));
      await tester.pumpAndSettle();

      await tester.enterText(find.byKey(const Key('address_form_name')), 'Bamba Inza');
      await tester.enterText(find.byKey(const Key('address_form_phone')), '0707262811');

      await _tapSubmit(tester);

      expect(spy.lastCreated!.lat, 5.36);
      expect(spy.lastCreated!.lng, -3.98);
      expect(spy.lastCreated!.addressLine, 'Cocody Angré, Abidjan');
    },
  );
}
