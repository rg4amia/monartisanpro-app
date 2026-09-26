# Chantier 13 — Machine à États Formelle & Guards d'Intégrité des Missions

> **Date** : 2026-09-26  
> **Statut** : livré (`faf6fd8f`, `141b0546`)  
> **Auteur** : Codex / Antigravity  
> **Contexte** : Option 2 approuvée par l'utilisateur (« Option 1 puis option 2 »)  
> **Dépendances** : Spatie ModelStates, MariaDB 11.8 / MySQL 8.4 / SQLite, Règles d'Or 4, 5, 12, 27.

---

## 1. Contexte & Objectifs

Le cycle de vie d'une mission sur ProsArtisan (`missions`) implique des enjeux financiers considérables (séquestre, division des portefeuilles Main d'Œuvre et Matériaux, codes J-Code, décaissements par jalon avec seuil Référent de 2 000 000 FCFA).

Bien que Spatie ModelStates ait posé les classes d'états dans `app/States/Mission/`, il manquait :
1. **Un journal d'audit formel des transitions (`mission_state_transitions`)** enregistrant chaque changement d'état avec l'acteur responsable, le motif, l'horodatage et les métadonnées techniques.
2. **Des Guards d'intégrité métier stricts** empêchant toute transition invalide au niveau du modèle :
   - Interdiction de transition vers `funded_locked` sans acceptation de devis et transaction d'acompte confirmée.
   - Interdiction formelle de clôturer (`completed`) une mission si des jalons restent impayés/non validés.
   - Interdiction formelle de clôturer une mission > 2 000 000 FCFA sans validation physique préalable par le Référent de zone (Règle d'Or n° 5).
   - Verrouillage automatique des fonds (`funds_frozen`) et des actions en cas de transition vers `disputed`.
3. **Une API d'historique du cycle de vie** : `GET /api/v1/missions/{mission}/state-history`.

---

## 2. Architecture & Composants

### 2.1 Schéma de Données (`mission_state_transitions`)
Table d'audit immuable :
- `id` (bigint unsigned auto-increment)
- `mission_id` (foreign key missions, cascade)
- `from_state` (string 50)
- `to_state` (string 50)
- `user_id` (foreign key users, null on delete, acteur déclencheur)
- `reason` (string 255, optionnel)
- `metadata_json` (json, IP, user-agent, contexte)
- `created_at` (timestamp)

### 2.2 Classes de Transition Spatie Dédiées
- `ToFundedLockedTransition` (Guard financement séquestre)
- `ToInProgressTransition` (Guard démarrage chantier)
- `ToCompletedTransition` (Guard vérification jalons & seuil Référent > 2M FCFA)
- `ToDisputedTransition` (Guard gel des fonds et archivage litige)

### 2.3 Listener / Hook d'Audit Trail
Écoute automatique de l'événement de transition d'état Spatie `StateChanged` pour consigner sans faille toutes les transitions dans `mission_state_transitions`.

---

## 3. Plan de Réalisation

1. **Migration & Modèle** :
   - Migration `2026_09_26_170000_create_mission_state_transitions_table.php`.
   - Modèle `MissionStateTransition`.
   - Relation `stateTransitions()` sur `Mission`.
2. **Guards & Transitions Spatie** :
   - Implémentation des classes de transition personnalisées dans `app/States/Mission/Transitions/`.
   - Enregistrement des transitions avec guards dans `MissionState::config()`.
3. **Audit Trail Automatisé** :
   - Listener `RecordMissionStateTransition` sur l'événement Spatie `StateChanged`.
4. **Contrôleur API** :
   - Endpoint `GET /api/v1/missions/{mission}/state-history`.
5. **Tests & Validation** :
   - Test unitaire/fonctionnel complet `tests/Feature/Chantier13MissionFsmGuardsTest.php`.
