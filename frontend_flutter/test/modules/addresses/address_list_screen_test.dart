import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/data/models/address_model.dart';
import 'package:frontend_flutter/modules/addresses/controllers/address_controller.dart';
import 'package:frontend_flutter/modules/addresses/views/address_list_screen.dart';
import 'package:get/get.dart';

/// `AddressListScreen` affiche l'état du `AddressController` : pas d'appel
/// réseau ici, seulement l'état déjà chargé — ce double neutralise
/// `loadAddresses()` (déclenché par `onInit`) pour piloter directement l'état
/// affiché depuis chaque test.
class _NoNetworkAddressController extends AddressController {
  @override
  Future<void> loadAddresses() async {}

  @override
  Future<bool> deleteAddress(int id) async {
    deletedId = id;
    addresses.removeWhere((a) => a.id == id);
    return true;
  }

  @override
  Future<bool> setDefaultAddress(int id) async {
    setDefaultId = id;
    return true;
  }

  int? deletedId;
  int? setDefaultId;
}

AddressModel _address({
  required int id,
  String? label = 'Domicile',
  bool isDefault = false,
}) {
  return AddressModel(
    id: id,
    label: label,
    recipientName: 'Bamba Inza',
    recipientPhone: '+2250707262811',
    addressLine: 'Cocody Angré 8e Tranche',
    city: 'Abidjan',
    isDefault: isDefault,
  );
}

void main() {
  setUp(() => Get.testMode = true);
  tearDown(Get.reset);

  testWidgets(
    'affiche un état vide avec une invitation à ajouter une adresse',
    (tester) async {
      final controller = Get.put<AddressController>(_NoNetworkAddressController());
      controller.isLoading.value = false;

      await tester.pumpWidget(const GetMaterialApp(home: AddressListScreen()));
      await tester.pump();

      expect(find.text('Aucune adresse enregistrée'), findsOneWidget);
      expect(find.widgetWithText(ElevatedButton, 'Ajouter une adresse'), findsOneWidget);
    },
  );

  testWidgets(
    'affiche le carnet et distingue visuellement l\'adresse par défaut',
    (tester) async {
      final controller = Get.put<AddressController>(_NoNetworkAddressController());
      controller.isLoading.value = false;
      controller.addresses.assignAll([
        _address(id: 1, label: 'Domicile', isDefault: true),
        _address(id: 2, label: 'Bureau'),
      ]);
      controller.selectedAddressId.value = 1;

      await tester.pumpWidget(const GetMaterialApp(home: AddressListScreen()));
      await tester.pump();

      expect(find.text('Domicile'), findsOneWidget);
      expect(find.text('Bureau'), findsOneWidget);
      expect(find.text('Par défaut'), findsOneWidget);
    },
  );

  testWidgets(
    'une suppression passe obligatoirement par une confirmation explicite',
    (tester) async {
      final controller =
          Get.put<AddressController>(_NoNetworkAddressController())
              as _NoNetworkAddressController;
      controller.isLoading.value = false;
      controller.addresses.assignAll([_address(id: 1, isDefault: true)]);
      controller.selectedAddressId.value = 1;

      await tester.pumpWidget(const GetMaterialApp(home: AddressListScreen()));
      await tester.pump();

      await tester.tap(find.byIcon(Icons.more_vert));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Supprimer'));
      await tester.pumpAndSettle();

      expect(find.byType(AlertDialog), findsOneWidget);
      expect(
        find.textContaining('sera définitivement supprimée'),
        findsOneWidget,
      );

      // Annuler ne doit déclencher aucune suppression côté contrôleur.
      await tester.tap(find.text('Annuler'));
      await tester.pumpAndSettle();

      expect(controller.deletedId, isNull);
      expect(controller.addresses.length, 1);
    },
  );

  testWidgets(
    'confirmer la suppression appelle bien le contrôleur avec la bonne adresse',
    (tester) async {
      final controller =
          Get.put<AddressController>(_NoNetworkAddressController())
              as _NoNetworkAddressController;
      controller.isLoading.value = false;
      controller.addresses.assignAll([_address(id: 7, isDefault: true)]);
      controller.selectedAddressId.value = 7;

      await tester.pumpWidget(const GetMaterialApp(home: AddressListScreen()));
      await tester.pump();

      await tester.tap(find.byIcon(Icons.more_vert));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Supprimer'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Supprimer').last);
      await tester.pumpAndSettle();

      expect(controller.deletedId, 7);
    },
  );

  testWidgets(
    'ne propose pas "Définir par défaut" pour l\'adresse déjà par défaut',
    (tester) async {
      final controller = Get.put<AddressController>(_NoNetworkAddressController());
      controller.isLoading.value = false;
      controller.addresses.assignAll([_address(id: 1, isDefault: true)]);
      controller.selectedAddressId.value = 1;

      await tester.pumpWidget(const GetMaterialApp(home: AddressListScreen()));
      await tester.pump();

      await tester.tap(find.byIcon(Icons.more_vert));
      await tester.pumpAndSettle();

      expect(find.text('Définir par défaut'), findsNothing);
    },
  );

  testWidgets(
    '"Définir par défaut" appelle le contrôleur pour une adresse secondaire',
    (tester) async {
      final controller =
          Get.put<AddressController>(_NoNetworkAddressController())
              as _NoNetworkAddressController;
      controller.isLoading.value = false;
      controller.addresses.assignAll([
        _address(id: 1, isDefault: true),
        _address(id: 2, label: 'Bureau'),
      ]);
      controller.selectedAddressId.value = 1;

      await tester.pumpWidget(const GetMaterialApp(home: AddressListScreen()));
      await tester.pump();

      await tester.tap(find.byIcon(Icons.more_vert).last);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Définir par défaut'));
      await tester.pumpAndSettle();

      expect(controller.setDefaultId, 2);
    },
  );
}
