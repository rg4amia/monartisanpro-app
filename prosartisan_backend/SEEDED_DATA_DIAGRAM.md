# Seeded Data Relationships Diagram

## Entity Relationship Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                         USERS (65 total)                            │
├─────────────────────────────────────────────────────────────────────┤
│  ┌──────────┐  ┌──────────┐  ┌──────────────┐  ┌──────────────┐  │
│  │ ARTISAN  │  │  CLIENT  │  │ FOURNISSEUR  │  │ REFERENT_ZONE│  │
│  │   (30)   │  │   (20)   │  │     (10)     │  │     (5)      │  │
│  └────┬─────┘  └────┬─────┘  └──────┬───────┘  └──────┬───────┘  │
└───────┼─────────────┼────────────────┼──────────────────┼──────────┘
        │             │                │                  │
        │             │                │                  │
┌───────▼─────────────▼────────────────┼──────────────────┼──────────┐
│              MISSIONS (50)           │                  │          │
│  ┌──────────────────────────────┐   │                  │          │
│  │ Status Distribution:         │   │                  │          │
│  │ • OPEN                       │   │                  │          │
│  │ • QUOTED                     │   │                  │          │
│  │ • ACCEPTED                   │   │                  │          │
│  │ • CANCELLED                  │   │                  │          │
│  └──────────────┬───────────────┘   │                  │          │
└─────────────────┼───────────────────┼──────────────────┼──────────┘
                  │                   │                  │
                  │                   │                  │
┌─────────────────▼───────────────────┼──────────────────┼──────────┐
│              DEVIS (~150)           │                  │          │
│  ┌──────────────────────────────┐   │                  │          │
│  │ 2-5 quotes per mission       │   │                  │          │
│  │ Status: PENDING/ACCEPTED/    │   │                  │          │
│  │         REJECTED              │   │                  │          │
│  └──────────────┬───────────────┘   │                  │          │
└─────────────────┼───────────────────┼──────────────────┼──────────┘
                  │                   │                  │
                  │ (accepted)        │                  │
┌─────────────────▼───────────────────┼──────────────────┼──────────┐
│            CHANTIERS (20)           │                  │          │
│  ┌──────────────────────────────┐   │                  │          │
│  │ Status: IN_PROGRESS/         │   │                  │          │
│  │         COMPLETED/SUSPENDED  │   │                  │          │
│  └──────┬───────────────────────┘   │                  │          │
└─────────┼───────────────────────────┼──────────────────┼──────────┘
          │                           │                  │
          │                           │                  │
    ┌─────┴─────┐                     │                  │
    │           │                     │                  │
┌───▼───┐   ┌───▼────────┐            │                  │
│JALONS │   │ SEQUESTRES │            │                  │
│ (~80) │   │    (20)    │            │                  │
├───────┤   ├────────────┤            │                  │
│3-6 per│   │Escrow for  │            │                  │
│chantier   │each chantier            │                  │
└───┬───┘   └────────────┘            │                  │
    │                                 │                  │
    │ (proof submission)              │                  │
    │                                 │                  │
┌───▼─────────────────────────────────▼──────────────────┼──────────┐
│         JETONS MATERIEL (~60)                          │          │
│  ┌──────────────────────────────┐                      │          │
│  │ 2-5 tokens per chantier      │                      │          │
│  │ For material purchases       │                      │          │
│  │ Status: ISSUED/VALIDATED/    │                      │          │
│  │         EXPIRED/CANCELLED    │                      │          │
│  └──────────────────────────────┘                      │          │
└────────────────────────────────────────────────────────┼──────────┘
                                                          │
┌─────────────────────────────────────────────────────────▼─────────┐
│                    LITIGES (8)                                     │
│  ┌──────────────────────────────────────────────────────────┐     │
│  │ Dispute Resolution Flow:                                 │     │
│  │ OPEN → IN_MEDIATION → IN_ARBITRATION → RESOLVED → CLOSED│     │
│  │                                                           │     │
│  │ Mediator/Arbitrator: REFERENT_ZONE users                │     │
│  └──────────────────────────────────────────────────────────┘     │
└────────────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────────────┐
│                  REPUTATION PROFILES (30)                          │
│  ┌──────────────────────────────────────────────────────────┐     │
│  │ One per artisan                                          │     │
│  │ Scores: Reliability, Integrity, Quality, Reactivity     │     │
│  │ Metrics: Completed projects, Average rating             │     │
│  └──────────────────────────────────────────────────────────┘     │
└────────────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────────────┐
│                      RATINGS (~40)                                 │
│  ┌──────────────────────────────────────────────────────────┐     │
│  │ Client ↔ Artisan mutual ratings                         │     │
│  │ 1-5 stars with comments                                 │     │
│  │ Created after chantier completion                       │     │
│  └──────────────────────────────────────────────────────────┘     │
└────────────────────────────────────────────────────────────────────┘

┌────────────────────────────────────────────────────────────────────┐
│                   TRANSACTIONS (~100)                              │
│  ┌──────────────────────────────────────────────────────────┐     │
│  │ Types: DEPOSIT, WITHDRAWAL, ESCROW_RELEASE, REFUND      │     │
│  │ Gateways: WAVE, ORANGE_MONEY, MTN_MOMO, FREE_MONEY      │     │
│  │ Status: PENDING, COMPLETED, FAILED, CANCELLED           │     │
│  └──────────────────────────────────────────────────────────┘     │
└────────────────────────────────────────────────────────────────────┘
```

## Data Flow Diagram

```
┌──────────┐
│  CLIENT  │
│  posts   │
│ MISSION  │
└────┬─────┘
     │
     ▼
