# Seeded Data Quick Reference

## 📊 Data Overview

| Entity | Count | Description |
|--------|-------|-------------|
| **Users** | 65 | All platform users |
| ├─ Artisans | 30 | Service providers |
| ├─ Clients | 20 | Job posters |
| ├─ Fournisseurs | 10 | Material suppliers |
| └─ Referent Zones | 5 | Mediators/Arbitrators |
| **Missions** | 50 | Job postings |
| **Devis** | ~150 | Quotes (2-5 per mission) |
| **Chantiers** | 20 | Active worksites |
| **Jalons** | ~80 | Milestones (3-6 per chantier) |
| **Sequestres** | 20 | Escrow accounts |
| **Jetons Materiel** | ~60 | Material tokens |
| **Transactions** | ~100 | Financial transactions |
| **Litiges** | 8 | Disputes |
| **Reputation Profiles** | 30 | Artisan scores |
| **Ratings** | ~40 | User reviews |

## 🔑 Test Accounts

All passwords: `password`

### Artisans (30)
```
artisan1@prosartisan.sn
artisan2@prosartisan.sn
...
artisan30@prosartisan.sn
```

### Clients (20)
```
client1@prosartisan.sn
client2@prosartisan.sn
...
client20@prosartisan.sn
```

### Fournisseurs (10)
```
fournisseur1@prosartisan.sn
fournisseur2@prosartisan.sn
...
fournisseur10@prosartisan.sn
```

### Referent Zones (5)
```
referent1@prosartisan.sn
referent2@prosartisan.sn
...
referent5@prosartisan.sn
```

## 🛠️ Trade Categories

- PLUMBER (Plombier)
- ELECTRICIAN (Électricien)
- MASON (Maçon)
- CARPENTER (Menuisier)
- PAINTER (Peintre)
- WELDER (Soudeur)

## 📍 Locations (Dakar)

All data is geo-located in Dakar neighborhoods:
- Plateau
- Almadies
- Ngor
- Parcelles Assainies
- Ouakam
- Medina
- Pikine
- Guediawaye

## 💰 Financial Data

### Budget Ranges
- Missions: 50,000 - 1,000,000 XOF
- Devis: 80,000 - 800,000 XOF
- Jetons: 20,000 - 300,000 XOF

### Payment Gateways
- WAVE
- ORANGE_MONEY
- MTN_MOMO
- FREE_MONEY

## 📈 Status Distributions

### Mission Statuses
- OPEN - Available for quotes
- QUOTED - Has received quotes
- ACCEPTED - Quote accepted, chantier created
- CANCELLED - Mission cancelled

### Devis Statuses
- PENDING - Awaiting client decision
- ACCEPTED - Client accepted
- REJECTED - Client rejected

### Chantier Statuses
- IN_PROGRESS - Work ongoing
- COMPLETED - Work finished
- SUSPENDED - Temporarily stopped

### Jalon Statuses
- PENDING - Not yet submitted
- SUBMITTED - Awaiting validation
- VALIDATED - Client approved
- CONTESTED - Client disputed
- AUTO_VALIDATED - Auto-approved after 72h

### Litige Statuses
- OPEN - Just reported
- IN_MEDIATION - Mediator assigned
- IN_ARBITRATION - Arbitrator deciding
- RESOLVED - Decision made
- CLOSED - Fully resolved

## 🎯 Use Cases Covered

### 1. Complete Workflow
- Client posts mission → Artisans quote → Client accepts → Chantier starts → Milestones validated → Payment released

### 2. Dispute Resolution
- Issue reported → Mediation → Arbitration → Resolution

### 3. Reputation System
- Completed work → Ratings → Score calculation → Profile updates

### 4. Material Tokens
- Client issues token → Artisan uses at supplier → Supplier validates

### 5. Financial Transactions
- Deposits → Escrow → Milestone releases → Withdrawals

## 🔍 Useful Queries

### Find active chantiers
```sql
SELECT * FROM chantiers WHERE status = 'IN_PROGRESS';
```

### Find top-rated artisans
```sql
SELECT u.name, rp.current_score, rp.average_rating 
FROM reputation_profiles rp
JOIN users u ON u.id = rp.artisan_id
ORDER BY rp.current_score DESC
LIMIT 10;
```

### Find open missions
```sql
SELECT m.*, u.name as client_name 
FROM missions m
JOIN users u ON u.id = m.client_id
WHERE m.status = 'OPEN'
ORDER BY m.created_at DESC;
```

### Find pending jalons
```sql
SELECT j.*, c.id as chantier_id
FROM jalons j
JOIN chantiers c ON c.id = j.chantier_id
WHERE j.status = 'SUBMITTED'
AND j.auto_validation_deadline > NOW();
```

## 🚀 Quick Start

```bash
# Seed the database
cd prosartisan_backend
./seed_platform.sh

# Or manually
php artisan migrate:fresh --seed

# Verify data
php artisan tinker
>>> \App\Models\User::count()
>>> \App\Models\Mission::where('status', 'OPEN')->count()
```

## 📝 Notes

- All amounts in centimes (divide by 100 for XOF)
- All timestamps are realistic (past 1-200 days)
- 80% of artisans are KYC verified
- Geographic coordinates have realistic variance
- Phone numbers follow Senegalese format (+221)
