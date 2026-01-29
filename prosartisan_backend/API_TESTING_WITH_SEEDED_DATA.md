# API Testing with Seeded Data

## Quick Test Scenarios

### 1. Authentication

#### Login as Artisan
```bash
POST /api/auth/login
{
  "email": "artisan1@prosartisan.sn",
  "password": "password"
}
```

#### Login as Client
```bash
POST /api/auth/login
{
  "email": "client1@prosartisan.sn",
  "password": "password"
}
```

### 2. Marketplace - Browse Missions

#### Get Open Missions
```bash
GET /api/missions?status=OPEN
Authorization: Bearer {token}
```

#### Get Missions Near Location (Dakar Plateau)
```bash
GET /api/missions?lat=14.6937&lng=-17.4441&radius=5000
Authorization: Bearer {token}
```

#### Filter by Trade
```bash
GET /api/missions?trade_category=PLUMBER
Authorization: Bearer {token}
```

### 3. Devis Management

#### Get Devis for a Mission
```bash
GET /api/missions/{mission_id}/devis
Authorization: Bearer {token}
```

#### Submit Devis (as Artisan)
```bash
POST /api/missions/{mission_id}/devis
Authorization: Bearer {artisan_token}
{
  "materials_amount_centimes": 15000000,
  "labor_amount_centimes": 10000000,
  "description": "Devis détaillé pour la mission"
}
```

#### Accept Devis (as Client)
```bash
POST /api/devis/{devis_id}/accept
Authorization: Bearer {client_token}
```

### 4. Chantier Management

#### Get My Chantiers (as Artisan)
```bash
GET /api/chantiers?artisan_id={artisan_id}
Authorization: Bearer {artisan_token}
```

#### Get Chantier Details
```bash
GET /api/chantiers/{chantier_id}
Authorization: Bearer {token}
```

### 5. Jalon Workflow

#### Get Jalons for Chantier
```bash
GET /api/chantiers/{chantier_id}/jalons
Authorization: Bearer {token}
```

#### Submit Jalon Proof (as Artisan)
```bash
POST /api/jalons/{jalon_id}/submit
Authorization: Bearer {artisan_token}
Content-Type: multipart/form-data

{
  "proof_photo": {file},
  "latitude": 14.6937,
  "longitude": -17.4441,
  "accuracy": 10.5
}
```

#### Validate Jalon (as Client)
```bash
POST /api/jalons/{jalon_id}/validate
Authorization: Bearer {client_token}
```

#### Contest Jalon (as Client)
```bash
POST /api/jalons/{jalon_id}/contest
Authorization: Bearer {client_token}
{
  "reason": "Le travail n'est pas conforme"
}
```

### 6. Material Tokens

#### Issue Jeton (as Client)
```bash
POST /api/jetons-materiel
Authorization: Bearer {client_token}
{
  "chantier_id": "{chantier_id}",
  "fournisseur_id": "{fournisseur_id}",
  "amount_centimes": 5000000
}
```

#### Validate Jeton (as Fournisseur)
```bash
POST /api/jetons-materiel/{jeton_id}/validate
Authorization: Bearer {fournisseur_token}
{
  "code": "ABC12345"
}
```

### 7. Transactions

#### Get Transaction History
```bash
GET /api/transactions
Authorization: Bearer {token}
```

#### Initiate Deposit
```bash
POST /api/transactions/deposit
Authorization: Bearer {token}
{
  "amount_centimes": 10000000,
  "gateway": "WAVE",
  "phone": "+221771234567"
}
```

#### Request Withdrawal (as Artisan)
```bash
POST /api/transactions/withdraw
Authorization: Bearer {artisan_token}
{
  "amount_centimes": 5000000,
  "gateway": "ORANGE_MONEY",
  "phone": "+221769876543"
}
```

### 8. Dispute Management

#### Report Litige
```bash
POST /api/litiges
Authorization: Bearer {token}
{
  "mission_id": "{mission_id}",
  "type": "QUALITY",
  "description": "Le travail n'est pas de qualité acceptable",
  "evidence": ["url1", "url2"]
}
```

#### Get My Litiges
```bash
GET /api/litiges?user_id={user_id}
Authorization: Bearer {token}
```

