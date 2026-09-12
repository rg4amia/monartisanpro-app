import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/shared/widgets/image_viewer.dart';
import 'package:get/get.dart';

/// La visionneuse est partagée par le catalogue, la discussion de chantier et
/// les preuves de jalon. Ces tests couvrent ce qui casserait silencieusement :
/// l'ouverture sur une liste vide, l'index hors bornes et le compteur affiché
/// uniquement quand plusieurs images sont présentes.
Future<void> _pumpHost(
  WidgetTester tester, {
  required List<String> urls,
  int initialIndex = 0,
  String? title,
}) async {
  await tester.pumpWidget(
    GetMaterialApp(
      home: Scaffold(
        body: Builder(
          builder: (context) => ElevatedButton(
            onPressed: () => openImageViewer(
              context,
              urls: urls,
              initialIndex: initialIndex,
              title: title,
            ),
            child: const Text('ouvrir'),
          ),
        ),
      ),
    ),
  );

  await tester.tap(find.text('ouvrir'));
  // `pumpAndSettle` ne convient pas ici : le placeholder de CachedNetworkImage
  // est un CircularProgressIndicator qui tourne indéfiniment tant que l'image
  // réseau n'est pas résolue, ce qui n'arrive jamais en test.
  await tester.pump();
  await tester.pump(const Duration(milliseconds: 400));
}

void main() {
  testWidgets('n\'ouvre rien quand aucune image exploitable n\'est fournie',
      (tester) async {
    await _pumpHost(tester, urls: const []);
    expect(find.byType(Dialog), findsNothing);

    // Des URLs vides ou blanches ne doivent pas ouvrir une visionneuse noire.
    await _pumpHost(tester, urls: const ['', '   ']);
    expect(find.byType(Dialog), findsNothing);
  });

  testWidgets('masque le compteur pour une image unique', (tester) async {
    await _pumpHost(tester, urls: const ['https://exemple.test/a.jpg']);

    expect(find.textContaining('/'), findsNothing);
  });

  testWidgets('affiche le compteur et la position pour plusieurs images',
      (tester) async {
    await _pumpHost(
      tester,
      urls: const [
        'https://exemple.test/a.jpg',
        'https://exemple.test/b.jpg',
        'https://exemple.test/c.jpg',
      ],
      initialIndex: 1,
    );

    expect(find.text('2 / 3'), findsOneWidget);
  });

  testWidgets('ramène un index hors bornes dans la plage valide',
      (tester) async {
    await _pumpHost(
      tester,
      urls: const ['https://exemple.test/a.jpg', 'https://exemple.test/b.jpg'],
      initialIndex: 99,
    );

    // Invariant visé côté utilisateur : aucun plantage et une page valide.
    // Il est aujourd'hui garanti à la fois par le clamp d'openImageViewer et
    // par PageView, qui recale de lui-même un index hors bornes — le test
    // reste donc vert si l'un des deux disparaît, mais rouge si les deux
    // sautent.
    expect(tester.takeException(), isNull);
    expect(find.text('2 / 2'), findsOneWidget);
  });

  testWidgets('affiche le titre quand il est fourni', (tester) async {
    await _pumpHost(
      tester,
      urls: const ['https://exemple.test/a.jpg'],
      title: 'Preuve de réalisation',
    );

    expect(find.text('Preuve de réalisation'), findsOneWidget);
  });
}
