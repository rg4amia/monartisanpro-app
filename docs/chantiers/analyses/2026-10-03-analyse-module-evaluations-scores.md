# Analyse — Module « Évaluations & Scores »

- **Date** : 2026-10-03
- **Auteur** : Claude (Opus 5.5), à la demande d'Inza Bamba
- **Statut** : analyse seule, aucun code modifié
- **Suite** : constats A4 et A5 traités par `plans/2026-10-03-chantier-24-formule-score.md` ; A1, A7, B1 à B3, C1 à C5, E1 à E3 et E6 par `plans/2026-10-03-chantier-25-corrections-evaluations-scores.md` ; A3 et le pilotage de la dégradation par `plans/2026-10-03-chantier-26-anti-collusion-et-degradation.md` ; les autres décisions de règle restent ouvertes

## Question posée

Comment le Score ProsArtisan est-il réellement calculé, les évaluations sont-elles fiables, et que faut-il corriger ?

## Méthode et sources

- **Serveur** : `app/Services/ScoreService.php` (520 lignes), `app/Http/Controllers/Api/V1/EvaluationController.php` (402 lignes), `app/Http/Requests/Evaluation/CreateEvaluationRequest.php`, `app/Console/Commands/DecayScoreCommand.php`, `routes/console.php`, `app/Services/LitigeService.php` (pénalités de litige), `app/Services/AdminService.php` (listes du backoffice), migrations `evaluations` et `score_ledger_entries`.
- **Backoffice** : `resources/js/pages/admin/panels/EvaluationsPanel.tsx`, `panels/DetailModals.tsx`.
- **Mobile** : `lib/modules/rating/`, `lib/data/repositories/evaluation_repository.dart`.
- **Tests existants** : `CriteriatedEvaluationTest`, `EvaluationComplianceTest`, `EvaluationStatusEndpointsTest`, `LogisticScoreTest`, `MultiEvaluationTest`, `ScoreZeroDefaultTest`, `EvaluationsPanel.test.tsx`.
- **Sonde d'exécution** : deux tests jetables, lancés puis supprimés, et une requête sur la base MariaDB locale. Les constats marqués « vérifié » en sont issus ; les autres reposent sur la lecture du code.

## Comment le score est calculé aujourd'hui

`ScoreService::recalculateFromLedger` additionne deux parts, bornées entre 0 et 1000 :

