import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/shared/widgets/code_verification_card.dart';

/// Le code de validation est la preuve qu'une remise a physiquement eu lieu.
/// Ces tests portent sur ce qui, en cassant silencieusement, retirerait cette
/// garantie : un code affiché sans qu'on l'ait demandé, un code récupéré trop
/// tôt (donc susceptible d'être mis en cache), ou un code qui ne se remasque
/// jamais.
Future<void> _pumpCard(
  WidgetTester tester, {
  required Future<String?> Function() onReveal,
  Duration revealDuration = const Duration(seconds: 30),
}) async {
  await tester.pumpWidget(
    MaterialApp(
      home: Scaffold(
        body: CodeVerificationCard(
          title: 'Code de retrait',
          instruction: 'Communiquez ce code APRÈS avoir remis la marchandise.',
          orderLabel: 'Commande #42',
          onReveal: onReveal,
          revealDuration: revealDuration,
        ),
      ),
    ),
  );
}

void main() {
  testWidgets('le code est masque tant qu on ne le demande pas', (tester) async {
    var called = false;

    await _pumpCard(
      tester,
      onReveal: () async {
        called = true;
        return 'LIVREUR-4821';
      },
    );

    expect(find.text('● ● ● ●'), findsOneWidget);
    expect(find.text('4821'), findsNothing);
    expect(find.text('LIVREUR-4821'), findsNothing);

    // Le point central : rien n'est récupéré tant que l'utilisateur n'a pas
    // agi. Le code ne transite donc pas dans les listes mises en cache.
    expect(called, isFalse);
  });

  testWidgets('la consigne d usage est toujours visible', (tester) async {
    await _pumpCard(tester, onReveal: () async => 'LIVREUR-4821');

    // L'ordre des gestes est le modèle de sécurité : il doit rester à l'écran,
    // masqué ou non.
    expect(
      find.textContaining('APRÈS avoir remis la marchandise'),
      findsOneWidget,
    );
  });

  testWidgets('la revelation affiche les chiffres et le libelle complet',
      (tester) async {
    await _pumpCard(tester, onReveal: () async => 'LIVREUR-4821');

    await tester.tap(find.text('Révéler'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));

    // Les chiffres en grand, à lire à voix haute…
    expect(find.text('4821'), findsOneWidget);
    // …et le libellé complet pour lever l'ambiguïté.
    expect(find.text('LIVREUR-4821'), findsOneWidget);
    expect(find.text('● ● ● ●'), findsNothing);
  });

  testWidgets('le code se remasque tout seul apres le delai', (tester) async {
    await _pumpCard(
      tester,
      onReveal: () async => 'RECEPTION-7390',
      revealDuration: const Duration(seconds: 3),
    );

    await tester.tap(find.text('Révéler'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));
    expect(find.text('7390'), findsOneWidget);

    // Un écran posé sur un comptoir ne doit pas garder le code affiché.
    await tester.pump(const Duration(seconds: 4));
    expect(find.text('7390'), findsNothing);
    expect(find.text('● ● ● ●'), findsOneWidget);
  });

  testWidgets('le bouton Masquer cache le code immediatement', (tester) async {
    await _pumpCard(tester, onReveal: () async => 'LIVREUR-4821');

    await tester.tap(find.text('Révéler'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));
    expect(find.text('4821'), findsOneWidget);

    await tester.tap(find.text('Masquer'));
    await tester.pump();

    expect(find.text('4821'), findsNothing);
    expect(find.text('● ● ● ●'), findsOneWidget);
  });

  testWidgets('un code indisponible affiche une erreur sans rien reveler',
      (tester) async {
    // Cas du livreur : le backend ne lui renvoie aucun code.
    await _pumpCard(tester, onReveal: () async => null);

    await tester.tap(find.text('Révéler'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));

    expect(find.textContaining('Code indisponible'), findsOneWidget);
    expect(find.text('● ● ● ●'), findsOneWidget);
  });

  testWidgets('une panne reseau est signalee sans faire tomber la carte',
      (tester) async {
    await _pumpCard(tester, onReveal: () async => throw Exception('offline'));

    await tester.tap(find.text('Révéler'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));

    expect(find.textContaining('Vérifiez votre connexion'), findsOneWidget);
    expect(find.text('● ● ● ●'), findsOneWidget);
  });

  testWidgets('un code sans chiffre reste affiche tel quel', (tester) async {
    // Les jeux de test et certaines commandes historiques portent des codes
    // non numériques : la carte ne doit pas afficher une zone vide.
    await _pumpCard(tester, onReveal: () async => 'RET-JOURNEY');

    await tester.tap(find.text('Révéler'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));

    expect(find.text('RET-JOURNEY'), findsNWidgets(2));
  });

  testWidgets('le compte a rebours est affiche pendant la revelation',
      (tester) async {
    await _pumpCard(
      tester,
      onReveal: () async => 'LIVREUR-4821',
      revealDuration: const Duration(seconds: 30),
    );

    await tester.tap(find.text('Révéler'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));

    expect(find.textContaining('Masqué dans'), findsOneWidget);

    // On laisse expirer pour ne pas terminer le test avec un timer actif.
    await tester.pump(const Duration(seconds: 31));
  });
}
