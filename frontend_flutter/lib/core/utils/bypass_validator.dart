class BypassValidator {
  static const List<String> _suspiciousKeywords = [
    'whatsapp',
    'telegram',
    'facebook',
    'instagram',
    'snapchat',
    'tiktok',
    'snap',
    'insta',
    'adresse',
    'numéro',
    'numero',
    'téléphone',
    'telephone',
    'phone',
    'contact',
    'appeler',
    'appelez',
    'écrire',
    'ecrire',
    'joignable',
    'carrefour',
    'devant',
    'côté de',
    'cote de',
    'quartier',
    'situé a',
    'situe a',
    'situé à',
    'situe à',
    'face a',
    'face à',
    'tel:',
    'tél:',
  ];

  static const List<String> _numberWords = [
    'zero',
    'zéro',
    'un',
    'deux',
    'trois',
    'quatre',
    'cinq',
    'six',
    'sept',
    'huit',
    'neuf',
    'dix'
  ];

  /// Valide si le texte contient des tentatives de contournement (contacts, adresses précises, etc.)
  /// Retourne un message d'erreur si c'est le cas, sinon null.
  static String? validate(String? value) {
    if (value == null || value.trim().isEmpty) return null;

    final text = value.toLowerCase();

    // 1. Détecter les mots-clés suspects ou invitations à contacter hors plateforme
    for (final keyword in _suspiciousKeywords) {
      if (text.contains(keyword)) {
        return "L'insertion de coordonnées de contact, d'indications d'adresse précises ou de réseaux sociaux est interdite.";
      }
    }

    // 2. Détecter les numéros de téléphone épelés en toutes lettres (3 mots numériques ou plus)
    final normalizedText = text.replaceAll(RegExp(r'[\s\-\.,_/\(\)]'), '');
    int wordNumbersCount = 0;
    for (final word in _numberWords) {
      if (normalizedText.contains(word)) {
        // Compter les occurrences
        final matches = RegExp(word).allMatches(normalizedText);
        wordNumbersCount += matches.length;
      }
    }
    if (wordNumbersCount >= 3) {
      return "L'insertion de numéros de téléphone (même épelés en lettres ou fragmentés) est interdite.";
    }

    // 3. Détecter les suites numériques (numéros de téléphone de 8 chiffres ou plus, possiblement espacés)
    final onlyDigits = text.replaceAll(RegExp(r'[^0-9]'), '');
    if (onlyDigits.length >= 8) {
      return "L'insertion de numéros de téléphone ou de coordonnées numériques est interdite.";
    }

    // 4. Détecter des adresses email
    final emailRegex = RegExp(r'[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}');
    if (emailRegex.hasMatch(text)) {
      return "L'insertion d'adresses email est interdite.";
    }

    return null;
  }
}
