# Analyse — Module « Administration LLM ProsArtisan »

- **Date** : 2026-10-03
- **Auteur** : Claude (Opus 5.5), à la demande d'Inza Bamba
- **Statut** : analyse seule, aucun code modifié
- **Suite** : `plans/2026-10-03-chantier-23-base-connaissances-assistant-ia.md`

## Question posée

Que fait réellement le module « Administration LLM » du backoffice, dans quel état est-il, et que faut-il corriger ?

## Méthode et sources

- **Serveur** : `app/Http/Controllers/Admin/LlmAdminController.php` (978 lignes), `routes/web.php` (groupe `admin/api/llm`), `routes/api.php` (`/chat`, `/search`, `/litiges/{litige}/llm-mediation`), migration `2026_07_02_000000_create_ia_tables.php`, modèles `StagingItem`, `ProductionItem`, `ImportHistory`, `LlmAttachment`, `LlmCategory`, `LlmContext`, `Profession`.
- **Backoffice** : `resources/js/pages/admin/llm-admin-panel.tsx` (929 lignes).
- **Tests** : `tests/Feature/LlmAdminTest.php` (6 tests), `AiQuotaEnforcementTest.php`.
- **Sonde d'exécution** : un test jetable, lancé puis supprimé, a confirmé les constats marqués « vérifié ». Les autres reposent sur la lecture du code.
- **Base locale** : les tables `production_items`, `staging_items`, `import_history`, `contexts` et `ia_users` sont vides. Le contenu de la base de production n'a pas été consulté.

## Ce que le module est censé faire

Alimenter l'Assistant IA des artisans en fiches techniques BTP validées :

1. un administrateur importe un document de référence (norme LBTP, BNETD…) ;
2. un modèle en extrait le texte et les tableaux ;
3. un modèle le « vulgarise » en fiche (méthode, dosages en unités locales, matériaux, coût) ;
4. la fiche attend en brouillon (`staging_items`) ; l'administrateur la corrige, l'approuve ou la rejette ;
5. approuvée, elle passe en base de production (`production_items`) ;
6. l'Assistant IA (`POST /api/v1/chat` et `/search`, écran mobile `/assistant`) s'appuie sur ces fiches pour répondre.

## Constats

### Ce qui tient

- **Accès gardé** : toutes les routes `admin/api/llm/*` exigent `admin.llm.manage`.
- **Circuit de validation en place** : brouillon → approbation ou rejet → production ; le chat ne lit que les fiches approuvées.
- **Quota IA appliqué** sur le chat et la recherche (Règle d'or 25), appels journalisés dans `ai_usage_logs`.
- **Erreur brute de Gemini jamais renvoyée** à l'artisan (`gracefulChatDegradation`).
- **Confirmations par `useConfirm`** dans l'écran.

### A. Le pipeline d'ingestion est une démonstration

**A1. L'extraction du document n'existe pas.** `runVlmParsing` attend une seconde puis affiche un tableau écrit dans le code (`setTimeout`, `llm-admin-panel.tsx:185`). Le journal de l'écran annonce « Lancement du traitement LlamaParse » : aucun service n'est appelé. Le fichier téléversé n'est jamais lu.

**A2. La fiche « générée » est un gabarit écrit en dur.** `runLlmDownscaling` (`llm-admin-panel.tsx:209-284`) ne contacte aucun modèle. Pour tout document autre que l'exemple « Dalles Béton », la fiche contient toujours le même contenu, sans rapport avec le document : « Ciment CPJ 32.5 R, 1 sac », « Sable fin, 2,5 brouettes », « 3 500 - 5 000 FCFA par m² », type d'ouvrage « Maçonnerie ». Le titre et l'étiquette sont déduits du nom du fichier.

**A3. Cette fiche inventée peut être approuvée et servir de source à l'Assistant.** Une fois en production, `chat` l'injecte dans la consigne de Gemini comme « informations locales de notre base de données BTP » à utiliser en priorité. Un artisan recevrait donc un dosage sans fondement, présenté comme une référence validée. Contraire à la Règle d'or 29.

**A4. Deux documents de démonstration sont proposés en permanence** (`MOCK_INSTITUTIONAL_DOCS`), impossibles à distinguer d'un vrai document importé.

**A5. La base de production des fiches n'a aucune source initiale** : pas de seeder, tables vides en local. Si elle est vide en production, l'Assistant répond sans aucune fiche.

### B. Réponses inventées servies aux artisans (Règle d'or 29)

**B1. Recherche sans résultat → dosage par défaut** (vérifié). `generateFallback` renvoie « Ciment CPJ 42.5 : 1 sac ; sable : 2,5 brouettes » pour n'importe quelle recherche sans fiche correspondante, y compris une recherche sur l'électricité.

**B2. Photo sans analyse possible → résultat simulé** (vérifié). Sans clé Gemini ou si l'analyse échoue, `simulateVlmResult` renvoie « Recommandation visuelle automatique » avec un coût « Moyen », comme si la photo avait été analysée.

**B3. Chat sans fiche → conseil de dosage générique** (vérifié) : « utilise du ciment CPJ 42.5 pour les dalles… ».

**B4. Médiation de litige sans clé Gemini → texte tout fait** présenté comme des « faits extraits » et une « analyse » du litige (`llmMediation`, ligne 970).

### C. Sécurité

**C1. Téléversement sans contrôle sur le disque public** (vérifié). `upload` accepte n'importe quelle extension et n'importe quelle taille, et enregistre le fichier sous `/storage/fileshare/<uuid>.<extension>`, accessible sans authentification. Un fichier `.php` est accepté ; sur un hébergement mutualisé qui exécute PHP dans ce dossier, c'est une exécution de code. Un fichier `.html` permet d'héberger une page piégée sur le domaine. Les documents de référence importés sont par ailleurs lisibles de tous.

**C2. Le serveur télécharge une adresse fournie par l'utilisateur.** `search` appelle `Http::get($validated['image_url'])` sans contrôle de l'hôte ni du protocole. La route `POST /api/v1/search` est ouverte à tout compte connecté de l'application : n'importe quel utilisateur peut faire interroger par le serveur une adresse interne.

**C3. Médiation IA d'un litige sans contrôle de propriété.** `POST /api/v1/litiges/{litige}/llm-mediation` ne vérifie ni que l'appelant est partie au litige, ni son rôle (Règle d'or 36). Le nom des deux parties, leurs scores et les montants de la mission sont envoyés à Gemini et la réponse est rendue à l'appelant. Pas de quota, pas de journal d'usage, pas de délai maximal sur l'appel. Aucun écran ne l'appelle aujourd'hui.

**C4. Aucune action n'est auditée** (Règle d'or 17) : approbation, rejet, suppression d'une fiche, vidage de l'historique et des fichiers.

**C5. Vidage irréversible** : `clearImports` exécute `truncate` sur deux tables et supprime le dossier des fichiers.

**C6. Entrées non validées** : `updateStaging` enregistre `$request->all()` comme contenu de la fiche ; `updateImport` accepte n'importe quel statut ; l'identifiant d'une fiche est choisi par le navigateur (`stage-` suivi d'un nombre tiré au hasard entre 200 et 1 199), et un doublon répond 500 (vérifié).

