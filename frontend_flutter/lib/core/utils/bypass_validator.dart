/// Détecte les tentatives de contournement de la plateforme (partage de
/// coordonnées de contact, invitation à échanger hors application).
///
/// L'objectif est de bloquer un numéro de téléphone / email / réseau social,
/// **sans** rejeter une description de chantier légitime. Les règles évitent
/// donc les mots trop courants ("quartier", "deux", "devant"…) et se
/// concentrent sur des signaux forts.
class BypassValidator {
  /// Réseaux sociaux et messageries : leur simple mention dans un champ de
  /// description n'a pas de raison d'être légitime.
  static const List<String> _socialKeywords = [
    'whatsapp',
    'whatsap',
    'telegram',
    'facebook',
    'messenger',
    'instagram',
    'snapchat',
    'tiktok',
    'viber',
    'imo',
  ];

  /// Formulations d'invitation explicite à échanger hors plateforme.
  static final List<RegExp> _contactInvitePatterns = [
    RegExp(r'\btel\s*[:.]', caseSensitive: false),
    RegExp(r'\bt[ée]l[ée]phone[rz]?\b', caseSensitive: false),
    RegExp(
      r'\b(appelez|appeler|joignable|contactez[- ]moi|rappelez)\b',
      caseSensitive: false,
    ),
    RegExp(r'\b(mon|le)\s+num[ée]ro\b', caseSensitive: false),
    RegExp(
      r'\b[ée]cris\s?[- ]?moi\b|\b[ée]crivez[- ]moi\b',
      caseSensitive: false,
    ),
    RegExp(
      r'\bhors\s+(de\s+l[’\x27]?\s*)?(appli|application|plateforme)\b',
      caseSensitive: false,
    ),
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
    'dix',
  ];

  static final RegExp _emailRegex =
      RegExp(r'[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}');

  /// Numéro ivoirien : +225 optionnel puis 10 chiffres, séparateurs tolérés.
  static final RegExp _phoneRegex = RegExp(
    r'(\+?\s*225)?(\s*[\.\-/]?\s*\d){10,}',
  );

  /// Valide un champ libre. Retourne un message d'erreur si un contournement
  /// est détecté, sinon `null`.
  static String? validate(String? value) {
    if (value == null || value.trim().isEmpty) return null;

    final text = value.toLowerCase();

    // 1. Réseaux sociaux / messageries.
    for (final keyword in _socialKeywords) {
      if (text.contains(keyword)) {
        return 'Le partage de réseaux sociaux ou de messageries est interdit : '
            "les échanges doivent rester dans l'application.";
      }
    }

    // 2. Invitations explicites à contacter hors plateforme.
    for (final pattern in _contactInvitePatterns) {
      if (pattern.hasMatch(text)) {
        return "L'invitation à échanger vos coordonnées ou à communiquer hors "
            "de l'application est interdite.";
      }
    }

    // 3. Adresses email.
    if (_emailRegex.hasMatch(text)) {
      return "L'insertion d'adresses email est interdite.";
    }

    // 4. Numéros de téléphone (suite d'au moins 10 chiffres, séparateurs
    //    tolérés). Le seuil de 10 évite de bloquer un montant en FCFA
    //    (ex : « 15 000 000 FCFA » = 8 chiffres).
    final digitClusters = RegExp(r'\d[\d\s\.\-/]{8,}\d');
    for (final m in digitClusters.allMatches(text)) {
      final digits = m.group(0)!.replaceAll(RegExp(r'\D'), '');
      if (digits.length >= 10 && digits.length <= 15) {
        return "L'insertion de numéros de téléphone ou de coordonnées "
            'numériques est interdite.';
      }
    }
    if (_phoneRegex.hasMatch(text.replaceAll(' ', ''))) {
      return "L'insertion de numéros de téléphone est interdite.";
    }

    // 5. Numéro épelé en toutes lettres (au moins 5 mots-nombres, pour ne pas
    //    déclencher sur « deux fenêtres et trois portes »).
    final normalizedText = text.replaceAll(RegExp(r'[\s\-\.,_/\(\)]'), '');
    var wordNumbersCount = 0;
    for (final word in _numberWords) {
      wordNumbersCount += RegExp(word).allMatches(normalizedText).length;
    }
    if (wordNumbersCount >= 5) {
      return "L'insertion de numéros de téléphone (même épelés en lettres) "
          'est interdite.';
    }

    return null;
  }
}
