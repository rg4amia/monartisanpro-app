# Document de Spécifications Fonctionnelles (PRD) — ProsArtisan

## 1. Vision & Objectifs
**ProsArtisan** est une plateforme marketplace mettant en relation :
* Des **Clients** ayant des besoins de travaux (diagnostic et chantiers).
* Des **Artisans** qualifiés et certifiés.
* Des **Fournisseurs (Quincailleries)** pour la fourniture des matériaux.
* Des **Livreurs (Drivers)** pour le transport de marchandises et matériaux.
* Des **Référents de zone** pour l'arbitrage et le contrôle des chantiers d'envergure.

---

## 2. Parcours Utilisateur de A à Z : Analyse & Retours d'Expérience

### 🎬 Phase 0 : Inscription, Authentification & KYC
#### Workflow — Phase 0
1. L'utilisateur télécharge l'application et s'inscrit en fournissant son numéro de téléphone (`+225` + 10 chiffres).
2. Il accepte obligatoirement les CGU et la Politique de Confidentialité (horodatage persistant en base de données).
3. Un code OTP à 4 chiffres lui est envoyé par SMS ou WhatsApp.
4. Il sélectionne son rôle : `client`, `artisan`, `fournisseur`, ou `driver` (livreur).
5. Pour pouvoir effectuer la moindre transaction, il doit soumettre son KYC (CNI + selfie).
6. L'administrateur valide ou rejette le dossier $\rightarrow$ `kyc_status = actif`.

#### 🔍 Retours Observés — Phase 0
* **Points Forts :**
  * Sécurité irréprochable : l'obligation du statut KYC actif bloque l'accès aux opérations financières de manière étanche.
  * Flexibilité du canal OTP (SMS/WhatsApp).
* **Points Faibles :**
  * Friction élevée à l'onboarding.

---

### 🔍 Phase 1 : Diagnostic & Matching Géospatial
#### Workflow — Phase 1
1. Le client décrit son problème. L'API **Gemini** analyse la demande, classe la catégorie, évalue l'urgence, et propose une estimation de prix.
2. Le système recherche les artisans actifs dans un rayon $\le$ 2 km à l'aide de requêtes spatiales MySQL (`ST_Distance_Sphere`).
3. La position GPS exacte de l'artisan est floutée d'environ 50 mètres pour préserver sa vie privée.
4. Tri par **Score de Réputation ProsArtisan** (enregistré sous la colonne `score_prosartisan` en BDD).

#### 🔍 Retours Observés — Phase 1
* **Points Forts :**
  * Floutage efficace et diagnostic intelligent.
* **Points Faibles :**
  * Rayon fixe de 2 km trop restrictif en dehors des zones urbaines denses.

---

### 💵 Phase 2 : Devis & Séquestre (Escrow)
#### Workflow — Phase 2
1. L'artisan formule une proposition de devis (lignes matériaux, lignes MO, jalons).
2. Le client accepte le devis et paie l'acompte total via Wave ou Orange Money.
3. Les fonds sont fragmentés et bloqués :
   * `wallet_materiaux` : Réservé exclusivement à la quincaillerie fournisseur.
   * `wallet_mo` : Débloqué jalon par jalon pour l'artisan.
4. Le ratio matériaux/MO est figé à l'acceptation et devient **strictement immuable**.

---

### 🚚 Flux Logistique E-commerce & Livreur (Livreurs / Drivers)
Le système gère également un flux de livraison de matériaux en 3 étapes :
#### Workflow — Logistique
1. **Recherche de Livreur (Radar de courses) :**
   * Dès que le fournisseur marque la commande comme prête (`status = prepared`), le statut passe à `searching_driver`.
   * Les livreurs dans un rayon spatial de 10 km reçoivent une notification. Si aucune réponse n'est obtenue locale, le radar est étendu à toutes les zones.
2. **Acceptation de la course par le Livreur :**
   * Le livreur accepte la course via son application.
   * Le système calcule dynamiquement le prix de livraison selon une formule combinant la distance Google Maps et le temps estimé :
     $$\text{Frais} = ((\text{Distance (km)} \times 150) + (\text{Durée (min)} \times 50)) \times \text{Multiplicateur Véhicule} \times \text{Multiplicateur Surge}$$
     *(Minimum forfaitaire de 1000 FCFA).*
   * La part de livraison payée par le client est consignée en séquestre (`escrow_order_` + ID).
   * L'état passe à `driver_assigned`. Le livreur reçoit l'adresse de la boutique et le code de retrait (`pickup_code`).
