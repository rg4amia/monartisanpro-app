import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:get/get.dart';

import '../../../app/routes/app_routes.dart';
import '../../../core/theme/app_colors.dart';
import '../../../data/models/address_model.dart';
import '../controllers/address_controller.dart';

/// Ajout ou modification d'une adresse du carnet de livraison.
///
/// La géolocalisation (bouton « Utiliser ma position actuelle ») réutilise
/// l'écran carte/recherche déjà éprouvé pour les missions
/// (`Routes.locationPicker`, `LocationPickerController`), dont l'appel GPS
/// est borné (`timeLimit`) conformément à la Règle d'or 26 — jamais un appel
/// direct à Geolocator ici.
class AddressFormScreen extends StatefulWidget {
  const AddressFormScreen({super.key});

  @override
  State<AddressFormScreen> createState() => _AddressFormScreenState();
}

class _AddressFormScreenState extends State<AddressFormScreen> {
  final _formKey = GlobalKey<FormState>();
  final _labelCtrl = TextEditingController();
  final _nameCtrl = TextEditingController();
  final _phoneCtrl = TextEditingController();
  final _addressLineCtrl = TextEditingController();
  final _cityCtrl = TextEditingController(text: 'Abidjan');
  final _regionCtrl = TextEditingController();

  double? _lat;
  double? _lng;
  bool _isSaving = false;
  bool _isLocating = false;

  AddressModel? get _editing =>
      Get.arguments is AddressModel ? Get.arguments as AddressModel : null;

  @override
  void initState() {
    super.initState();
    final editing = _editing;
    if (editing != null) {
      _labelCtrl.text = editing.label ?? '';
      _nameCtrl.text = editing.recipientName;
      _phoneCtrl.text = editing.recipientPhone.replaceFirst('+225', '');
      _addressLineCtrl.text = editing.addressLine;
      _cityCtrl.text = editing.city;
      _regionCtrl.text = editing.region ?? '';
      _lat = editing.lat;
      _lng = editing.lng;
    }
  }

  @override
  void dispose() {
    _labelCtrl.dispose();
    _nameCtrl.dispose();
    _phoneCtrl.dispose();
    _addressLineCtrl.dispose();
    _cityCtrl.dispose();
    _regionCtrl.dispose();
    super.dispose();
  }

  Future<void> _useCurrentLocation() async {
    setState(() => _isLocating = true);
    try {
      final result = await Get.toNamed(Routes.locationPicker);
      if (result is Map) {
        final lat = result['latitude'];
        final lng = result['longitude'];
        final address = result['address'];
        setState(() {
          if (lat is num) _lat = lat.toDouble();
          if (lng is num) _lng = lng.toDouble();
          if (address is String && address.trim().isNotEmpty) {
            _addressLineCtrl.text = address.trim();
          }
        });
      }
    } finally {
      if (mounted) setState(() => _isLocating = false);
    }
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() => _isSaving = true);
    final controller = Get.find<AddressController>();
    final address = AddressModel(
      id: _editing?.id ?? 0,
      label: _labelCtrl.text.trim().isEmpty ? null : _labelCtrl.text.trim(),
      recipientName: _nameCtrl.text.trim(),
      recipientPhone: '+225${_phoneCtrl.text.trim()}',
      addressLine: _addressLineCtrl.text.trim(),
      city: _cityCtrl.text.trim(),
      region: _regionCtrl.text.trim().isEmpty ? null : _regionCtrl.text.trim(),
      lat: _lat,
      lng: _lng,
    );

    final success = _editing != null
        ? await controller.updateAddress(_editing!.id, address)
        : await controller.createAddress(address);

