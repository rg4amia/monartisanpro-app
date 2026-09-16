import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

/// Tout appel GPS doit être borné dans le temps.
///
/// `Geolocator.getCurrentPosition` sans `timeLimit` ne rend jamais la main
/// lorsque le terminal ne parvient pas à fixer un point — cas courant en
/// intérieur. L'appelant reste alors sur son indicateur de chargement, son
/// `catch` de repli ne s'exécute pas et son `finally` non plus : l'écran
/// « charge indéfiniment » sans jamais afficher d'erreur.
///
/// C'est précisément ce qui immobilisait la carte des artisans de l'espace
/// client : la caméra n'était jamais positionnée. Le défaut touchait aussi la
/// vérification GPS du J-Code et la validation physique du Référent, deux
/// chemins régis par une Règle d'Or.
///
/// Ce test relit les sources plutôt qu'un comportement : la borne est une
/// convention transverse, et c'est son oubli ponctuel — quatre sites sur onze —
/// qui a produit la panne.
void main() {
  test('chaque getCurrentPosition precise un timeLimit', () {
    final offenders = <String>[];

    final sources = Directory('lib')
        .listSync(recursive: true)
        .whereType<File>()
        .where((f) => f.path.endsWith('.dart'));

    for (final file in sources) {
      final source = file.readAsStringSync();

      // Ancré sur `Geolocator.` : un habillage local nommé
      // `_getCurrentPosition` n'est pas l'appel au SDK, et c'est ce dernier
      // qui porte la borne.
      for (final match in RegExp(r'Geolocator\.getCurrentPosition\s*\(')
          .allMatches(source)) {
        final call = _balancedCall(source, match.end - 1);

        if (!call.contains('timeLimit')) {
          final line = '\n'.allMatches(source.substring(0, match.start)).length;
          offenders.add('${file.path.replaceAll(r'\', '/')}:${line + 1}');
        }
      }
    }

    expect(
      offenders,
      isEmpty,
      reason: 'Appels GPS sans borne de temps — ils peuvent rester en attente '
          'indéfiniment et figer l\'écran appelant :\n  ${offenders.join('\n  ')}',
    );
  });
}

/// Renvoie le texte de l'appel ouvert par la parenthèse en [openIndex],
/// jusqu'à sa parenthèse fermante correspondante.
String _balancedCall(String source, int openIndex) {
  var depth = 0;

  for (var i = openIndex; i < source.length; i++) {
    final char = source[i];

    if (char == '(') depth++;
    if (char == ')') {
      depth--;
      if (depth == 0) return source.substring(openIndex, i + 1);
    }
  }

  return source.substring(openIndex);
}
