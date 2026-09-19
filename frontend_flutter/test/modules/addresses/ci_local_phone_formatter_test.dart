import 'package:flutter/services.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/modules/addresses/views/address_form_screen.dart';

/// Un numéro destinataire collé depuis les contacts arrive souvent déjà
/// préfixé (`+2250707262811`). Sans normalisation, un simple filtrage
/// "chiffres uniquement + 10 premiers caractères" acceptait silencieusement
/// un numéro tronqué et faux (`2250707072`) plutôt que de rejeter la saisie
/// ou de retirer le préfixe — ce test verrouille la normalisation attendue.
void main() {
  TextEditingValue apply(String input) {
    final formatter = CiLocalPhoneFormatter();
    return formatter.formatEditUpdate(
      TextEditingValue.empty,
      TextEditingValue(text: input, selection: TextSelection.collapsed(offset: input.length)),
    );
  }

  test('conserve un numéro local à 10 chiffres tel quel', () {
    expect(apply('0707262811').text, '0707262811');
  });

  test('retire le préfixe +225 collé avant de tronquer à 10 chiffres', () {
    expect(apply('+2250707262811').text, '0707262811');
  });

  test('retire le préfixe 225 sans + avant de tronquer à 10 chiffres', () {
    expect(apply('2250707262811').text, '0707262811');
  });

  test('retire les espaces et tirets de mise en forme', () {
    expect(apply('+225 07 07 26 28 11').text, '0707262811');
  });

  test('tronque une saisie trop longue sans préfixe 225 détecté', () {
    expect(apply('07072628111234').text, '0707262811');
  });

  test('laisse une saisie partielle inchangée pendant la frappe', () {
    expect(apply('0707').text, '0707');
  });
}