3. **Récupération chez le Fournisseur (Verify Pickup) :**
   * Le livreur se rend chez le fournisseur et présente le `pickup_code`.
   * Le fournisseur valide le code. Les fonds des matériaux sont immédiatement transférés du séquestre vers le portefeuille réel du fournisseur.
   * L'état de la commande passe à `driver_picked_up`. Le livreur reçoit l'adresse de livraison du client et le client reçoit le code secret de livraison (`reception_code`).
4. **Remise au Client final (Verify Delivery) :**
   * Le livreur remet les colis au client.
   * Le client fournit son `reception_code` secret au livreur.
   * Le livreur saisit le code dans l'application. Les frais de livraison sont alors libérés du séquestre vers le portefeuille réel du livreur.
   * L'état passe à `delivered`.

#### 🔍 Retours Observés — Logistique
* **Points Forts :**
  * Calcul de tarification dynamique intelligent (ajusté selon la classe de véhicule : moto, voiture, cargo).
  * Double contrôle à double clé (`pickup_code` pour le fournisseur et `reception_code` pour le livreur) évitant tout détournement de marchandise ou fraude à la livraison.
* **Points Faibles / Risques :**
  * Pas de gestion automatisée de réaffectation de livreur si celui-ci a un contretemps en chemin après acceptation.

---

### 📦 Phase 3 : Achat des Matériaux & Anti-Fraude J-Code
Note : Pour les prestations chantiers de l'Artisan.
* L'artisan génère un **J-Code** unique (`PA-XXXX` + QR Code + code USSD).
* Le fournisseur scanne le code. **Vérification GPS obligatoire :** la distance entre la boutique et le scan doit être $< 100$ mètres.
* Les fonds du `wallet_materiaux` de l'artisan sont transférés au fournisseur.

---

### 🏗️ Phase 4 : Exécution des Jalons & Validation OTP
* L'artisan soumet les jalons terminés avec photos géolocalisées.
* Analyse de vision (Gemini Vision) obligatoire.
* Le client valide par OTP $\rightarrow$ libération de la MO.
* Si le montant de la mission $> 2 000 000$ FCFA $\rightarrow$ passage physique obligatoire d'un Référent de zone avant paiement.

---

### ⭐️ Phase 5 : Clôture & Score ProsArtisan
* Le client évalue la mission et peut noter distinctement l'ensemble des acteurs intervenus :
  * **L'Artisan** — 4 piliers pondérés sur l'échelle 0–1000 : Fiabilité **400 pts**, Intégrité **300 pts**, Qualité **200 pts**, Réactivité **100 pts** (équivalents aux poids relatifs 40 % / 30 % / 20 % / 10 %).
  * **Le Livreur** (Ponctualité, Intégrité, Soin du colis, Courtoisie).
  * **Le Fournisseur / Quincaillerie** (Disponibilité, Transparence, Qualité, Rapidité).
* Le **Score ProsArtisan** est mis à jour dans la colonne de base de données **`score_prosartisan`** (échelle 0 à 1000) et journalisé dans `score_ledger_entries`.
* **Seuils (configurables dans `config/prosartisan.php` → `score_prosartisan`) :**
  * `credit_threshold = 700` — score $\ge 700$ requis pour l'éligibilité au micro-crédit d'urgence ; plafond de crédit = $50\,000 + (\text{score} - 700) \times 1\,500$ FCFA (soit 500 000 FCFA à 1000).
  * `golden_marker_threshold = 700` — badge « marqueur doré » (artisan prioritaire dans le matching et la vitrine).
  * `excellence_threshold = 800` — palier d'excellence, conditionné à la maturité (10 missions) et à ≥ 3 critères $\ge 4{,}8/5$.
