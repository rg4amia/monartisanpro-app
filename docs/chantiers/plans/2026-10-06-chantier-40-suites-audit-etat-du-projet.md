# Plan — Chantier 40 : suites de l'audit de l'état du projet

| Champ | Valeur |
| --- | --- |
| Statut | en cours |
| Créé le | 2026-10-06 |
| Mis à jour le | 2026-10-06 |
| Auteur | Claude Code (Opus 5.5) |
| Analyses liées | `../audits/2026-10-06-audit-etat-du-projet-apres-chantier-39.md` |
| Commits | — |

## Objectif

Corriger les anomalies relevées par l'audit de l'état du projet après le Chantier 39, en commençant par les deux qui touchent la production.

## Périmètre

- Inclus : anomalies 1, 2 et 4 à 12 de l'audit.
- Exclu : anomalie 3 (renouvellement des secrets et du jeton, sortie du dépôt de `public_html`), qui ne se règle pas dans le code.

## Étapes

1. **Lot A — production** : origine `www.` autorisée dans `config/cors.php` (anomalie 2) ; le push relance le déploiement complet resté à mi-parcours (anomalie 1).
2. **Lot B — constats laissés ouverts par le Chantier 39** : téléversement sur le disque public (anomalie 4), capacités fines des routes de l'API (anomalie 5), messages d'exception (anomalie 6).
3. **Lot C — hygiène** : dépendances JavaScript (anomalie 8), filtre HTML de secours (anomalie 9), Pint (anomalie 10), restes de l'audit du matin (anomalie 11), en-tête `x-powered-by` (anomalie 12), fichiers de règles et journal (anomalie 7).

## Règles d'or concernées

- 36 et 105 : capacité fine, pas le seul rôle `admin`.
- 35 : aucun message technique renvoyé à l'utilisateur.
- 40 : aucun document sensible à une adresse publique permanente.
- 54, 70 et 77 : fichiers de règles et journal tenus dans le même commit.

## Vérification

- `Chantier40AuditFollowUpsTest.php`, chaque correctif avec un test qui échoue avant.
- Après le déploiement du lot A : `Access-Control-Allow-Origin` présent pour `https://www.prosartisan.net`, vitrine à jour.

## Écarts

À renseigner à la livraison.
