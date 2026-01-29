# PROSARTISAN - ARCHITECTURE DOMAIN-DRIVEN DESIGN

## 1. CONTEXTES DÉLIMITÉS (BOUNDED CONTEXTS)

### 1.1 Bounded Context : Gestion des Identités (Identity & Access)

**Responsabilité :** Gérer l'inscription, l'authentification et la vérification des utilisateurs.

#### Agrégats
- **Utilisateur** (Aggregate Root)
  - Entités : Client, Artisan, Fournisseur, RéférentDeZone
  - Value Objects : Identité (CNI/Passeport), Coordonnées, Selfie
  - Événements : UtilisateurInscrit, ProfilValidé, CompteSuspendu

#### Domain Services
- `ServiceKYC` : Vérification des documents d'identité
- `ServiceAuthentification` : Gestion des sessions et 2FA

#### Règles Métier
- Un artisan ne peut être actif sans validation KYC
- Un utilisateur suspendu ne peut pas initier de transactions

---

### 1.2 Bounded Context : Marketplace (Recherche & Mise en Relation)

**Responsabilité :** Faciliter la découverte des artisans et la création de missions.

#### Agrégats
- **Mission** (Aggregate Root)
  - Entités : Devis, LigneDevis
  - Value Objects : Catégorie, ZoneGéographique, StatutMission
  - Événements : MissionCréée, DevisÉmis, DevisAccepté, DevisRejeté

#### Domain Services
- `ServiceRecherche` : Filtrage par métier, zone, score
- `ServiceCartographie` : Clustering et calcul de proximité (≤1km)
- `ServicePrivacité` : Floutage des coordonnées GPS (rayon 50m)

#### Règles Métier
- Les artisans à ≤1km apparaissent en tête avec marqueurs dorés
- La position exacte n'est révélée qu'après acceptation du devis
- Maximum 3 devis simultanés par client

---

### 1.3 Bounded Context : Transactions Financières (Financial Management)

**Responsabilité :** Sécuriser les paiements et gérer le séquestre.

#### Agrégats
- **Séquestre** (Aggregate Root)
  - Entités : PortefeuilleMatériel, PortefeuilleMainDœuvre
  - Value Objects : Montant, DeviseXOF, StatutSéquestre
  - Événements : FondsBloqués, FragmentationEffectuée

- **JetonMatériel** (Aggregate Root)
  - Value Objects : CodeJeton, MontantDisponible, DateExpiration
  - Événements : JetonGénéré, JetonValidé, JetonExpiré