    if (mounted) setState(() => _isSaving = false);
    if (success) {
      Get.back(result: true);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: Text(_editing != null ? 'Modifier l\'adresse' : 'Nouvelle adresse'),
        backgroundColor: AppColors.surface,
        foregroundColor: AppColors.textPrimary,
        elevation: 0,
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            OutlinedButton.icon(
              key: const Key('address_form_use_current_location'),
              onPressed: _isLocating ? null : _useCurrentLocation,
              style: OutlinedButton.styleFrom(
                foregroundColor: AppColors.client,
                side: const BorderSide(color: AppColors.client),
                padding: const EdgeInsets.symmetric(vertical: 14),
              ),
              icon: _isLocating
                  ? const SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.my_location),
              label: Text(
                _lat != null
                    ? 'Position sélectionnée sur la carte'
                    : 'Utiliser ma position actuelle',
              ),
            ),
            const SizedBox(height: 20),
            _label('Libellé (optionnel)'),
            TextFormField(
              key: const Key('address_form_label'),
              controller: _labelCtrl,
              decoration: _decoration('Ex : Domicile, Bureau'),
            ),
            const SizedBox(height: 16),
            _label('Nom du destinataire'),
            TextFormField(
              key: const Key('address_form_name'),
              controller: _nameCtrl,
              decoration: _decoration('Nom complet'),
              validator: (v) => (v == null || v.trim().isEmpty)
                  ? 'Le nom du destinataire est requis'
                  : null,
            ),
            const SizedBox(height: 16),
            _label('Téléphone du destinataire'),
            Row(
              children: [
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 16,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.secondary,
                    borderRadius: BorderRadius.circular(8),
                  ),
                  child: const Text(
                    '+225',
                    style: TextStyle(fontWeight: FontWeight.w600),
                  ),
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: TextFormField(
                    key: const Key('address_form_phone'),
                    controller: _phoneCtrl,
                    keyboardType: TextInputType.phone,
                    inputFormatters: [CiLocalPhoneFormatter()],
                    decoration: _decoration('0707262811'),
                    validator: (v) => (v == null || v.trim().length != 10)
                        ? 'Numéro invalide (10 chiffres)'
                        : null,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            _label('Adresse'),
            TextFormField(
              key: const Key('address_form_address_line'),
              controller: _addressLineCtrl,
              maxLines: 2,
              decoration: _decoration('Quartier, rue, repère...'),
              validator: (v) => (v == null || v.trim().isEmpty)
                  ? 'L\'adresse est requise'
                  : null,
            ),
            const SizedBox(height: 16),
            _label('Ville'),
            TextFormField(
              key: const Key('address_form_city'),
              controller: _cityCtrl,
              decoration: _decoration('Ville'),
              validator: (v) =>
                  (v == null || v.trim().isEmpty) ? 'La ville est requise' : null,
            ),
            const SizedBox(height: 16),
            _label('Commune / région (optionnel)'),
            TextFormField(
              key: const Key('address_form_region'),
              controller: _regionCtrl,
              decoration: _decoration('Ex : Cocody, Abidjan-Lagunes'),
            ),
            const SizedBox(height: 28),
            ElevatedButton(
              key: const Key('address_form_submit'),
              onPressed: _isSaving ? null : _save,
              style: ElevatedButton.styleFrom(
                backgroundColor: AppColors.client,
                foregroundColor: Colors.white,
                padding: const EdgeInsets.symmetric(vertical: 16),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(10),
                ),
              ),
              child: _isSaving
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(
                        strokeWidth: 2,
                        color: Colors.white,
                      ),
                    )
                  : const Text('Enregistrer l\'adresse'),
            ),
          ],
        ),
      ),
    );
  }

  Widget _label(String text) => Padding(
        padding: const EdgeInsets.only(bottom: 6),
        child: Text(
          text,
          style: const TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w600,
            color: AppColors.textPrimary,
          ),
        ),
      );

  InputDecoration _decoration(String hint) => InputDecoration(
        hintText: hint,
        filled: true,
        fillColor: AppColors.surface,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(8),
          borderSide: const BorderSide(color: AppColors.border),
        ),
        contentPadding:
            const EdgeInsets.symmetric(horizontal: 12, vertical: 14),
      );
}

/// Normalise la saisie du téléphone local (10 chiffres, ex. `0707262811`)
/// affiché à côté du préfixe fixe `+225`.
///
/// Un numéro copié-collé depuis les contacts arrive souvent déjà préfixé
/// (`+2250707262811` ou `2250707262811`). Un simple filtrage `digitsOnly` +
/// troncature à 10 chiffres accepterait silencieusement les 10 premiers
/// chiffres de cette chaîne (`2250707072`), un numéro plausible mais faux,
/// sans jamais lever d'erreur de validation. Un numéro local ivoirien
/// commence toujours par `0` (jamais par `2`), ce qui permet de détecter et
/// de retirer sans ambiguïté un préfixe `225`/`+225` déjà présent avant de
/// tronquer à 10 chiffres.
class CiLocalPhoneFormatter extends TextInputFormatter {
  @override
  TextEditingValue formatEditUpdate(
    TextEditingValue oldValue,
    TextEditingValue newValue,
  ) {
    var digits = newValue.text.replaceAll(RegExp(r'[^0-9]'), '');
    if (digits.length > 10 && digits.startsWith('225')) {
      digits = digits.substring(3);
    }
    if (digits.length > 10) {
      digits = digits.substring(0, 10);
    }
    return TextEditingValue(
      text: digits,
      selection: TextSelection.collapsed(offset: digits.length),
    );
  }
}
