# ProsArtisan Platform Seeding Guide

## Overview

The `CompletePlatformSeeder` creates a complete simulation of the ProsArtisan platform with realistic data across all domains.

## What Gets Seeded

### Users (65 total)
- **30 Artisans** - Various trades (plumber, electrician, mason, carpenter, painter, welder)
- **20 Clients** - Regular users posting missions
- **10 Fournisseurs** - Material suppliers with shop locations
- **5 Referent Zones** - Zone managers for mediation/arbitration

### Marketplace Data
- **50 Missions** - Job postings with various statuses (OPEN, QUOTED, ACCEPTED, CANCELLED)
- **~150 Devis** - 2-5 quotes per mission from different artisans
- **20 Chantiers** - Active worksites from accepted quotes

### Worksite Data
- **~80 Jalons** - Milestones with various statuses (PENDING, SUBMITTED, VALIDATED, CONTESTED)
- **20 Sequestres** - Escrow accounts for each chantier
- **~60 Jetons Materiel** - Material tokens for supplier purchases

### Financial Data
- **~100 Transactions** - Deposits, withdrawals, escrow releases
- Multiple payment gateways (WAVE, ORANGE_MONEY, MTN_MOMO, FREE_MONEY)

### Dispute Management
- **8 Litiges** - Disputes with various statuses and resolutions
- Different dispute types (QUALITY, PAYMENT, DELAY, OTHER)

### Reputation System
- **30 Reputation Profiles** - One per artisan with calculated scores
- **~40 Ratings** - Client-artisan mutual ratings for completed work

## Running the Seeder

### Fresh Database (Recommended)
```bash
cd prosartisan_backend
php artisan migrate:fresh --seed
```

### Add to Existing Database
```bash
php artisan db:seed --class=CompletePlatformSeeder
```

### Run Only Trades Seeder
```bash
php artisan db:seed --class=TradeSeeder
```

## Test Credentials

All seeded users have the password: `password`

### Sample Logins
- Artisan: `artisan1@prosartisan.sn` to `artisan30@prosartisan.sn`
- Client: `client1@prosartisan.sn` to `client20@prosartisan.sn`
- Fournisseur: `fournisseur1@prosartisan.sn` to `fournisseur10@prosartisan.sn`
- Referent: `referent1@prosartisan.sn` to `referent5@prosartisan.sn`

## Data Characteristics

### Geographic Distribution
- All locations centered around Dakar, Senegal
- Coordinates spread across major neighborhoods:
  - Plateau (14.6937, -17.4441)
  - Almadies (14.7167, -17.4677)
  - Ngor (14.7319, -17.4572)
  - Parcelles Assainies (14.7644, -17.3889)
  - Ouakam (14.7392, -17.4856)
  - Medina (14.6928, -17.4467)
  - Pikine (14.7797, -17.3656)
  - Guediawaye (14.7500, -17.3333)

### Realistic Scenarios
- 80% of artisans are KYC verified
- Missions have budgets ranging from 50,000 to 1,000,000 XOF
- Chantiers have 3-6 milestones each
- Various transaction statuses (PENDING, COMPLETED, FAILED)
- Disputes in different stages of resolution

### Time Distribution
- Users created over the past 30-200 days
- Missions posted in the last 1-60 days
- Chantiers started 1-3 days after quote acceptance
- Realistic progression through workflow stages

## Database Tables Populated

1. `users` - All platform users
2. `artisan_profiles` - Artisan-specific data
3. `fournisseur_profiles` - Supplier-specific data
4. `referent_zone_profiles` - Zone manager data
5. `missions` - Job postings
6. `devis` - Quotes/estimates
7. `chantiers` - Active worksites
8. `jalons` - Milestones with proof of work
9. `sequestres` - Escrow accounts
10. `jetons_materiel` - Material purchase tokens
11. `transactions` - Financial transactions
12. `litiges` - Disputes
13. `reputation_profiles` - Artisan reputation scores
14. `ratings` - User ratings and reviews

## Notes

- All monetary amounts are stored in centimes (multiply by 100)
- UUIDs are used for all primary keys
- Timestamps reflect realistic progression through workflows
- Phone numbers follow Senegalese format (+221)
- All data is in French (platform language)

## Troubleshooting

### Foreign Key Errors
Make sure migrations are run in order:
```bash
php artisan migrate:fresh
php artisan db:seed
```

### Memory Issues
If seeding fails due to memory, reduce the number of records in `CompletePlatformSeeder.php`

### Duplicate Data
Always use `migrate:fresh` to start with a clean database when testing
