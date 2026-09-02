# Plan d'implémentation — Dette technique mobile (`frontend_flutter/`)

**Date** : 2 septembre 2026
**Périmètre** : les 3 chantiers identifiés lors de l'analyse mobile et volontairement
différés du commit `be598ed7` (sécurité réseau, synchro hors-ligne, anti-contournement).

| # | Chantier | Effort | Priorité | Risque de régression |
|---|---|---|---|---|
| 1 | Couche réseau + cache unifiée pour tous les repositories | ~5 j | Haute | Moyen |
| 2 | Découpe des 5 vues > 1 400 lignes | ~4 j | Moyenne | Faible |
| 3 | Durcissement progressif du lint | ~3 j | Moyenne | Faible |

Total indicatif : **~12 jours-homme**, découpables en 3 lots livrables indépendamment.

---

## État d'avancement (mis à jour le 2 septembre 2026)

| Chantier | Étape | Statut | Commit |
|---|---|---|---|
| 1 | `NetworkExecutor` + tests | ✅ Fait | `bb947ba2` |
| 1 | `CacheStore<T>` + `HiveCipherProvider` + tests | ✅ Fait | `bb947ba2` |
| 1 | `mission_repository` délègue à `NetworkExecutor` | ✅ Fait | `bb947ba2` |
| 1 | Migration `artisan_repository` + `wallet_repository` | ✅ Fait | `bb947ba2` |
| 1 | `SyncService.flush()` après login + purge cache au logout | ✅ Fait | `bb947ba2` |
| 1 | Bandeau hors-ligne global (`OfflineBanner` dans `main_tab`) | ✅ Fait | `bb947ba2` |
| 1 | Migration `devis_repository` + `notification_repository` | ✅ Fait | `8cdf3ccd` |
| 3 | `core/errors/` (`AppException` + mapper) + tests | ✅ Fait | `a9678eac` |
| 3 | Lint `directives_ordering` (`dart fix`, 44 fichiers) | ✅ Fait | `a9678eac` |
| 3 | Lint `prefer_single_quotes` (`dart fix`, 13 fichiers) | ✅ Fait | `a154304e` |
| 3 | Lint `only_throw_errors` (0 violation) | ✅ Fait | `9968da80` |
| 3 | Réparation de `mobile-ci.yml` (chemins/branches, gate `flutter analyze`) | ✅ Fait | `9968da80` |
| 3 | Lint `unawaited_futures` (51 sites : 50 `unawaited()` + 1 vrai `await` dans `StorageService.clearAll`) | ✅ Fait | `21a11658` |
| 3 | Lint `require_trailing_commas` (`dart format .` = 103 fichiers + `dart fix` 632 corrections + 6 `if` enrobés d'un bloc) | ✅ Fait | `23c341b0` |
| 3 | Lint `avoid_dynamic_calls` — couche repository (`res.data` casté, 49 sites) | ✅ Fait | `ffaa7e77` |
| 3 | Lint `avoid_dynamic_calls` — contrôleurs + vues (111 sites, modèles typés) + `flutter analyze --fatal-infos` en CI | ✅ Fait | `a2bfbbdf` |
| 2 | Écran 5 — `artisan_home_screen.dart` : 1438 → **157 l.**, 12 widgets extraits dans `home/widgets/artisan_home/` + 7 tests widget | ✅ Fait | `a820fba6` |
| 2 | Écran 4 — `client_home_screen.dart` : 2195 → **162 l.**, 21 widgets extraits dans `home/widgets/client_home/` + 8 tests widget | ✅ Fait | `262b86ff` |
| 2 | Écran 3 — `devis_review_screen.dart` : 1785 → **119 l.**, 11 fichiers dans `missions/widgets/devis_review/` (+ `formatDevisFcfa`, token `AppColors.dangerSoft`) + 3 tests | ✅ Fait | `c4dbaa0b` |
| 2 | Écran 2 — `devis_creation_screen.dart` (2162 l.) | ⏳ À faire | — |
| 2 | Écran 1 — `mission_tracking_screen.dart` (2740 l., OTP/jalons) | ⏳ À faire | — |

> **Chantier 3 terminé.** Les 6 règles de lint sont actives et `flutter analyze
> --fatal-infos` protège la CI contre toute régression.
>
> **Chantier 2 démarré par l'écran le moins risqué** (`artisan_home`, tableau de
> bord sans logique de mutation). Méthode appliquée : sous-widgets `_Palette.*`
> convertis en `AppColors.*`, primitives réutilisables (`StatCard`, `ActionTile`,
> `StatusPill`, `MetaText`, `HeroMetric`) rendues publiques et couvertes par des
> tests widget ; composites (`HeroHeader`, `StatGrid`, `QuickActions`,
> `WorkflowReminder`, `MissionQueue`, `ProsArtisanScoreCard`) extraits tels quels.
> L'état d'erreur inline devient `_ErrorRetryView`. Les 4 écrans restants — dont
> `mission_tracking` (OTP/jalons/paiements) — exigent au préalable un harnais de
> mock des contrôleurs GetX pour un vrai golden test d'écran.

