import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:get/get.dart';

/// Fait tourner une action de contrôleur (appel réseau + `Get.snackbar`) dans
/// un contexte de test stable, sans changer le code de production.
///
/// Deux pièges distincts sont neutralisés ici :
///
/// 1. **`Get.snackbar` sans overlay monté.** La plupart des contrôleurs de
///    l'app appellent `Get.snackbar(...)` directement dans leurs chemins de
///    succès ET d'erreur (pas d'état observable séparé à la
///    `OrderFollowUpController.errorMsg`). Sans `GetMaterialApp` monté,
///    `Get.snackbar` lève aussitôt (`Get.key.currentState` est `null`).
///    → on monte un `GetMaterialApp` minimal avant d'exécuter [action].
///
/// 2. **Un vrai appel réseau (Dio) sous `testWidgets`.** `testWidgets`
///    exécute le corps du test dans une zone `FakeAsync` à horloge
///    virtuelle : elle n'avance que lorsqu'on appelle `tester.pump(...)`.
///    Un `await` direct sur un appel Dio (même entièrement mocké côté
///    adaptateur HTTP) peut alors ne **jamais** se résoudre, car rien ne fait
///    avancer l'horloge pendant l'attente — le test reste bloqué
///    indéfiniment en temps réel, sans message d'erreur exploitable.
///    → on exécute [action] via `tester.runAsync`, qui la fait tourner sur
///    une vraie boucle d'événements le temps de l'appel.
///
/// Une fois [action] terminée, on annule explicitement toute snackbar
/// affichée plutôt que d'attendre son délai d'auto-fermeture (3 s par
/// défaut). Ce délai est piloté par un `Timer` que GetX crée **pendant**
/// l'exécution réelle ouverte par `tester.runAsync` : ce `Timer` ignore
/// ensuite l'horloge virtuelle de `testWidgets` (`tester.pump(duration)` ne
/// le fait pas avancer) et continue de tourner en tâche de fond, sur
/// l'horloge murale réelle, une fois revenu dans la zone `FakeAsync` du
/// test. S'il se déclenche pendant l'exécution d'un test *ultérieur* — une
/// fois l'app GetX de CE test démontée — il fait planter ce test suivant
/// (`Null check operator used on a null value` dans
/// `SnackbarController._configureOverlay`), rapporté comme un échec « after
/// it had already completed » qui semble sans rapport avec sa cause réelle.
/// `Get.closeAllSnackbars()` annule ce `Timer` de façon synchrone
/// (`SnackbarController._cancelTimer`), sans dépendre d'aucune horloge.
Future<void> runControllerAction(
  WidgetTester tester,
  Future<void> Function() action,
) async {
  // `unknownRoute` : plusieurs contrôleurs enchaînent un `Get.toNamed`/
  // `Get.offNamed` après leur appel réseau (ex: redirection vers l'écran de
  // confirmation d'un scan J-Code). Sans route enregistrée pour ce nom,
  // Flutter lève « Could not find a generator for route » — on fournit une
  // page de repli générique plutôt que de dupliquer la table de routes de
  // l'app dans chaque test.
  await tester.pumpWidget(
    GetMaterialApp(
      home: const Scaffold(body: SizedBox.shrink()),
      onUnknownRoute: (settings) => MaterialPageRoute<void>(
        settings: settings,
        builder: (_) => const Scaffold(body: SizedBox.shrink()),
      ),
    ),
  );
  await tester.pump();

  await tester.runAsync(action);

  Get.closeAllSnackbars();
  await tester.pump();
  await tester.pump(const Duration(seconds: 1));
}