* L'ancienne échelle 0–100 et l'ancien seuil `70` (période « Score N'Zassa ») sont **obsolètes** et ne doivent plus apparaître ni en code ni en documentation.

---

## 3. Matrice de Synthèse : Forces vs Faiblesses

| Étape du flux | Points Forts (Forces) | Points Faibles (Faiblesses) |
| --- | --- | --- |
| **Onboarding & KYC** | • Blocage KYC actif robuste. OTP multi-canal. | • Forte friction d'entrée. |
| **Matching & Géo** | • Floutage GPS artisan. Qualification intelligente pannes. | • Rayon de 2km trop rigide. |
| **Séquestre** | • Ratios fixes et immuables. | • Pas de modification possible du devis. |
| **Livreurs & Logistique** | • Radar étendu automatique. Formule dynamique Maps. Double code de sécurité (Pickup/Reception). | • Pas de réaffectation automatique du livreur. |
| **J-Code & Anti-Fraude** | • Clôture GPS boutique < 100m. | • Signal GPS en intérieur capricieux. |
| **Jalons & Libération** | • Validation progressive OTP. Contrôle physique > 2M FCFA. | • Risque de blocage client injoignable. |
| **Score ProsArtisan** | • Échelle 0–1000, seuils configurables. Facteur clé d'accès au micro-crédit (≥ 700). | • Colonne `score_prosartisan` en BDD. |
| **Backoffice admin** | • Permissions fines + super admin protégé. Journal d'audit immuable. Throttle login. Pagination serveur + cache KPI. Exports CSV tracés. RGPD (accès/portabilité/anonymisation). Observabilité + alerte Telegram. Usurpation de session encadrée. | • Espace web limité aux rôles `admin` (et `fournisseur`/`livreur` via API) : l'usurpation d'un `client`/`artisan` a une portée réduite. |

---

## 4. Spécifications Techniques & Règles Métier Critiques

### Règles d'Or (Immuables)
1. **KYC Actif Obligatoire :** `kyc_status = 'actif'` requis.
2. **Immuabilité du Ratio :** Ratio de fragmentation figé dès l'acceptation.
3. **Géorepérage J-Code :** Distance scan-boutique $> 100\text{m}$ $\rightarrow$ Blocage automatique.
4. **Validation de Livraison par Codes :** Aucun transfert de fonds logistique ou matériel sans validation des codes respectifs (`pickup_code` et `reception_code`).
5. **Score ProsArtisan (`score_prosartisan`, échelle 0–1000) :** Archivage complet dans un Ledger d'événements (`score_ledger_entries`) pour audits bancaires et micro-crédits. Tous les seuils métier (`credit_threshold` 700, `golden_marker_threshold` 700, `excellence_threshold` 800) sont lus depuis `config/prosartisan.php` côté backend et depuis `kMicroCreditScoreThreshold` côté mobile — **jamais** une valeur en dur ni l'ancienne échelle 0–100 / seuil `70`.
6. **FCFA Entier :** Toutes les colonnes financières en `BIGINT`.
7. **Autorité Financière du Serveur & Alignement de Paiement :** Le serveur fait autorité sur le montant total du devis (`montant_total`). L'initiation de paiement ajuste automatiquement tout écart de commission ou d'arrondi client sans bloquer la transaction.
8. **Redirection Négociée par Deep Link Android (`intent://`) :** Lors de la validation de paiement, le navigateur web exécute la fragmentation du séquestre et déclenche un Intent Android `intent://payment-result...#Intent;scheme=prosartisan;package=com.prosartisan.app;end` pour ramener l'utilisateur sur l’application mobile.
9. **Machine d'état des Jalons & Preuves Multiples :** La soumission de photos/vidéos par l'artisan fait passer le jalon de `en_attente` à `soumis`. Une fois soumis, l'artisan peut continuer à ajouter autant de preuves (images ou vidéos, max 25MB chacune) que souhaité. Le client peut directement consulter la galerie de preuves et décider de les accepter (validation directe sans OTP) ou de valider classiquement via OTP. Dès que le jalon est validé/payé, toute soumission/upload de preuve supplémentaire est bloquée (HTTP 400).
10. **Suivi 360° & Contrôle Unifié des Livraisons en Backoffice :** Le module Missions du Backoffice offre un suivi temps réel croisé des livreurs, quincailleries, artisans et clients avec traçabilité complète des codes de sécurité (`pickup_code`/`reception_code`) et preuves photo.
11. **Gestion Dynamique des Rôles & Permissions (RBAC) :** Le système de permissions et d'actions (`permissions` et `permission_role`) couvre l'ensemble des 6 rôles du système (`client`, `artisan`, `fournisseur`, `referent`, `livreur`, `admin`). Toutes les actions d'attribution/révocation depuis le Backoffice React/Inertia gèrent obligatoirement l'en-tête `X-Inertia` (`back()`) pour éviter les ruptures de protocole HTTP.
12. **Module d'Itinéraire Routier Réel (OSRM) :** Le planificateur de trajet livreur calcule le tracé routier réel sur la carte Yandex Maps via OSRM (`router.project-osrm.org`), affichant la trajectoire précise des voies de circulation ainsi que les métriques de distance ($km$) et d'estimation de temps (ETA $min$).
13. **Validation Souple des Codes Logistiques Backend :** Les méthodes `verifyPickup` et `verifyDelivery` de `OrderService` acceptent le code complet généré (`LIVREUR-XXXX`, `RECEPTION-XXXX`) ainsi que les formats raccourcis/tests (`RET-16`, `REC-16`, etc.) et suffixes numériques, garantissant le passage effectif à l'état `delivered` et le nettoyage automatique des livraisons actives sur l'espace livreur.
14. **Visualisation Interactive du Sinistre (Photos/Vidéos) :** L'artisan dispose d'une galerie interactive défilante sur l'écran d'édition de devis et l'écran de suivi de chantier pour examiner les pièces fournies par le client lors de sa demande. Le visualiseur offre un zoom interactif (pinch-to-zoom) sur les images et une ouverture/lecture vidéo native.
15. **Sécurisation Multi-niveaux du Traitement des Devis :** L'acceptation du devis requiert obligatoirement une transaction d'acompte confirmée de même montant et explicitement liée à ce devis. Toute modification ou validation de devis est soumise aux vérifications strictes de rôle (exclusivité client), d'état (devis au statut `soumis`) et de sécurité.
16. **Gating de Protection des Coordonnées Client (RGPD & Sécurité) :** Masquer les coordonnées GPS exactes, l'adresse textuelle et le numéro de téléphone du client tant que la mission n'a pas été acceptée et payée. Dès que le devis est validé et financé (statut `financee`/`funded_locked` ou ultérieur), ces coordonnées sont dévoilées via l'API et déverrouillées sur le mobile de l'artisan.
17. **Audit Trailing & Preuves d'Interactions en Backoffice :** Le backoffice d'administration intègre un historique global complet de toutes les notifications, SMS, alertes et OTP expédiés par le système pour servir de preuve d'interaction et de journal d'audit en cas de litige, avec recherche, filtres par rôle/type et inspection des métadonnées JSON techniques.
18. **Consommation Partielle de J-Code & Suivi par Item :** Le J-Code supporte les débits partiels auprès de plusieurs fournisseurs agréés. Le statut global du J-Code passe de `actif` à `partiellement_utilise` puis `utilise` une fois le montant total consommé. Le statut individuel des articles requis passe par `requested` $\rightarrow$ `partial` $\rightarrow$ `served` avec traçabilité complète du fournisseur ayant servi chaque article et décrémentation automatique des stocks.
19. **Évaluation Multi-Acteurs & Scoring Différencié :** Le client a la possibilité d'évaluer distinctement l'ensemble des intervenants ayant contribué à la réussite de son projet ou de sa commande : l'Artisan (travaux), le Livreur (transport & état du colis), et le Fournisseur (quincaillerie ayant fourni le matériel), avec critères d'évaluation adaptés et traçabilité dans le grand livre `score_ledger_entries`.
20. **Gestion du Consentement des Cookies sur le Front Office Web (RGPD / Loi CI n° 2013-450) :** Lors de l'accès au front office web (vitrine Next.js & interface web), un bandeau interactif de recueil du consentement des cookies est présenté à l'utilisateur. Il permet d'accepter globalement, de refuser les cookies non essentiels ou de personnaliser ses préférences (Cookies techniques/essentiels, analytiques, préférences de navigation). Les choix sont persistés localement et modifiables à tout moment via le lien "Gestion des cookies" du footer.
21. **Acceptation Obligatoire des CGU & Politique de Confidentialité :** À la première inscription sur l'application mobile ou sur le web, la validation explicite des Conditions Générales d'Utilisation et de la Politique de Confidentialité est enregistrée avec horodatage en base de données. Ces documents restent consultables à tout moment dans chaque espace applicatif et sur le footer du site web.
22. **Historique des Paiements Reçus & Rapprochement Mobile Money :** L'espace artisan offre un accès One-Tap pour consulter l'historique complet et exhaustif de tous les paiements reçus (jalons libérés, acomptes, micro-crédits, remboursements). Chaque entrée trace l'opérateur (Wave, Orange Money, MTN MoMo, Moov Money), le montant en FCFA entier, le statut de libération, le numéro et intitulé de la mission, ainsi que le nom du client.
23. **Numéro Mobile Money Multi-Acteurs (Client & Prestataires) :** Tous les utilisateurs de la plateforme (Clients, Artisans, Livreurs, Fournisseurs) peuvent renseigner et mettre à jour leur numéro de téléphone Mobile Money (`payment_phone`) et leur opérateur préféré (`preferred_payment_provider`: Wave, Orange Money, MTN MoMo, Moov Money). Dans l'espace client, ce numéro permet le règlement rapide des devis et la réception automatique des remboursements en cas de litige. Pour les prestataires, ces coordonnées font autorité pour le virement automatisé des gains et fonds débloqués.
24. **Permissions Fines & Super Admin Protégé (Backoffice) :** L'accès aux sections et actions de l'administration (`/admin/*`) est régi, au-delà du rôle `admin`, par des capacités fines `admin.<x>` (table pivot `admin_permission_user`, middleware `can:` + Gates). Un administrateur sans capacité affectée, ou porteur de `admin.full-access`, dispose de l'accès total. Les super administrateurs protégés (`config('prosartisan.super_admins')`, env `SUPER_ADMIN_EMAILS`, défaut `admin@prosartisan.ci`) disposent d'un accès total **inconditionnel** qui ne peut jamais être restreint depuis l'interface ni par API — garde-fou anti-verrouillage, avec commande de secours `php artisan admin:full-access`.
25. **Journal d'Audit Immuable des Actions Sensibles (Backoffice) :** Toute action sensible du backoffice (revue KYC unitaire et groupée, arbitrage de litige, revue fournisseur/CNMCI, gel de score, cycle de vie des comptes, modification des droits admin, paramètres/IA/taxonomie/codes promo, exports CSV, anonymisation RGPD, usurpation de session, connexions administrateur) est journalisée en append-only dans `admin_activity_logs` (acteur, IP, user-agent, sujet, contexte JSON, horodatage). L'écriture est *best-effort* et ne bloque jamais l'action métier.
26. **Anti-Force Brute sur l'Authentification Admin :** Les points d'entrée `/admin/login` et `/admin/login/verify-2fa` sont plafonnés à 5 tentatives par tranche de 60 secondes et par (identifiant + adresse IP) → HTTP 429. Chaque échec (mot de passe, rôle refusé, 2FA invalide) est audité ; le compteur se réinitialise à la connexion réussie.
27. **Pagination Serveur & Agrégats Indépendants (Backoffice) :** Les grandes listes de l'administration (utilisateurs, transactions, missions, litiges, évaluations, KYC, journal d'audit) chargent une page à la fois via rechargement partiel Inertia ; les métriques KPI sont calculées indépendamment de la page affichée et mises en cache (TTL court) avec invalidation automatique sur mouvement financier.
28. **Exports CSV Conformes & Tracés (Backoffice) :** Les exports (`users`, `transactions`, `missions`, `evaluations`, `litiges`) sont produits en streaming synchrone avec BOM UTF-8 et séparateur `;` (compatibilité Excel FR), respectent les filtres de la liste et les scopes Eloquent (soft-delete), et chaque génération est auditée.
29. **Actions Groupées Sécurisées (Backoffice) :** La revue KYC et le changement de statut de compte peuvent s'appliquer par lot (max 100). L'administrateur qui déclenche l'action ne peut jamais être affecté par son propre lot ; un élément en échec n'interrompt pas le traitement ; une ligne d'audit récapitulative est produite en plus des lignes individuelles.
30. **RGPD — Droit d'Accès, Portabilité & Effacement (Backoffice) :** L'administration expose la vue consolidée des données personnelles d'un utilisateur (identité, KYC, position, horodatage d'acceptation des CGU, empreinte sur la plateforme, traçabilité), un export JSON de portabilité, et une **anonymisation irréversible et tracée** (`anonymized_at`, `anonymized_by`) qui expurge toutes les données identifiantes, supprime les pièces KYC et notifications, révoque les jetons et suspend le compte — **sans supprimer la ligne `users`**, afin de préserver l'intégrité des écritures financières et du journal d'audit. Refus de l'auto-cible et du second passage.
31. **Observabilité & Alerte Telegram (Backoffice) :** Un panneau de santé (`/admin/observability`) agrège quatre signaux critiques : files d'attente en échec (`failed_jobs`), webhooks de paiement KO (transactions `echoue`), tentatives de fraude GPS J-Code (`score_ledger_entries.event_type = 'fraude_gps_tentative'`), missions bloquées au seuil Référent. Une tâche planifiée `admin:health-check` (toutes les 15 minutes) émet une alerte Telegram dès qu'un signal est non nul.
32. **Usurpation de Session Encadrée (Super Admin) :** Le super administrateur peut « se connecter en tant que » un utilisateur **non-admin** ; l'identité d'origine est conservée en session, un bandeau permanent permet le retour (`/admin/stop-impersonating`, accessible au compte usurpé). L'usurpation d'un autre administrateur, de soi-même ou d'un compte anonymisé/supprimé est interdite ; le début et la fin sont journalisés.