---

## Chantier 1 — Couche réseau + cache unifiée

### 1.1 Constat

| Repository | LOC | Retry | Cache offline |
|---|---|---|---|
| `mission_repository.dart` | 526 | ✅ `_executeWithRetry` (backoff expo.) | ✅ `MissionCacheService` (Hive AES-256) |
| 14 autres (`artisan`, `wallet`, `devis`, `order`, `jcode`…) | 16–170 | ❌ | ❌ |

La résilience réseau — pilier du marché visé (connectivité faible) — n'existe que
pour les missions. `artisan_repository` (artisans à proximité, consulté sur la carte)
et `wallet_repository` (solde) sont les manques les plus visibles pour l'utilisateur.

### 1.2 Cible

Deux primitives réutilisables dans `core/network/` et `core/cache/`, sans imposer
de réécriture massive des repositories.

```
core/network/
  network_executor.dart      # retry + classification d'erreurs (extrait de mission_repository)
core/cache/
  cache_store.dart           # box Hive chiffrée générique <T> (généralise MissionCacheService)
  cache_policy.dart          # TTL + stratégies (cacheFirst / networkFirst / cacheOnly)
```

#### `NetworkExecutor`

- Extraire `_executeWithRetry` de `mission_repository.dart:466` **sans changement de
  comportement** : `maxRetries` (défaut 3), backoff `1s → 2s → 4s`, retry sur
  `connect/receive/sendTimeout`, `connectionError`, `408`, `429` ; jamais sur les
  autres `4xx`.
- Exposé comme service GetX singleton **ou** simple classe utilitaire statique
  (`NetworkExecutor.run(() => dio.get(...))`).
- `mission_repository` délègue à ce service (suppression de sa copie privée).

#### `CacheStore<T>`

- Généraliser `MissionCacheService` : une box Hive chiffrée AES-256 (clé déjà gérée
  via `flutter_secure_storage`, cf. `mission_cache_service.dart:31`), self-healing
  sur box corrompue conservé.
- API :

```dart
class CacheStore<T> {
  CacheStore(this.boxName, {required this.fromJson, required this.toJson});

  Future<void> put(String key, T value, {Duration? ttl});
  T?   get(String key, {bool ignoreExpiration = false});
  List<T>? getList(String key, {bool ignoreExpiration = false});
  Future<void> invalidate(String key);   // '*' = tout
  bool isFresh(String key, Duration ttl);
}
```

- Métadonnées (timestamps) dans une box dédiée, comme aujourd'hui.

#### Helper `cachedFetch`

Wrapper qui compose les deux, pour ré-écrire un repository en ~5 lignes :

```dart
Future<List<ArtisanModel>> getNearby({...}) => cachedFetch(
      store: _artisanStore,
      key: 'nearby_$lat_$lng_$radius',
      ttl: const Duration(minutes: 3),
      policy: CachePolicy.cacheFirst,
      fetch: () => NetworkExecutor.run(
        () => _client.get(ApiEndpoints.artisans, params: {...}),
      ).then((r) => (r.data['data'] as List).map(ArtisanModel.fromJson).toList()),
    );
```

### 1.3 Étapes

1. **Extraire `NetworkExecutor`** + test unitaire (mock `DioException` → vérifier
   nombre de tentatives et délais avec `fakeAsync`). `mission_repository` bascule
   dessus. *Aucune modif fonctionnelle attendue → les tests missions existants
   doivent rester verts.* — **1 j**
2. **Généraliser `CacheStore<T>`** à partir de `MissionCacheService` ; réimplémenter
   `MissionCacheService` par-dessus (ou le remplacer) ; tests de round-trip +
   expiration + box corrompue. — **1,5 j**
3. **Migrer 4 repositories prioritaires** avec `cachedFetch` :
   `artisan_repository` (nearby + profil), `wallet_repository` (solde + historique),
   `devis_repository` (lecture), `notification_repository` (liste). — **1,5 j**
4. **Brancher `SyncService.flush()`** après un login réussi
   (`AuthController` / `verify-otp`) — aujourd'hui la file n'est rejouée que sur
   changement de connectivité. — **0,25 j**
