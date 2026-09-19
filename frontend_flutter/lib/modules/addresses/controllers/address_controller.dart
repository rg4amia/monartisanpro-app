import 'package:get/get.dart';

import '../../../data/models/address_model.dart';
import '../../../data/repositories/address_repository.dart';

class AddressController extends GetxController {
  final AddressRepository _repo = AddressRepository();

  final isLoading = true.obs;
  final addresses = <AddressModel>[].obs;

  /// Adresse retenue pour la commande en cours. Initialisée sur l'adresse
  /// par défaut du carnet dès le chargement, modifiable via [selectAddress].
  final selectedAddressId = Rxn<int>();

  AddressModel? get selectedAddress => addresses.firstWhereOrNull(
        (a) => a.id == selectedAddressId.value,
      );

  AddressModel? get defaultAddress =>
      addresses.firstWhereOrNull((a) => a.isDefault);

  @override
  void onInit() {
    super.onInit();
    loadAddresses();
  }

  Future<void> loadAddresses() async {
    isLoading.value = true;
    try {
      final list = await _repo.list();
      addresses.value = list;
      if (selectedAddressId.value == null ||
          !list.any((a) => a.id == selectedAddressId.value)) {
        selectedAddressId.value = defaultAddress?.id ?? list.firstOrNull?.id;
      }
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de charger votre carnet d\'adresses',
        snackPosition: SnackPosition.BOTTOM,
      );
    } finally {
      isLoading.value = false;
    }
  }

  void selectAddress(int addressId) {
    selectedAddressId.value = addressId;
  }

  Future<bool> createAddress(AddressModel address) async {
    try {
      final created = await _repo.create(address);
      await loadAddresses();
      selectedAddressId.value = created.id;
      return true;
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible d\'enregistrer cette adresse',
        snackPosition: SnackPosition.BOTTOM,
      );
      return false;
    }
  }

  Future<bool> updateAddress(int id, AddressModel address) async {
    try {
      await _repo.update(id, address);
      await loadAddresses();
      return true;
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de modifier cette adresse',
        snackPosition: SnackPosition.BOTTOM,
      );
      return false;
    }
  }

  Future<bool> deleteAddress(int id) async {
    try {
      await _repo.delete(id);
      await loadAddresses();
      return true;
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de supprimer cette adresse',
        snackPosition: SnackPosition.BOTTOM,
      );
      return false;
    }
  }

  Future<bool> setDefaultAddress(int id) async {
    try {
      await _repo.setDefault(id);
      await loadAddresses();
      return true;
    } catch (e) {
      Get.snackbar(
        'Erreur',
        'Impossible de définir cette adresse par défaut',
        snackPosition: SnackPosition.BOTTOM,
      );
      return false;
    }
  }
}