1. **Part « évaluations »** : moyenne de chaque critère sur 5, convertie en points (Fiabilité 400, Intégrité 300, Qualité 200, Réactivité 100), plafonnée à 800 sans trois critères à 4,8 au moins, puis multipliée par un facteur de maturité (nombre d'évaluations reçues / 10, plafonné à 1).
2. **Part « événements »** : somme des lignes de `score_ledger_entries` (bonus et pénalités), chacune multipliée par son indice de crédibilité.

## Constats

### Ce qui tient

- **Score initial à zéro**, garanti par le modèle, la base et le recalcul (Règle d'or 15).
- **Seuils lus dans la configuration** (crédit 700, marqueur doré 700, excellence 800).
- **Micro-crédit et jury jugés sur le score recalculé**, pas sur la colonne stockée.
- **Évaluation réservée au client de la mission ou de la commande**, une fois celle-ci terminée ou livrée ; auto-évaluation refusée ; doublon refusé par le contrôleur.
- **Note jamais attribuée = « Non évalué »** (`ratingsSummary`).
- **Gel du score audité** et confirmé par une modale.

### A. Calcul du score

**A1. L'indice de crédibilité de l'évaluateur vaut toujours 0,1** (vérifié). `resolveCredibility` compte les missions terminées avec `where('status', CompletedState::class)`, alors que la base stocke `completed`. Le compte est toujours nul : un client aux cinq missions terminées reçoit 0,1 comme un nouveau venu. La branche `client_b2b` vise un rôle qui n'existe pas.

**A2. La crédibilité ne pèse presque rien.** Elle ne s'applique qu'au bonus ou malus de 5 ou 15 points, pas aux moyennes des critères, qui font l'essentiel du score. Un compte client tout neuf qui met 5/5 compte donc pleinement.

**A3. Une seule évaluation suffit à donner 100 points, et dix à donner 1000** (vérifié). Dix évaluations à 5/5 émises par un seul client complice portent un artisan au score maximal : micro-crédit (jusqu'à 500 000 FCFA), marqueur doré, siège de juré. Le facteur de maturité compte des évaluations, pas des missions ni des clients distincts.

**A4. La pire note rapporte des points** (vérifié). Les critères vont de 1 à 5 et sont convertis par `moyenne / 5` : dix évaluations à 1/5 donnent 200 points. Un livreur au score 0 qui reçoit une étoile passe à 19.

**A5. Le plafond d'excellence se contourne par les bonus** (vérifié). Le plafond de 800 ne s'applique qu'à la part « évaluations ». Un artisan plafonné à 800 passe à 900 avec vingt bonus de 5 points, sans remplir la condition des trois critères à 4,8 (Règle d'or 14).

**A6. Fournisseurs et livreurs sont notés avec la formule des artisans** (vérifié). La pondération logistique annoncée (Fiabilité 50 %, Qualité 30 %, Réactivité 20 %, Règle d'or 64) ne sert qu'à décider d'un bonus de 5 points. Le score lui-même utilise les quatre piliers, Intégrité comprise : un fournisseur à 5/5 partout mais 1/5 en intégrité obtient 760.

**A7. Le recalcul prend « la dernière évaluation reçue », pas celle qui vient d'être créée.** Deux évaluations simultanées peuvent attribuer le bonus à la mauvaise.

**A8. Une consultation écrit en base.** `getScoreDetail` et le tableau de bord recalculent et enregistrent le score à chaque lecture (`GET /artisans/{user}/score`).

**A9. Un score gelé perd définitivement le bonus des évaluations reçues pendant le gel** : l'écriture est sautée et n'est pas rattrapée au dégel.

### B. Dégradation d'inactivité (« La Rouille »)

**B1. La commande plante en production** (vérifié sur MariaDB). `getInactivityDays` lit `missions.accepted_at`, colonne qui n'existe pas : MariaDB répond « Unknown column 'accepted_at' ». L'appel est hors du bloc `try` : la commande planifiée chaque jour s'arrête au premier artisan dont le score est positif. Aucun test ne la couvre. La dégradation n'a donc jamais été appliquée.

**B2. Une fois réparée, elle pénaliserait bien plus qu'annoncé** (lecture du code). La règle affichée est « −5 points par semaine ». Or la commande tourne chaque jour et retire à chaque passage `5 × nombre de semaines au-delà de 60 jours`, sans vérifier qu'une pénalité a déjà été appliquée : 35 points par semaine les deux premières semaines, puis 70, puis 105, et ainsi de suite.

**B3. Les points retirés ne reviennent jamais.** Les pénalités s'accumulent dans le ledger sans plancher ; un artisan qui reprend son activité garde cette dette.

### C. Enregistrement des évaluations

**C1. Un client peut noter n'importe quel livreur depuis une mission** (vérifié). Dans `store`, la branche mission accepte tout évalué au rôle livreur, sans lien avec la mission (`elseif ($evalue->isLivreur()) { $isValidRecipient = true; }`). Tout client ayant une mission terminée peut donc noter chaque livreur de la plateforme.

**C2. `GET /evaluations/my` expose le compte entier de l'autre partie** (vérifié). La réponse renvoie les modèles bruts : 33 champs de l'utilisateur, dont le téléphone, l'e-mail, le numéro de paiement, les soldes des portefeuilles, l'empreinte de l'appareil, le jeton de notification et la **position exacte**. Un client obtient ainsi la position non floutée de l'artisan, contre la Règle d'or 6.

**C3. Aucune contrainte d'unicité en base.** Le doublon n'est refusé que par une lecture préalable : deux envois simultanés créent deux évaluations.

**C4. Le message d'exception est renvoyé à l'utilisateur** en cas d'erreur (`'Erreur… : '.$e->getMessage()`, HTTP 500).

**C5. Toute la logique est dans le contrôleur** (402 lignes), contre la Règle d'or 49. Le statut `terminee` y est encore testé alors qu'il n'existe plus.

**C6. Un critère non renseigné prend la valeur de la note globale**, côté application comme côté serveur : les quatre piliers sont souvent quatre copies de la même note.

**C7. Aucune fenêtre de temps** : une mission terminée il y a un an reste notable.

### D. Événements du score

**D1. Trois événements ne sont jamais émis** : jalon dans les temps (+2), retard de jalon (−15), livraison ponctuelle (+5). Les méthodes existent, rien ne les appelle. Rupture de stock et casse de matériel ne sont émises nulle part non plus. La ponctualité, premier pilier annoncé, ne vient donc que des étoiles du client.

**D2. Les pénalités de litige écrivent le ledger hors de `ScoreService`** (`LitigeService`, ligne 630). Un deuxième litige perdu est enregistré sous le libellé « abandon » (−300). Pour un perdant qui n'est pas artisan, la colonne du score est diminuée de 1 directement, écriture effacée au recalcul suivant (Règle d'or 9).

### E. Backoffice

**E1. La recherche du classement renvoie des comptes qui ne sont pas artisans** (vérifié). Dans `paginateArtisanScores`, les conditions `OR` ne sont pas groupées : chercher un identifiant ou un téléphone ramène un client.

**E2. L'historique du score d'un artisan est incomplet sans le dire.** La fiche filtre les 100 dernières lignes du ledger, tous utilisateurs confondus : un artisan absent de ces 100 lignes paraît sans historique (Règle d'or 29).

**E3. Les évaluations de commande s'affichent « Mission # »** sans numéro, sous des colonnes « Évaluateur (Client) » et « Évalué (Artisan) » ; fournisseurs et livreurs n'ont aucun classement.

**E4. Le classement trie sur la colonne stockée**, qui peut être périmée.

**E5. Aucune modération** : un commentaire injurieux ou une évaluation frauduleuse ne peut être ni masqué ni annulé. Le gel ne demande pas de motif.

**E6. La note moyenne affiche 0 quand il n'y a aucune évaluation.**

## Conclusion

Le score repose aujourd'hui presque entièrement sur les étoiles des clients, sans pondération par la crédibilité (A1, A2) et sans les faits mesurés par la plateforme (D1). Il se manipule facilement (A3, C1), alors qu'il ouvre le micro-crédit, le marqueur doré et le jury. La dégradation d'inactivité ne fonctionne pas (B1). Une fuite de données personnelles est à corriger sans attendre (C2).

## Pistes, par ordre de priorité

1. **Sans décision à prendre** : fuite de `GET /evaluations/my` (C2), livreur sans lien (C1), crédibilité (A1), commande de dégradation (B1 à B3), unicité (C3), recherche du classement (E1), historique complet (E2), message d'exception (C4), couche service (C5).
2. **Décisions de règle** (voir le compte rendu) : poids de la crédibilité, anti-collusion, plancher des notes, formule logistique, plafond d'excellence, émission des événements de ponctualité, modération.