5. **Bandeau « hors-ligne »** global piloté par `SyncService.isOffline`
   (widget dans le `Scaffold` racine de `main_tab`). — **0,75 j**

### 1.4 Points de vigilance

- Ne migrer un repository de mutation (`POST`/`PUT`) vers le cache **que** pour ses
  lectures ; les écritures continuent de passer par `SyncService.enqueueRequest`
  sur `connectionError`.
- Les clés de cache doivent inclure le `user_id` (via `StorageService.getUserId()`)
  pour éviter la fuite de données entre comptes sur un même appareil.
- Invalider le cache concerné après chaque mutation réussie (déjà le motif dans
  `mission_repository`).
- Purge du cache dans `AuthRepository.logout()` (`CacheStore.invalidate('*')` sur
  toutes les box).

---

## Chantier 2 — Découpe des vues volumineuses

### 2.1 Constat

| Fichier | LOC | Sous-widgets privés déjà présents |
|---|---|---|
| `missions/views/mission_tracking_screen.dart` | 2 740 | ~20 (`_MissionHeaderCard`, `_JalonsSection`, `_JalonCard`…) |
| `missions/views/devis_creation_screen.dart` | 2 162 | ~18 |
| `home/views/client_home_screen.dart` | 2 091 | à cartographier |
| `missions/views/devis_review_screen.dart` | 1 743 | ~17 |
| `home/views/artisan_home_screen.dart` | 1 409 | à cartographier |

**Bonne nouvelle** : ces écrans sont déjà découpés en widgets privés — ils sont
juste tous dans un seul fichier. Le travail est à 80 % de l'**extraction mécanique**,
pas de la ré-architecture.

**Exception** : quelques widgets sont eux-mêmes trop gros et demandent une vraie
découpe, notamment `_JalonCard` (`mission_tracking_screen.dart:1287-1886`, ~600 l.)
et les `_MaterialsSection` / `_JalonsSection` de `devis_creation_screen.dart`.

### 2.2 Cible

Par module concerné :

```
modules/missions/views/
  mission_tracking_screen.dart          # < 300 l. : Scaffold + orchestration
  widgets/tracking/
    mission_header_card.dart
    counterparty_card.dart
    workflow_card.dart
    budget_section.dart
    escrow_section.dart
    devis_section.dart
    materials_section.dart
    jalons_section.dart
    jalon_card/                          # _JalonCard éclaté
      jalon_card.dart
      jalon_proof_gallery.dart
      jalon_otp_panel.dart
      jalon_status_chip.dart
    bottom_actions.dart
```

Règles :
- 1 widget public par fichier, `part`/`part of` proscrit.
- Un fichier de vue « écran » ne dépasse pas ~300 lignes ; il ne contient que le
  `Scaffold`, le `Obx`/`GetBuilder` racine et l'assemblage des sections.
- Toute logique (calculs, appels controller, formatage) descend dans le controller
  ou un `*_view_model` ; les widgets extraits restent `StatelessWidget` recevant
  des données + callbacks.
- Les widgets réutilisés entre écrans (`_InfoRow`, `_Pill`, `_ActionButton`,
  `_SectionContainer`, `_RecapRow`…) montent dans `shared/widgets/`.

### 2.3 Étapes (itératif, 1 écran ≈ 0,75 j)

Pour **chaque** écran, dans cet ordre (le plus risqué en premier) :

1. `mission_tracking_screen.dart` — **1 j** (inclut la découpe de `_JalonCard`)
2. `devis_creation_screen.dart` — **1 j**
3. `devis_review_screen.dart` — **0,75 j**
4. `client_home_screen.dart` — **0,75 j**
5. `artisan_home_screen.dart` — **0,5 j**

Méthode par écran :
1. `git mv` conceptuel : créer `widgets/<feature>/`, déplacer 1 widget privé →
   fichier, le passer `public` (retirer le `_`), ajouter l'`import`.
2. Compiler + `flutter analyze` après **chaque** widget déplacé (commits atomiques).
3. Factoriser les widgets partagés vers `shared/widgets/` en fin de passe.
4. Test de non-régression visuelle : golden test léger sur l'écran assemblé
   (`flutter test --update-goldens` puis vérification manuelle), ou a minima un
   `pumpWidget` + `expect(find.byType(...), findsWidgets)`.

### 2.4 Points de vigilance