┌─────────────┐
│  ARTISANS   │
│   submit    │
│   DEVIS     │
└─────┬───────┘
      │
      ▼
┌─────────────┐
│   CLIENT    │
│  accepts    │
│   DEVIS     │
└─────┬───────┘
      │
      ▼
┌─────────────────┐
│   CHANTIER      │
│   created       │
│ + SEQUESTRE     │
│   (escrow)      │
└─────┬───────────┘
      │
      ├──────────────────┐
      │                  │
      ▼                  ▼
┌─────────────┐    ┌──────────────┐
│   JALONS    │    │   JETONS     │
│  (3-6 per   │    │  MATERIEL    │
│  chantier)  │    │  (2-5 per    │
└─────┬───────┘    │  chantier)   │
      │            └──────┬───────┘
      │                   │
      ▼                   ▼
┌─────────────┐    ┌──────────────┐
│  ARTISAN    │    │ FOURNISSEUR  │
│  submits    │    │  validates   │
│   proof     │    │   jeton      │
└─────┬───────┘    └──────────────┘
      │
      ▼
┌─────────────┐
│   CLIENT    │
│  validates  │
│   jalon     │
└─────┬───────┘
      │
      ▼
┌─────────────┐
│  SEQUESTRE  │
│  releases   │
│   funds     │
└─────┬───────┘
      │
      ▼
┌─────────────┐
│  ARTISAN    │
│  receives   │
│  payment    │
└─────┬───────┘
      │
      ▼
┌─────────────┐
│   RATING    │
│   mutual    │
│  feedback   │
└─────┬───────┘
      │
      ▼
┌─────────────┐
│ REPUTATION  │
│   updated   │
└─────────────┘
```

## Dispute Flow

```
┌─────────────┐
│   ISSUE     │
│  occurs     │
└─────┬───────┘
      │
      ▼
┌─────────────┐
│   LITIGE    │
│  reported   │
│  (OPEN)     │
└─────┬───────┘
      │
      ▼
┌─────────────────┐
│  REFERENT_ZONE  │
│   assigned as   │
│    MEDIATOR     │
└─────┬───────────┘
      │
      ▼
┌─────────────────┐
│  IN_MEDIATION   │
│  communications │
│    exchanged    │
└─────┬───────────┘
      │
      ├─────────────┐
      │             │
      ▼             ▼
┌──────────┐  ┌─────────────┐
│ RESOLVED │  │IN_ARBITRATION
│ (agreed) │  │ (escalated) │
└──────────┘  └─────┬───────┘
                    │
                    ▼
              ┌─────────────┐
              │ ARBITRATOR  │
              │   renders   │
              │  decision   │
              └─────┬───────┘
                    │
                    ▼
              ┌─────────────┐
              │  RESOLVED   │
              │   CLOSED    │
              └─────────────┘
```

## Geographic Distribution

```
        Dakar Region
    ┌─────────────────┐
    │                 │
    │  ┌───┐ Pikine   │
    │  │ 🏠│          │
    │  └───┘          │
    │                 │
    │ Parcelles       │
    │ Assainies       │
    │  ┌───┐          │
    │  │ 🏠│          │
    │  └───┘          │
    │                 │
    │     ┌───┐ Ngor  │
    │     │ 🏠│       │
    │     └───┘       │
    │  Almadies       │
    │   ┌───┐         │
    │   │ 🏠│         │
    │   └───┘         │
    │                 │
    │ Ouakam          │
    │  ┌───┐          │
    │  │ 🏠│          │
    │  └───┘          │
    │                 │
    │   Plateau       │
    │    ┌───┐        │
    │    │ 🏠│        │
    │    └───┘        │
    │  Medina         │
    │   ┌───┐         │
    │   │ 🏠│         │
    │   └───┘         │
    │                 │
    └─────────────────┘

🏠 = Artisan/Client/Fournisseur locations
```

## Trade Distribution

```
PLUMBER      ████████ (8)
ELECTRICIAN  ████████ (8)
MASON        ████████ (8)
CARPENTER    ██████ (6)
PAINTER      ██████ (6)
WELDER       ████ (4)
```

## Status Distribution Examples

### Missions
```
OPEN       ████████████ (40%)
QUOTED     ████████ (30%)
ACCEPTED   ████ (20%)
CANCELLED  ██ (10%)
```

### Jalons
```
PENDING        ████████ (30%)
SUBMITTED      ████████ (30%)
VALIDATED      ██████ (20%)
CONTESTED      ████ (10%)
AUTO_VALIDATED ██ (10%)
```

### Transactions
```
COMPLETED  ████████████ (60%)
PENDING    ████████ (25%)
FAILED     ████ (10%)
CANCELLED  ██ (5%)
```

## Summary Statistics

```
Total Records: ~500+
├── Users: 65
├── Profiles: 45 (artisan + fournisseur + referent)
├── Missions: 50
├── Devis: ~150
├── Chantiers: 20
├── Jalons: ~80
├── Sequestres: 20
├── Jetons: ~60
├── Transactions: ~100
├── Litiges: 8
├── Reputation: 30
└── Ratings: ~40
```