### Formule mathématique du Score ProsArtisan
Le score d'un artisan $S(t)$ est calculé sur une échelle de 0 à 1000 :
$$S(t) = \min\left(1000, \max\left(0, S_{base} + \sum_{k} (\omega_k \cdot E_k \cdot C_k) - \Delta(t)\right)\right)$$
* $S_{base}$ : Score de départ (0 par défaut pour tout nouvel artisan non évalué).
* $E_k$ : Valeur de l'événement $k$ (ex: $+10$ pour jalon à l'heure, $-300$ pour abandon de chantier).
* $\omega_k$ : Coefficient de pondération selon la catégorie (Fiabilité, Intégrité, Qualité, Réactivité).
* $C_k$ : Facteur de crédibilité du client évaluateur (0.1 pour un nouveau client à 1.5 pour un partenaire B2B).
* $\Delta(t)$ : Pénalité d'inactivité ("La Rouille") s'appliquant après 60 jours sans chantier.

---

### 🖥️ Suivi 360° & Contrôle Backoffice (Module Missions & Livraisons)
Le module **Missions** du Backoffice administrateur (`/admin/missions`) intègre une gestion unifiée à deux sous-vues :
1. **🔨 Chantiers & Missions Artisans** : Suivi des chantiers, diagnostics IA Gemini, devis, jalons, séquestres (35% MO / 65% Matériaux), audits Référent (> 2M FCFA) et litiges.
2. **🛵 Livraisons Matériaux & Courses Livreurs** : Suivi tous azimuts de tous les livreurs et artisans :
   * Metrics KPI en temps réel (Total livraisons, En transit, En attente coursier, Livrées & Réceptionnées).
   * Filtres dynamiques par statut (`paid`, `prepared`, `searching_driver`, `driver_assigned`, `driver_picked_up`, `shipping`, `delivered`, `disputed`).
   * Modale **Suivi 360°** : Timeline 4 étapes, fiches des 3 acteurs (Livreur, Artisan/Client destinataire, Quincaillerie expéditrice) avec boutons d'appel direct `tel:`, grille des matériaux commandés, ventilation des frais et preuves photographiques.
   * Liaison croisée dans le détail d'une mission de chantier pour consulter instantanément les courses associées.