#### Send Mediation Message
```bash
POST /api/litiges/{litige_id}/messages
Authorization: Bearer {token}
{
  "message": "Je propose une solution..."
}
```

### 9. Reputation & Ratings

#### Get Artisan Reputation
```bash
GET /api/artisans/{artisan_id}/reputation
Authorization: Bearer {token}
```

#### Submit Rating (after chantier completion)
```bash
POST /api/chantiers/{chantier_id}/rate
Authorization: Bearer {token}
{
  "score": 5,
  "comment": "Excellent travail, très professionnel!"
}
```

#### Get Top Artisans
```bash
GET /api/artisans/top?limit=10
Authorization: Bearer {token}
```

### 10. Search & Discovery

#### Search Artisans by Trade
```bash
GET /api/artisans?trade_category=ELECTRICIAN&verified=true
Authorization: Bearer {token}
```

#### Search Artisans Near Location
```bash
GET /api/artisans/nearby?lat=14.6937&lng=-17.4441&radius=3000
Authorization: Bearer {token}
```

#### Search Fournisseurs
```bash
GET /api/fournisseurs?lat=14.7167&lng=-17.4677&radius=5000
Authorization: Bearer {token}
```

## Sample Test Flow

### Complete Mission Workflow

1. **Client creates mission**
   ```bash
   POST /api/missions
   {
     "description": "Installation électrique",
     "trade_category": "ELECTRICIAN",
     "budget_min_centimes": 10000000,
     "budget_max_centimes": 20000000,
     "latitude": 14.6937,
     "longitude": -17.4441
   }
   ```

2. **Artisans submit devis**
   ```bash
   POST /api/missions/{mission_id}/devis
   # Multiple artisans submit quotes
   ```

3. **Client accepts best devis**
   ```bash
   POST /api/devis/{devis_id}/accept
   ```

4. **Chantier automatically created**
   ```bash
   GET /api/chantiers/{chantier_id}
   ```

5. **Artisan submits jalons progressively**
   ```bash
   POST /api/jalons/{jalon_id}/submit
   # For each milestone
   ```

6. **Client validates jalons**
   ```bash
   POST /api/jalons/{jalon_id}/validate
   # Funds released from escrow
   ```

7. **Both parties rate each other**
   ```bash
   POST /api/chantiers/{chantier_id}/rate
   ```

## Testing Tips

### Use Seeded IDs
Query the database to get actual IDs:
```sql
-- Get a mission ID
SELECT id FROM missions WHERE status = 'OPEN' LIMIT 1;

-- Get an artisan ID
SELECT id FROM users WHERE role = 'ARTISAN' LIMIT 1;

-- Get a chantier ID
SELECT id FROM chantiers WHERE status = 'IN_PROGRESS' LIMIT 1;
```

### Test Different User Roles
- Login as different users to test role-based permissions
- Artisans can submit devis and jalons
- Clients can accept devis and validate jalons
- Referents can mediate disputes

### Test Edge Cases
- Try to validate someone else's jalon (should fail)
- Try to submit devis for wrong trade category
- Try to withdraw more than available balance
- Try to validate expired jeton

### Monitor Escrow Flow
```sql
-- Check escrow balance
SELECT * FROM sequestres WHERE chantier_id = '{chantier_id}';

-- Check released amounts
SELECT SUM(labor_amount_centimes) 
FROM jalons 
WHERE chantier_id = '{chantier_id}' 
AND status = 'VALIDATED';
```

## Postman Collection

Import these as Postman environment variables:
```json
{
  "base_url": "http://localhost:8000/api",
  "artisan_email": "artisan1@prosartisan.sn",
  "client_email": "client1@prosartisan.sn",
  "fournisseur_email": "fournisseur1@prosartisan.sn",
  "password": "password",
  "artisan_token": "{{artisan_token}}",
  "client_token": "{{client_token}}"
}
```

## Common Response Codes

- `200` - Success
- `201` - Created
- `400` - Bad Request (validation error)
- `401` - Unauthorized (invalid/missing token)
- `403` - Forbidden (insufficient permissions)
- `404` - Not Found
- `422` - Unprocessable Entity (business logic error)
- `500` - Server Error
