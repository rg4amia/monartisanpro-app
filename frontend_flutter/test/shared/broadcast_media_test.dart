import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:frontend_flutter/data/models/communication_model.dart';
import 'package:frontend_flutter/shared/widgets/broadcast_media_section.dart';
import 'package:frontend_flutter/shared/widgets/video_broadcast_card.dart';
import 'package:frontend_flutter/shared/widgets/voice_broadcast_card.dart';

/// Diffusion de contenus vocaux et vidéo aux quatre espaces.
///
/// Deux exigences gouvernent ces cartes, et ce sont elles qu'on éprouve :
/// rien ne se lit sans un geste de l'utilisateur, et le coût de ce geste —
/// durée, poids — est annoncé avant. Sur un forfait mobile ivoirien, lancer
/// un téléchargement sans consentement se paie en francs CFA.
CommunicationModel _media({
  int id = 1,
  String type = 'audio',
  String? url = 'https://prosartisan.net/storage/communications/audio/a.mp3',
  int? duration,
  int? size,
  String contenu = '',
}) {
  return CommunicationModel(
    id: id,
    type: type,
    titre: type == 'audio' ? 'Message du jour' : 'Tutoriel J-Code',
    contenu: contenu,
    cibles: const ['client', 'artisan', 'fournisseur', 'livreur'],
    statut: 'publie',
    createdAt: '2026-09-12T10:00:00Z',
    mediaUrl: url,
    mediaDuration: duration,
    mediaSize: size,
  );
}

Future<void> _pump(WidgetTester tester, Widget child) => tester.pumpWidget(
      MaterialApp(home: Scaffold(body: SingleChildScrollView(child: child))),
    );

void main() {
  group('CommunicationModel — média', () {
    test('formate la duree en minutes et secondes', () {
      expect(_media(duration: 80).formattedDuration, '1:20');
      expect(_media(duration: 9).formattedDuration, '0:09');
      expect(_media(duration: 0).formattedDuration, isNull);
      expect(_media().formattedDuration, isNull);
    });

    test('formate le poids en Ko en dessous de 0,1 Mo', () {
      expect(_media(size: 2 * 1024 * 1024).formattedSize, '2.0 Mo');
      expect(_media(size: 40 * 1024).formattedSize, '40 Ko');
      expect(_media().formattedSize, isNull);
    });

    test('lit des cles absentes sans lever', () {
      // Un backend anterieur, ou un cache Hive ecrit avant la mise a jour,
      // ne porte aucune de ces cles.
      final model = CommunicationModel.fromJson({
        'id': 7,
        'type': 'annonce',
        'titre': 'Ancienne annonce',
        'contenu': 'Texte',
      });

      expect(model.mediaUrl, isNull);
      expect(model.hasMedia, isFalse);
      expect(model.mediaDuration, isNull);
    });

    test('tolere une taille renvoyee en chaine', () {
      final model = CommunicationModel.fromJson({
        'id': 8,
        'type': 'audio',
        'titre': 'Vocal',
        'contenu': '',
        'media_url': 'https://exemple.test/a.mp3',
        'media_size': '524288',
      });

      expect(model.mediaSize, 524288);
      expect(model.formattedSize, '512 Ko');
    });
  });

  group('VoiceBroadcastCard', () {
    testWidgets('annonce la duree et le poids avant toute lecture',
        (tester) async {
      await _pump(
        tester,
        VoiceBroadcastCard(
          communication: _media(duration: 80, size: 480 * 1024),
        ),
      );

      expect(find.text('Message du jour'), findsOneWidget);
      expect(find.textContaining('1:20'), findsOneWidget);
      expect(find.textContaining('480 Ko'), findsOneWidget);
    });

    testWidgets('ne demarre aucune lecture a l affichage', (tester) async {
      await _pump(tester, VoiceBroadcastCard(communication: _media()));

      // Le lecteur natif n'est construit qu'au premier appui : l'afficher ne
      // doit ni ouvrir de session audio ni consommer de donnees.
      expect(BroadcastAudioPlayer.isInitialised, isFalse);
      expect(BroadcastAudioPlayer.playingId.value, isNull);
      expect(find.byIcon(Icons.play_arrow_rounded), findsOneWidget);
      expect(find.byIcon(Icons.pause_rounded), findsNothing);
    });

    testWidgets('affiche le resume ecrit, lisible sans depenser de donnees',
        (tester) async {
      await _pump(
        tester,
        VoiceBroadcastCard(
          communication: _media(contenu: 'Nouvelle procedure de retrait.'),
        ),
      );

      expect(find.text('Nouvelle procedure de retrait.'), findsOneWidget);
    });
  });

  group('VideoBroadcastCard', () {
    testWidgets('ouvre le lien hors de l application, sur geste explicite',
        (tester) async {
      final opened = <Uri>[];

      await _pump(
        tester,
        VideoBroadcastCard(
          communication: _media(
            type: 'video',
            url: 'https://www.youtube.com/watch?v=abc123',
            duration: 245,
          ),
          launcher: (uri) async {
            opened.add(uri);

            return true;
          },
        ),
      );

      // Rien ne part avant l'appui.
      expect(opened, isEmpty);
      expect(find.textContaining('4:05'), findsOneWidget);
      // L'utilisateur est prevenu qu'il quitte l'application.
      expect(find.textContaining('hors de l\'application'), findsOneWidget);

      await tester.tap(find.text('Regarder la vidéo'));
      await tester.pumpAndSettle();

      expect(opened.single.toString(), 'https://www.youtube.com/watch?v=abc123');
    });

    testWidgets('signale l echec plutot que de rester muet', (tester) async {
      await _pump(
        tester,
        VideoBroadcastCard(
          communication: _media(type: 'video', url: 'https://exemple.test/v'),
          launcher: (_) async => false,
        ),
      );

      await tester.tap(find.text('Regarder la vidéo'));
      await tester.pumpAndSettle();

      expect(find.textContaining('Aucune application'), findsOneWidget);
    });
  });

  group('BroadcastMediaSection', () {
    testWidgets('ne rend rien quand il n y a rien a diffuser', (tester) async {
      await _pump(tester, const BroadcastMediaSection(voice: [], video: []));

      // Un en-tete de section suivi du vide se lirait comme une panne.
      expect(find.text('Messages vocaux'), findsNothing);
      expect(find.text('Vidéos'), findsNothing);
    });

    testWidgets('ecarte une publication dont le media a disparu',
        (tester) async {
      await _pump(
        tester,
        BroadcastMediaSection(
          voice: [_media(url: null)],
          video: [_media(id: 2, type: 'video', url: '')],
        ),
      );

      expect(find.byType(VoiceBroadcastCard), findsNothing);
      expect(find.byType(VideoBroadcastCard), findsNothing);
      expect(find.text('Messages vocaux'), findsNothing);
    });

    testWidgets('affiche les deux familles quand elles sont presentes',
        (tester) async {
      await _pump(
        tester,
        BroadcastMediaSection(
          voice: [_media(duration: 30)],
          video: [_media(id: 2, type: 'video', url: 'https://exemple.test/v')],
        ),
      );

      expect(find.text('Messages vocaux'), findsOneWidget);
      expect(find.text('Vidéos'), findsOneWidget);
      expect(find.byType(VoiceBroadcastCard), findsOneWidget);
      expect(find.byType(VideoBroadcastCard), findsOneWidget);
    });
  });
}
