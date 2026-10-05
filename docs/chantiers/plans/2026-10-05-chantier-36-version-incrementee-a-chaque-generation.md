# Plan — Chantier 36 : version de l'application incrémentée à chaque génération

| Champ | Valeur |
| --- | --- |
| Statut | livré (installation sur appareil et envoi sur Google Play à faire) |
| Créé le | 2026-10-05 |
| Mis à jour le | 2026-10-05 |
| Auteur | Claude Code |
| Analyses liées | — |
| Commits | — |

## Objectif

Demandes d'Inza Bamba du 05/10/2026 : générer un nouvel APK, intégrer une fonctionnalité qui incrémente la version à chaque génération, puis générer un bundle pour test sur Google Play.

La version (`version:` de `pubspec.yaml`) se modifiait à la main. Deux générations successives portaient donc souvent le même numéro de build : Android refuse d'installer un APK dont le numéro n'est pas supérieur à celui déjà installé, et Google Play refuse un bundle dont le numéro a déjà été envoyé.

## Décisions retenues

Choix faits pendant le chantier, à confirmer :

- par défaut, une génération augmente le **correctif et le numéro de build** (`1.0.1+2` → `1.0.2+3`) ; `--build-only` n'augmente que le numéro de build ;
- le numéro de build augmente toujours, quel que soit le niveau choisi, et ne repart jamais de 1.

## Périmètre

- Inclus : `frontend_flutter/tool/version_bump.dart`, `frontend_flutter/tool/build_apk.dart`, `frontend_flutter/test/tool/version_bump_test.dart`, `frontend_flutter/pubspec.yaml`.
- Exclu : le workflow `mobile-ci.yml`, qui construit toujours son APK sans incrément ; l'affichage de la version dans l'application ; l'envoi automatique sur Google Play.

## Étapes

1. **Lecture et écriture de la version** — `version_bump.dart` : `readVersion`, `AppVersion.bump`, `writeVersion`. Seule la ligne `version:` change ; une version absente ou sans numéro de build arrête la génération plutôt que de produire un numéro inventé (Règle d'or 29).
2. **Génération** — `dart run tool/build_apk.dart`, depuis `frontend_flutter/` :
   - options `--build-only`, `--minor`, `--major`, `--no-bump`, `--split-per-abi`, `--bundle` ;
   - la commande porte toujours `--dart-define-from-file=env.json` (Règle d'or 30) ;
   - si la génération échoue, `pubspec.yaml` revient à son état d'origine : aucun numéro n'est consommé par un fichier qui n'existe pas ;
   - le fichier produit est copié sous un nom portant sa version (`prosartisan-<version>-build<N>.apk` ou `.aab`), que la génération suivante n'écrase pas.
3. **Bundle Google Play** — `--bundle` lance `flutter build appbundle` ; l'outil s'arrête si `android/key.properties` manque, car la génération se rabattrait sur la clé de débogage, que Google Play refuse.

## Règles d'or concernées

29, 30, 54, 70. Nouvelle : 115 de `CLAUDE.md` (151 d'`AGENTS.md`).

## Vérification

- **Flutter** : `version_bump_test.dart` (6 tests) ; `flutter analyze tool test/tool` sans remarque.
- **Générations réelles du 05/10/2026** :
  - APK `prosartisan-1.0.2-build3.apk` (84,2 Mo), version passée de `1.0.1+2` à `1.0.2+3` ;
  - bundle `prosartisan-1.0.3-build4.aab` (106,3 Mo), signé avec la clé d'envoi ProsArtisan.
- **Manuel** : installer l'APK sur un appareil ; déposer le bundle dans une piste de test de la Play Console.

## Écarts

- **Non vérifié** : ni l'APK ni le bundle n'ont été installés sur un appareil ; la correspondance entre `upload-keystore.jks` et la clé d'envoi enregistrée dans la Play Console n'a pas été contrôlée.
- **Retour en arrière sur échec non éprouvé en réel** : les deux générations ont réussi ; la restauration de `pubspec.yaml` n'a pas été exercée.
- **Manuel d'utilisation inchangé** : rien n'est visible des utilisateurs.

## Suites

- Faire passer le workflow `mobile-ci.yml` par l'outil, ou l'en tenir écarté pour de bon.
- Afficher la version dans l'application (écran Profil ou « Aide et support »).
