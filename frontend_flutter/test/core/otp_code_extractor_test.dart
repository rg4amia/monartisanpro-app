import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/core/utils/otp_code_extractor.dart';

void main() {
  group('extractOtpCode', () {
    test('lit le code du texte d\'origine du catalogue', () {
      const sms =
          'Votre code de vérification ProsArtisan est: 4821. Valide 5 minutes. '
          'Ne le communiquez jamais : ProsArtisan ne vous le demandera pas.';

      expect(extractOtpCode(sms), '4821');
    });

    test('lit le code d\'un texte réécrit depuis le backoffice', () {
      expect(extractOtpCode('ProsArtisan : 0073 est votre code.'), '0073');
      expect(extractOtpCode('9054'), '9054');
      expect(extractOtpCode('Code valable 10 minutes : 3310'), '3310');
    });

    test('ne prend jamais une durée ou un numéro pour un code', () {
      expect(extractOtpCode('Valide 5 minutes.'), isNull);
      expect(extractOtpCode('Appelez le +2250707262811.'), isNull);
      expect(extractOtpCode('Référence 123456'), isNull);
    });

    test('ignore une suite plus longue et retient le code qui suit', () {
      expect(extractOtpCode('Dossier 20261003, code 7719'), '7719');
    });

    test('renvoie null sans message', () {
      expect(extractOtpCode(null), isNull);
      expect(extractOtpCode(''), isNull);
    });
  });
}