- Aucune modification de comportement dans ce chantier : purement structurel.
- Ne pas toucher aux `Obx`/`GetX<Controller>` : garder la même granularité de
  rebuild (extraire un widget ne doit pas élargir la portée d'un `Obx`).
- Attention aux `BuildContext` capturés (snackbars, `Navigator`) lors du
  déplacement — passer le contexte en paramètre plutôt que le fermer.

---

## Chantier 3 — Durcissement progressif du lint

### 3.1 Constat

`analysis_options.yaml` n'active que `flutter_lints` par défaut. Activer d'un coup
`prefer_single_quotes`, `require_trailing_commas`, `directives_ordering`,
`avoid_dynamic_calls`, `unawaited_futures`, `only_throw_errors` produit
**~515 avertissements** (dont ~53 `unawaited_futures` qui sont de vrais défauts
potentiels : `Future` non attendus dans des `initState`, `onPressed`…).

### 3.2 Stratégie : une règle à la fois, chaque activation = 1 PR verte

| Ordre | Règle | Nature du fix | Auto-fixable | Volume estimé |
|---|---|---|---|---|
| 1 | `directives_ordering` | tri des imports | ✅ `dart fix --apply` | ~faible |
| 2 | `prefer_single_quotes` | `"` → `'` | ✅ `dart fix --apply` | ~moyen |
| 3 | `require_trailing_commas` | virgules finales | ✅ `dart format` + `dart fix` | ~moyen |
| 4 | `unawaited_futures` | ajouter `await` / `unawaited()` — **revue manuelle obligatoire** | ⚠️ partiel | ~53 sites |
| 5 | `only_throw_errors` | remplacer `throw 'string'` / `throw Exception` par des types d'erreur dédiés | ❌ manuel | à mesurer |
| 6 | `avoid_dynamic_calls` | typer les `res.data['x']` — se combine avec le Chantier 1 (modèles typés) | ❌ manuel | à mesurer |

### 3.3 Étapes

1. **Créer `lib/core/errors/`** : hiérarchie `AppException` (→ `NetworkException`,
   `ValidationException`, `AuthException`, `CacheException`) pour préparer les
   règles 5 et 6. — **0,5 j**
2. **Règles 1–3** (auto-fixables) : une PR chacune = `dart fix --apply` +
   `dart format .` + activation de la règle + `flutter analyze` vert. — **0,75 j**
3. **Règle 4 `unawaited_futures`** : revue site par site. Cas typiques :
   navigation « fire-and-forget » → `unawaited(Get.to(...))` ; effets de bord
   dans `onPressed` async → `await` ; télémétrie → `unawaited(...)`. — **1 j**
4. **Règle 5 `only_throw_errors`** : brancher sur la hiérarchie de l'étape 1. — **0,5 j**
5. **Règle 6 `avoid_dynamic_calls`** : à faire **après** le Chantier 1 (les
   repositories renverront des modèles typés, la majorité des `dynamic` disparaît).
   Le reliquat : `// ignore: avoid_dynamic_calls` justifié au cas par cas. — **0,25 j**
6. **CI** : ajouter `flutter analyze --fatal-infos` au workflow `mobile-ci.yml`
   une fois toutes les règles vertes, pour empêcher toute régression.

### 3.4 Points de vigilance

- Ne jamais mélanger une PR « activation de règle » avec une PR fonctionnelle.
- `dart fix --apply` peut réordonner des imports conditionnels (`dart:io` /
  `dart:html`) — vérifier les fichiers qui font du web + mobile.
- `require_trailing_commas` change beaucoup de lignes → faire cette PR **seule** et
  la merger vite pour éviter les conflits.

---

## Séquencement recommandé

```
Sprint N     : Chantier 1 étapes 1–2 (NetworkExecutor + CacheStore + tests)
               Chantier 3 étapes 1–2 (errors/ + règles auto-fixables)
Sprint N+1   : Chantier 1 étapes 3–5 (migration repos + SyncService.flush + bandeau)
               Chantier 2 écrans 1–2 (mission_tracking, devis_creation)
Sprint N+2   : Chantier 2 écrans 3–5
               Chantier 3 étapes 3–6 (unawaited_futures, only_throw_errors, CI)
```

Chantiers 2 et 3 sont **parallélisables** entre deux développeurs (l'un structurel,
l'autre lint). Le Chantier 1 doit précéder l'étape 6 du Chantier 3 (typage).

## Définition de « terminé »

- `flutter analyze` : 0 issue, `--fatal-infos` activé en CI.
- Tous les repositories de lecture passent par `CacheStore` + `NetworkExecutor`.
- Aucun fichier de vue > 400 lignes (cible 300).
- `mobile-ci.yml` exécute `flutter test` + `flutter analyze --fatal-infos` sur chaque PR.
- Documentation `frontend_flutter/README.md` mise à jour (pattern repository +
  convention de découpe des vues).
