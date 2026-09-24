/// Lecteurs JSON défensifs (Règle d'or 28).
///
/// Aucune valeur issue de l'API n'est transtypée directement (`as String`,
/// `as int`) : un champ absent, `null`, ou arrivant sous une autre forme
/// (entier en chaîne, moyenne ronde `3` au lieu de `3.0`, booléen `1`/`0`)
/// lèverait une exception et emporterait la lecture de tout l'objet.
/// Chaque lecteur tolère ces variantes et renvoie `null` (ou la valeur de
/// repli) plutôt que d'échouer.
library;

/// Chaîne non vide, ou `null`. Les nombres et booléens sont convertis.
String? readString(dynamic value) {
  if (value == null) return null;
  if (value is String) return value;
  if (value is num || value is bool) return value.toString();
  return null;
}

/// Entier, accepte `int`, `double` (tronqué) et chaîne numérique.
int? readInt(dynamic value) {
  if (value == null) return null;
  if (value is int) return value;
  if (value is num) return value.toInt();
  if (value is String) {
    final trimmed = value.trim();
    return int.tryParse(trimmed) ?? double.tryParse(trimmed)?.toInt();
  }
  return null;
}

/// Décimal, accepte `int`, `double` et chaîne numérique.
double? readDouble(dynamic value) {
  if (value == null) return null;
  if (value is num) return value.toDouble();
  if (value is String) return double.tryParse(value.trim());
  return null;
}

/// Booléen, accepte `bool`, `1`/`0` et `'true'`/`'1'`/`'oui'`.
bool? readBool(dynamic value) {
  if (value == null) return null;
  if (value is bool) return value;
  if (value is num) return value != 0;
  if (value is String) {
    final normalized = value.trim().toLowerCase();
    if (const ['1', 'true', 'oui', 'yes'].contains(normalized)) return true;
    if (const ['0', 'false', 'non', 'no', ''].contains(normalized)) {
      return false;
    }
  }
  return null;
}

/// Objet JSON, ou `null` si la valeur n'en est pas un (y compris `[]`,
/// forme que prend un tableau associatif PHP vide).
Map<String, dynamic>? readMap(dynamic value) {
  if (value is Map<String, dynamic>) return value;
  if (value is Map) {
    return value.map((key, v) => MapEntry(key.toString(), v));
  }
  return null;
}

/// Liste JSON, ou `null` si la valeur n'en est pas une.
List<dynamic>? readList(dynamic value) => value is List ? value : null;