#### Domain Services
- `ServiceMobileMoney` : Intégration Wave, Orange Money, MTN
- `ServiceFragmentation` : Calcul automatique des ratios (65% matériel / 35% main-d'œuvre)
- `ServiceAntiFraude` : Vérification GPS (distance < 100m)

#### Règles Métier
- Le jeton ne peut être validé que si artisan et fournisseur sont à <100m
- La validation partielle d'un jeton est autorisée
- Le transfert vers le fournisseur s'effectue à J+1

---

### 1.4 Bounded Context : Suivi de Chantier (Worksite Management)

**Responsabilité :** Orchestrer l'avancement des travaux et les validations.

#### Agrégats
- **Chantier** (Aggregate Root)
  - Entités : Jalon (Étape)
  - Value Objects : PreuveLivraison (Photo géolocalisée), StatutJalon
  - Événements : ChantierDémarré, JalonValidé, ChantierTerminé

#### Domain Services
- `ServiceValidation` : Génération et vérification OTP SMS
- `ServiceGéolocalisation` : Horodatage et vérification des photos

#### Règles Métier
- Chaque jalon doit être accompagné d'une preuve photo géolocalisée
- Le client a 48h pour valider ou contester un jalon
- La libération de la main-d'œuvre est conditionnée à la validation client

---

### 1.5 Bounded Context : Réputation (Reputation Management)

**Responsabilité :** Calculer et maintenir le Score N'Zassa.

#### Agrégats
- **ProfilRéputation** (Aggregate Root)
  - Value Objects : ScoreNZassa (0-100), ComposantesScore
  - Événements : ScoreRecalculé, SeuilÉligibilitéAtteint

#### Domain Services
- `ServiceCalculScore` : Algorithme de scoring
  - Fiabilité (40%) : Chantiers terminés / Chantiers acceptés
  - Intégrité (30%) : Absence de tentatives de contournement
  - Qualité (20%) : Moyenne des notes clients
  - Réactivité (10%) : Temps de réponse moyen

#### Règles Métier
- Le score est recalculé après chaque mission
- Toutes les variations sont historisées (audit bancaire)
- Score >700 = éligibilité micro-crédit

---

### 1.6 Bounded Context : Litiges (Dispute Resolution)

**Responsabilité :** Gérer les conflits entre clients et artisans.

#### Agrégats
- **Litige** (Aggregate Root)
  - Entités : Médiation, Arbitrage
  - Value Objects : TypeLitige, Décision, Justification
  - Événements : LitigeDéclaré, MédiationInitiée, ArbitrageRendu

#### Domain Services
- `ServiceMédiation` : Facilitation du dialogue
- `ServiceArbitrage` : Décision financière (remboursement/paiement/gel)

#### Règles Métier
- Un référent de zone intervient pour les chantiers >2M FCFA
- Le client a 7 jours pour signaler un problème après validation finale

---

## 2. COUCHES DDD (LAYERED ARCHITECTURE)

### 2.1 Domain Layer (Cœur Métier)
```
/domain
  /identites
    /entities: Client, Artisan, Fournisseur
    /value_objects: Identite, Coordonnees
    /repositories: IUtilisateurRepository
  /marketplace
    /entities: Mission, Devis
    /value_objects: Categorie, ZoneGeo
    /services: ServiceRecherche
  /financial
    /aggregates: Sequestre, JetonMateriel
    /events: FondsBloqués, JetonValidé
```

### 2.2 Application Layer (Cas d'Usage)
```
/application
  /use_cases
    - InscrireArtisan
    - RechercherArtisan
    - GenererJetonMateriel
    - ValiderJalon
    - CalculerScoreNZassa
  /dto: Objets de transfert de données
  /handlers: Gestionnaires d'événements
```

### 2.3 Infrastructure Layer
```
/infrastructure
  /persistence
    - PostgreSQL + PostGIS
    - Repositories concrets
  /external_services
    - MobileMoneyGateway (Wave, Orange, MTN)
    - GoogleMapsAPI
    - WhatsAppBusinessAPI
    - FirebaseCloudMessaging
  /security
    - JWTAuthentication
    - KYCVerificationService
```

### 2.4 Presentation Layer
```
/presentation
  /mobile: Application Flutter
  /backoffice: React.js/Vue.js
  /api: FastAPI endpoints
```

---

## 3. RELATIONS ENTRE CONTEXTES (CONTEXT MAPPING)

### Anti-Corruption Layer (ACL)
- **Marketplace → Identity** : Le contexte Marketplace accède aux profils utilisateurs via une ACL pour éviter le couplage
- **Financial → Marketplace** : Les transactions référencent les missions via des IDs, pas des objets complets
- **Worksite → Financial** : Le chantier déclenche la libération des fonds via des événements

### Shared Kernel
- **Value Objects partagés** : Montant, DeviseXOF, CoordonnéesGPS

### Customer/Supplier
- **Reputation (Supplier) → Worksite (Customer)** : Le contexte Réputation consomme les événements du Chantier pour recalculer le score

---

## 4. ÉVÉNEMENTS DOMAINE (DOMAIN EVENTS)

### Événements Critiques
```python
# Identity Context
class UtilisateurInscrit(DomainEvent):
    user_id: UUID
    type_utilisateur: str  # CLIENT, ARTISAN, FOURNISSEUR
    timestamp: datetime

class ProfilValidé(DomainEvent):
    artisan_id: UUID
    validateur_id: UUID
    timestamp: datetime

# Financial Context
class FondsBloqués(DomainEvent):
    sequestre_id: UUID
    mission_id: UUID
    montant_total: Decimal
    montant_materiel: Decimal
    montant_main_oeuvre: Decimal

class JetonGénéré(DomainEvent):
    jeton_id: UUID
    artisan_id: UUID
    code: str
    montant: Decimal
    date_expiration: datetime

class JetonValidé(DomainEvent):
    jeton_id: UUID
    fournisseur_id: UUID
    montant_utilisé: Decimal
    coordonnees_validation: GPS

# Worksite Context
class JalonValidé(DomainEvent):
    chantier_id: UUID
    jalon_id: UUID
    client_id: UUID
    montant_liberé: Decimal
    preuve_photo_url: str

# Reputation Context
class ScoreRecalculé(DomainEvent):
    artisan_id: UUID
    ancien_score: int
    nouveau_score: int
    raison: str
```

---

## 5. UBIQUITOUS LANGUAGE (LANGAGE OMNIPRÉSENT)

### Vocabulaire Métier
- **Séquestre** : Blocage temporaire des fonds du client sur la plateforme
- **Jeton Matériel** : Crédit numérique échangeable contre du matériel physique
- **Score N'Zassa** : Indicateur de réputation et solvabilité (0-100)
- **Jalon** : Étape validable d'un chantier
- **Référent de Zone** : Tiers de confiance pour validation physique
- **Clustering** : Regroupement visuel des artisans en zone dense
- **Floutage** : Masquage de la position exacte (rayon 50m)
- **KYC** : Vérification d'identité (Know Your Customer)

### Règles de Nommage
- Les événements sont au passé : `FondsBloqués`, `JetonValidé`
- Les commandes sont à l'impératif : `BloquerFonds`, `ValiderJeton`
- Les agrégats sont des noms : `Séquestre`, `Mission`, `Chantier`

---

## 6. TACTICAL PATTERNS (PATTERNS TACTIQUES)

### Entities vs Value Objects

**Entities (avec identité)**
- Client, Artisan, Fournisseur (ont un ID unique)
- Mission, Devis, Chantier
- Séquestre, JetonMatériel

**Value Objects (sans identité, immuables)**
- Montant (montant + devise)
- CoordonnéesGPS (latitude, longitude, précision)
- ScoreNZassa (valeur, composantes)
- CodeJeton (format PA-XXXX)

### Repositories
```python
class IMissionRepository(ABC):
    @abstractmethod
    def save(self, mission: Mission) -> None: pass
    
    @abstractmethod
    def find_by_id(self, mission_id: UUID) -> Optional[Mission]: pass
    
    @abstractmethod
    def find_by_artisan_in_zone(self, zone: ZoneGeo, rayon_km: float) -> List[Mission]: pass
```

### Factories
```python
class JetonMaterielFactory:
    @staticmethod
    def creer_jeton(
        sequestre: Sequestre,
        artisan: Artisan,
        fournisseurs_proches: List[Fournisseur]
    ) -> JetonMateriel:
        code = generer_code_unique()  # PA-XXXX
        montant = sequestre.montant_materiel_disponible
        expiration = datetime.now() + timedelta(days=7)
        
        return JetonMateriel(
            code=code,
            montant=montant,
            artisan_id=artisan.id,
            fournisseurs_autorises=[f.id for f in fournisseurs_proches],
            date_expiration=expiration
        )
```

---

## 7. SAGA PATTERN (PROCESSUS MÉTIER LONG)

### Saga : Cycle Complet d'une Mission

```
1. [Marketplace] MissionCréée
   → Notification aux artisans de la zone

2. [Marketplace] DevisÉmis
   → Notification au client

3. [Marketplace] DevisAccepté
   → Commande: BloquerFonds

4. [Financial] FondsBloqués
   → Commande: FragmenterSéquestre

5. [Financial] FragmentationEffectuée
   → Commande: GénérerJetonMateriel

6. [Financial] JetonGénéré
   → Notification à l'artisan

7. [Financial] JetonValidé (chez fournisseur)
   → Commande: DémarrerChantier

8. [Worksite] ChantierDémarré
   → Notification au client

9. [Worksite] PreuveLivraisonSoumise
   → Notification au client pour validation

10. [Worksite] JalonValidé (par client)
    → Commande: LibérerMainDœuvre

11. [Financial] MainDœuvreLiberée
    → Transfert Mobile Money vers artisan

12. [Worksite] ChantierTerminé
    → Commande: RecalculerScore

13. [Reputation] ScoreRecalculé
    → Notification à l'artisan
    → Mission archivée
```

### Gestion des Compensations
Si le client annule après blocage des fonds :
- **Compensation** : Remboursement total - frais de service (5%)
- **Pénalité artisan** : Impact négatif sur le Score N'Zassa

---

## 8. RECOMMANDATIONS TECHNIQUES

### Event Sourcing (Optionnel Phase 2)
Pour le contexte Réputation, envisager l'Event Sourcing pour tracer toutes les variations du Score N'Zassa (requis pour audit bancaire).

### CQRS (Command Query Responsibility Segregation)
- **Write Model** : PostgreSQL pour les commandes transactionnelles
- **Read Model** : Elasticsearch pour la recherche d'artisans (performances)

### Résilience
- **Circuit Breaker** pour les appels aux gateways Mobile Money
- **Mode dégradé** : Validation OTP SMS si échec GPS

---

## 9. MATRICE DE DÉCISION : BOUNDED CONTEXTS

| Contexte | Autonomie | Complexité | Équipe Dédiée |
|----------|-----------|------------|---------------|
| Identity | Haute | Moyenne | Oui |
| Marketplace | Haute | Haute | Oui |
| Financial | **Critique** | Très Haute | Oui |
| Worksite | Moyenne | Moyenne | Partagée |
| Reputation | Moyenne | Haute | Partagée |
| Dispute | Basse | Basse | Non (Admin) |

---

## 10. PROCHAINES ÉTAPES

### Phase 1 (MVP)
- Implémenter les contextes **Identity**, **Marketplace**, **Financial**, **Worksite**
- Reporter **Reputation** en calcul batch nocturne
- Litige géré manuellement via back-office

### Phase 2 (Scale)
- Event Sourcing pour Réputation
- CQRS pour Recherche
- Micro-crédit intégré au Financial Context

---

**Document généré pour ProsArtisan v1.0**  
*Approche Domain-Driven Design (DDD) by Eric Evans*