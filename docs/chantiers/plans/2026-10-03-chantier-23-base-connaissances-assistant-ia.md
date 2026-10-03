# Plan — Chantier 23 : base de connaissances de l'Assistant IA, ingestion réelle

| Champ | Valeur |
| --- | --- |
| Statut | livré (contrôle manuel à faire) |
| Créé le | 2026-10-03 |
| Mis à jour le | 2026-10-03 |
| Auteur | Claude Code |
| Analyses liées | `2026-10-03-analyse-module-administration-llm.md` |
| Commits | — |

## Objectif

Faire du module « Administration LLM » ce qu'il annonce : un document de référence importé est réellement lu par l'IA, qui en rédige des fiches pratiques ; un administrateur les relit et les publie ; l'Assistant IA des artisans ne s'appuie que sur des fiches publiées. Supprimer au passage les réponses inventées et corriger les défauts de sécurité relevés par l'analyse.

## État actuel (lecture du code)

- L'extraction du document et la rédaction de la fiche étaient simulées dans l'écran (`setTimeout`, gabarit écrit en dur). Le fichier importé n'était jamais lu.
- Une recherche sans résultat, une photo non analysée, un chat sans fiche et une médiation sans clé Gemini renvoyaient un contenu par défaut.
- Téléversement sans contrôle sur le disque public ; téléchargement par le serveur d'une adresse fournie par l'utilisateur ; médiation IA sans contrôle de propriété ; aucune action auditée.
- Toute la logique dans `LlmAdminController` (978 lignes), qui servait aussi l'application mobile.

## Décision prise (03/10/2026)

**Ingestion réelle** (plutôt que la saisie manuelle seule) : le serveur transmet le document à Gemini, qui rédige les fiches ; chaque fiche est marquée « Générée par IA » et reste à relire. La saisie manuelle d'une fiche reste possible.

## Périmètre

- **Inclus** : import contrôlé sur disque privé, génération des fiches par Gemini, relecture, publication, retrait, audit, index Qdrant à la publication, fin des réponses de substitution (serveur et page de l'Assistant), médiation IA sécurisée, nouvel écran du backoffice, tests.
- **Exclu** : refonte complète de la page de l'Assistant (`public/client.js`), suppression des tables inutilisées, pagination des fiches.

## Étapes

### Lot A — Sécurité

- [x] Import : extensions `pdf, txt, md, png, jpg, jpeg, webp`, contenu vérifié, taille bornée (`prosartisan.llm.max_document_kb`, 15 Mo), disque privé `local` (`llm-documents/`), téléchargement par la session du backoffice.
- [x] Migration : les fichiers de l'ancien dossier public `fileshare` rejoignent le disque privé.
- [x] `image_url` n'est plus lue : le serveur ne télécharge aucune adresse fournie par l'utilisateur.
- [x] Médiation IA : parties au litige ou administrateur uniquement, quota IA, journal d'usage, aucun nom transmis à l'IA.
- [x] Audit de chaque action (`llm.document.*`, `llm.sheet.*`).
- [x] Identifiant des fiches créé par le serveur ; contenu validé (`KnowledgeSheetSchema`).

### Lot B — Fin des réponses inventées

- [x] Recherche sans fiche : liste vide.
- [x] Photo non analysée : 503 avec un message, sauf si des mots-clés permettent une recherche.
- [x] Chat sans IA : annonce de l'indisponibilité, citation de la fiche validée s'il y en a une, sinon rien d'autre.
- [x] Médiation sans IA : 503.
- [x] Page de l'Assistant : plus de fiches de démonstration en cache, plus de diagnostic « de secours » fabriqué dans la page, message du serveur affiché.

### Lot C — Ingestion réelle

- [x] `GeminiService::extractKnowledgeSheets` : document en pièce jointe, réponse JSON, consigne « n'invente rien », 5 fiches au plus, passage d'origine recopié.
- [x] `KnowledgeBaseService` : import, génération, relecture, publication, retrait.
- [x] `GenerateKnowledgeSheetsJob`, exécuté après la réponse HTTP ; l'écran suit l'état de l'import.
- [x] Échec fermé : sans réponse exploitable, l'import passe en « Échec » avec son motif et aucune fiche n'est créée.
- [x] Génération restée « en cours » plus de 10 minutes : tenue pour interrompue, relançable.

### Lot D — Couche service et index

- [x] `AssistantService` (chat, recherche), `DisputeMediationService`, `KnowledgeVectorIndex` ; `AssistantController` pour l'application, `LlmAdminController` réduit au backoffice.
- [x] Qdrant : fiche indexée à la publication, retirée au retrait ; modèle d'embedding en configuration.

### Lot E — Écran

- [x] `panels/LlmAdminPanel.tsx` et `panels/KnowledgeSheetEditor.tsx` : documents, fiches à relire, fiches publiées, essai de l'Assistant ; libellés français ; composants partagés.

## Règles d'or concernées

16, 17, 25, 29, 35, 36, 40, 49, 77, 87 ; nouvelle règle 94.

## Vérification

- `tests/Feature/Chantier23LlmKnowledgeTest.php` (import, génération, relecture, droits, Assistant, médiation).
- `resources/js/pages/admin/panels/LlmAdminPanel.test.tsx`.
- Suites complètes Pest (SQLite et MariaDB) et Vitest.

## Écarts

- **Aucun essai avec le vrai Gemini** : la génération est vérifiée par des réponses simulées dans les tests. La qualité des fiches rédigées à partir d'un vrai PDF reste à constater.
- **Traitement après la réponse HTTP** : sur l'hébergement mutualisé, un PDF long peut dépasser le temps d'exécution autorisé. L'import repasse alors en « Échec » au bout de 10 minutes et se relance.
- **Tables inutilisées conservées** (`ia_users`, `professions`, `categories`, `contexts`) : leur suppression en production demande une décision.
- **Page de l'Assistant** : seules les réponses inventées ont été retirées ; ses modes réseau simulés et ses exemples de pathologies restent.
- **Recherche par mots-clés** : `ProductionItem::all()` à chaque question, acceptable tant que la base compte peu de fiches.
- **Fiches publiées avant ce chantier** : conservées telles quelles ; celles issues de l'ancienne maquette sont à retirer à la main depuis « Fiches publiées ».