---

### 🛡️ Durcissement du Backoffice Administrateur (Chantiers C1–C7)

Le backoffice (Laravel 12 + Inertia 2 + React 19 + TypeScript) a fait l'objet d'un plan de fiabilisation en sept chantiers, tous **[COMPLÉTÉS]** :

| Chantier | Contenu |
| --- | --- |
| **C1 — Architecture** | Suppression du god-method `renderPage` ; chaque onglet reçoit sa seule tranche de données via `AdminPanelData` ; logique métier déplacée en Service Layer + Form Requests. |
| **C2 — Découpe front** | `console.tsx` scindé : `shared/AdminShell.tsx` (chrome) + un panneau par onglet dans `panels/` + hooks (`useServerTable`, `useRowSelection`). |
| **C3 — Traçabilité & anti-abus** | Journal d'audit append-only `admin_activity_logs` (Règle d'Or 25) ; throttle de connexion admin (Règle d'Or 26). |
| **C4 — Performance** | Pagination serveur des grandes listes via rechargements partiels Inertia + cache des KPI du dashboard invalidé par observers (Règle d'Or 27). |
| **C5 — Opérations de masse** | Exports CSV conformes et tracés (Règle d'Or 28) ; actions groupées KYC & statut de compte (Règle d'Or 29). |
| **C6 — Gouvernance des accès & RGPD** | Permissions fines `admin.*` + super admin protégé (Règle d'Or 24) ; vue des données personnelles, portabilité et anonymisation tracée (Règle d'Or 30) ; affichage de l'horodatage d'acceptation des CGU par compte. |
| **C7 — Observabilité, UX & tests** | Panneau de santé + alerte Telegram (Règle d'Or 31) ; confirmations destructives normalisées et accessibles (`role="dialog"`, focus, Échap), persistance des filtres en `localStorage`, fil d'Ariane, squelettes de chargement ; suite de tests de composants front (Vitest + Testing Library, `npm test`). |

**Ajouts hors-plan :** accès total permanent garanti au super administrateur (Règle d'Or 24) et fonctionnalité d'usurpation de session encadrée (Règle d'Or 32).

**Capacités fines du backoffice** (extrait, `AdminPermissionService::catalog()`) : `admin.kyc.view|review`, `admin.missions.view`, `admin.litiges.view|arbitrate`, `admin.users.view|manage|delete|impersonate`, `admin.transactions.view`, `admin.exports`, `admin.evaluations.view`, `admin.fournisseurs.review`, `admin.settings.manage`, `admin.taxonomy.manage`, `admin.roles.manage`, `admin.audit.view`, `admin.observability.view|manage`, `admin.communications.manage`, `admin.notifications.view`, `admin.vitrine.manage`, `admin.promo.manage`, `admin.ai.manage`, `admin.llm.manage`, `admin.rgpd.view|manage` + sentinelle `admin.full-access`.

**Variables d'environnement associées :** `SUPER_ADMIN_EMAILS` (défaut `admin@prosartisan.ci`), `TELEGRAM_BOT_TOKEN`, `TELEGRAM_ALERT_CHAT_ID`.

---

## 5. Liste des Besoins Produits Prioritaires (Backlog)

### 🚚 Logistique & Livreurs
1. **Réaffectation Automatique de Livreur (Driver Fallback) :** Si un livreur accepte une course mais reste immobile pendant plus de 15 minutes ou s'éloigne du fournisseur, la course doit lui être retirée automatiquement et remise dans le radar.
2. **Consommation Partielle du J-Code :** [COMPLÉTÉ] Permettre à l'artisan d'utiliser son J-Code chez plusieurs fournisseurs agréés si le premier n'a pas la totalité du stock disponible (débit partiel du séquestre matériel), avec interface mobile de sélection de quantité et traçabilité par item.
3. **Mode Hors-Ligne pour les Livreurs :** [COMPLÉTÉ] Permettre au livreur de valider la récupération (prise en charge) ou la livraison via des requêtes USSD interactives ou instantanées (`*555*RET-123#`), ou par SMS crypté (ex: `RET-123`), dans les zones blanches à faible connectivité internet.

### 🛡️ Anti-Fraude, Sécurité & Finance (Ledger)
1. **Ledger Financier Immuable (Double-Entry Ledger) :** [COMPLÉTÉ] L'autorité absolue des soldes de portefeuilles (`wallet_materiaux` and `wallet_mo`) a été inversée au profit du ledger immuable `wallet_transactions`. Les balances sont calculées dynamiquement par somme des crédits et débits de ledger, et toute modification directe hors-ledger des colonnes de la table `users` en base de données est interceptée/ignorée.
2. **Circuit Breaker sur les APIs de Paiement :** Si Wave CI ou Orange Money CI subit une panne, le système doit basculer en mode dégradé, suspendre l'initiation de nouveaux paiements mobiles et afficher un message clair à l'utilisateur.
3. **Device Fingerprinting (Empreinte Appareil) :** Lier le compte artisan/client à l'identifiant matériel unique du téléphone (Device UUID) pour empêcher les artisans bannis de recréer instantanément un compte sur le même appareil.
4. **Configuration Reversement Mobile Money & Historique des Paiements Reçus :** [COMPLÉTÉ] Configuration dans le profil utilisateur du numéro Mobile Money dédié aux virements sortants (`payment_phone`, `preferred_payment_provider`) pour artisans, livreurs et quincailleries, et consultation One-Tap de l'historique complet des gains et déblocages dans l'espace artisan.

### 🏗️ Gestion de Chantier & Jalons
1. **Bypass de Sécurité / Auto-Release 72h :** Si un jalon soumis reste sans réponse pendant 72h, il est validé automatiquement par le système, libérant ainsi les fonds de main-d'œuvre pour protéger la trésorerie de l'artisan.
2. **Ajustements de Devis en cours de Mission (Avenants) :** [COMPLÉTÉ] Permettre la création d'avenants au devis initial (matériel/main d'œuvre supplémentaire imprévu) validés par le client, réajustant le séquestre de façon incrémentale et insérant les jalons sans perturber le cycle de vie de la mission.

### ⭐️ Système de Réputation (Score ProsArtisan)
1. **Indice de Crédibilité de l'Évaluateur ($C_k$) :** [COMPLÉTÉ] Pondérer la note laissée à l'artisan selon le profil du client (0.1 pour un nouveau client, 1.0 pour un client vérifié récurrent, 1.5 pour un client B2B) afin d'éviter le dénigrement ou les faux avis.
2. **Dégradation Temporelle ("La Rouille" $\Delta(t)$) :** [COMPLÉTÉ] Diminuer progressivement le Score ProsArtisan si l'artisan reste inactif pendant plus de 60 jours afin de valoriser les profils actifs.
3. **Évaluation Multi-Acteurs & Notation Directe Mobile :** [COMPLÉTÉ] Évaluation indépendante de l'artisan, du livreur et de la quincaillerie sur mission terminée ou commande livrée. Accès direct via bouton d'action doré « ⭐ Noter l'artisan » sur les cartes de missions terminées dans l'application mobile, notation par sous-critères (Fiabilité, Intégrité, Qualité, Réactivité), recalcul instantané du score et journalisation d'audit dans `score_ledger_entries`.
4. **Maturité d'Expérience (10 Missions) & Règle d'Excellence :** [COMPLÉTÉ] Le Score ProsArtisan (0 à 1000) applique un coefficient de maturité $F_{\text{volume}} = \min(1.0, \frac{n}{10})$ afin d'éviter qu'un artisan atteigne 1000 points en seulement 1 ou 2 missions. L'accès aux scores d'excellence (> 800 et jusqu'à 1000) requiert impérativement un minimum de 10 missions terminées et 5 étoiles ($\ge 4.8/5$) sur au moins 3 critères distincts.

### 🛡️ Administration & Backoffice
1. **Journal d'Audit Immuable :** [COMPLÉTÉ] `admin_activity_logs` append-only pour toutes les actions sensibles (Règle d'Or 25).
2. **Anti-Force Brute sur la Connexion Admin :** [COMPLÉTÉ] Throttle 5 tentatives / 60 s par identifiant + IP, échecs audités (Règle d'Or 26).
3. **Pagination Serveur & Cache des KPI :** [COMPLÉTÉ] Rechargements partiels Inertia + agrégats indépendants de la page + cache invalidé par observers (Règle d'Or 27).
4. **Exports CSV & Actions Groupées :** [COMPLÉTÉ] Exports tracés (Règle d'Or 28) et revue KYC / statut de compte en lot (Règle d'Or 29).
5. **Permissions Fines & Super Admin Protégé :** [COMPLÉTÉ] Capacités `admin.*`, table pivot, Gates, super admins inconditionnels (Règle d'Or 24).
6. **Panneau de Santé & Alerte Telegram :** [COMPLÉTÉ] Jobs KO, webhooks paiement, fraude GPS, seuil Référent + tâche `admin:health-check` (Règle d'Or 31).
7. **Usurpation de Session :** [COMPLÉTÉ] « Se connecter en tant que » un utilisateur non-admin avec bandeau de retour et audit (Règle d'Or 32). *À étendre :* variante « jeton Sanctum temporaire » pour le support de l'application mobile.

### ⚖️ Conformité, Confidentialité & RGPD / Loi CI n° 2013-450
1. **Gestion du Consentement des Cookies Web :** [COMPLÉTÉ] Bandeau de consentement interactif et modale de personnalisation granulaire (Essentiels, Analytiques, Préférences) sur le front office web, mémorisation du choix en stockage local et modification permanente via le footer.
2. **Acceptation et Consultation des CGU / Confidentialité :** [COMPLÉTÉ] Traçabilité de l'acceptation initiale en base de données (`cgu_accepted_at`, `privacy_policy_accepted_at`) et consultation permanente de la politique de confidentialité et des CGU sur web et mobile.
3. **Droit d'Accès, de Portabilité et à l'Effacement (Backoffice) :** [COMPLÉTÉ] L'administrateur habilité (`admin.rgpd.view` / `admin.rgpd.manage`) consulte la vue consolidée des données personnelles d'un utilisateur, l'exporte au format JSON (portabilité) et procède à une anonymisation irréversible et tracée (`anonymized_at`, `anonymized_by`) qui expurge toutes les données identifiantes tout en conservant la ligne de compte pour l'intégrité comptable et l'audit. L'horodatage d'acceptation des CGU est affiché par compte.
4. **Journal d'Audit des Actions Sensibles :** [COMPLÉTÉ] Table append-only `admin_activity_logs` traçant acteur, IP, user-agent, sujet et contexte JSON de chaque action sensible du backoffice (voir Règle d'Or 25), consultable et filtrable depuis l'onglet « Journal d'audit ».