**C7. Le métier (`trade`) et le message sont insérés tels quels dans la consigne** envoyée à Gemini ; la clé d'API voyage dans l'adresse de la requête.

### D. Architecture

**D1. Toute la logique est dans le contrôleur** (978 lignes : accès base, appels Gemini et Qdrant, consignes, recherche), contre les Règles d'or 49 et 79. `GeminiService` existe et n'est pas utilisé ici : les appels à Gemini sont recopiés quatre fois.

**D2. Un contrôleur « Admin » sert l'application mobile** : `chat` et `search` sont les routes de l'Assistant des artisans, et `llmMediation` une route des litiges.

**D3. Recherche vectorielle à moitié branchée.** Si `services.qdrant.url` est renseigné, `chat` et `search` interrogent Qdrant ; mais l'approbation d'une fiche n'écrit rien dans Qdrant. Le modèle d'embedding (`text-embedding-004`) est écrit en dur. Une fiche venue de Qdrant sans clé `id` fait échouer `chat` (`$match['id']`).

**D4. Recherche de repli coûteuse et trop large** : `ProductionItem::all()` à chaque message ; une fiche est retenue dès qu'un mot de plus de quatre lettres du message apparaît dans son texte.

**D5. Tables inutilisées ou mal nommées** : `ia_users` (adresse, empreinte de mot de passe, jeton de réinitialisation) n'est lue par aucun code ; `professions`, `categories`, `contexts` ne sont exposées que par trois routes que l'écran n'appelle pas ; `categories` et `attachments` portent des noms génériques sans lien visible avec l'IA. La liste de mots-clés « dans le périmètre » est recopiée à l'identique côté serveur et côté écran.

### E. Écran du backoffice

- Composant unique de 929 lignes, typé `any`, hors du dossier `panels/`, **sans aucun test** (la règle de couverture ne vise que `panels/`).
- Appels par `axios`, sans prise en compte de la session expirée (Chantier 18).
- Le « Simulateur Mobile » n'utilise pas le code de l'application : son mode hors ligne est une recherche locale propre à l'écran, que l'application mobile n'a pas.
- Vocabulaire technique en anglais affiché à l'administrateur (« Staging », « Downscaling », « VLM », « RAG ») ; le manuel d'utilisation ne décrit pas le module.

### F. Tests

Six tests couvrent le chemin nominal (liste, création, approbation, rejet, recherche, téléversement). Rien sur les droits, les extensions de fichier, les doublons, le chat, la médiation ni les réponses de repli.

## Conclusion

Le circuit de validation et le chat fonctionnent, mais **la moitié « ingestion » du module est une maquette** : elle fabrique des fiches au contenu inventé, qu'un administrateur peut publier vers l'Assistant des artisans. Tant que ce n'est pas corrigé, le module ne doit pas servir à alimenter la base de production.

Trois défauts de sécurité sont à traiter sans attendre la refonte : le téléversement sans contrôle (C1), le téléchargement d'une adresse fournie par l'utilisateur (C2) et la médiation sans contrôle de propriété (C3).

## Pistes, par ordre de priorité

1. **Sécurité** : extensions et taille autorisées, disque privé et lien signé pour les documents ; suppression ou liste blanche de `image_url` ; contrôle de propriété, quota et journal sur la médiation.
2. **Fin des réponses inventées** : retirer `generateFallback`, `simulateVlmResult`, le conseil générique du chat et la médiation toute faite ; répondre « aucune fiche » ou « analyse indisponible ».
3. **Ingestion réelle ou retrait de la maquette** : soit le serveur lit le document et fait rédiger la fiche par Gemini (extraction PDF multimodale, fiche marquée « générée par IA, à relire »), soit l'écran ne propose que la saisie manuelle d'une fiche. Retirer les documents de démonstration.
4. **Couche service** : `LlmKnowledgeService` (fiches, validation, audit), `AssistantChatService` (chat, recherche) s'appuyant sur `GeminiService` ; contrôleurs séparés pour le backoffice et pour l'application.
5. **Qdrant** : indexer à l'approbation, ou retirer le branchement.
6. **Ménage et tests** : tables inutilisées, audit de chaque action, libellés français, découpage et tests de l'écran, entrée dans le manuel.
