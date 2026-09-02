import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:hive/hive.dart';

/// Fournit le chiffreur AES-256 partagé par toutes les box Hive de l'app.
///
/// La clé est générée une seule fois et conservée dans le keystore sécurisé
/// de la plateforme (`flutter_secure_storage`).
class HiveCipherProvider {
  const HiveCipherProvider._();

  static const _keyName = 'hive_encryption_key';
  static HiveAesCipher? _cipher;

  /// Retourne le chiffreur, en le construisant au premier appel.
  static Future<HiveAesCipher> cipher() async {
    if (_cipher != null) return _cipher!;

    const secureStorage = FlutterSecureStorage();
    final existing = await secureStorage.read(key: _keyName);

    List<int> key;
    if (existing == null) {
      key = Hive.generateSecureKey();
      await secureStorage.write(key: _keyName, value: base64UrlEncode(key));
    } else {
      key = base64Url.decode(existing);
    }

    return _cipher = HiveAesCipher(key);
  }
}
