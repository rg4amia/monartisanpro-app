# Seeding Verification Checklist

## Pre-Seeding Checks

- [ ] Database connection configured in `.env`
- [ ] Database exists and is accessible
- [ ] All migrations are present in `database/migrations/`
- [ ] TradeSeeder exists and is functional
- [ ] CompletePlatformSeeder exists

## Run Seeding

```bash
cd prosartisan_backend
php artisan migrate:fresh --seed
```

## Post-Seeding Verification

### 1. User Counts
```bash
php artisan tinker
>>> \App\Models\User::count() // Should be 65
>>> \App\Models\User::where('role', 'ARTISAN')->count() // Should be 30
>>> \App\Models\User::where('role', 'CLIENT')->count() // Should be 20
>>> \App\Models\User::where('role', 'FOURNISSEUR')->count() // Should be 10
>>> \App\Models\User::where('role', 'REFERENT_ZONE')->count() // Should be 5
```

### 2. Profile Counts
```sql
SELECT COUNT(*) FROM artisan_profiles; -- Should be 30
SELECT COUNT(*) FROM fournisseur_profiles; -- Should be 10
SELECT COUNT(*) FROM referent_zone_profiles; -- Should be 5
```

### 3. Marketplace Data
```sql
SELECT COUNT(*) FROM missions; -- Should be 50
SELECT COUNT(*) FROM devis; -- Should be ~150
SELECT status, COUNT(*) FROM missions GROUP BY status;
```

### 4. Worksite Data
```sql
SELECT COUNT(*) FROM chantiers; -- Should be 20
SELECT COUNT(*) FROM jalons; -- Should be ~80
SELECT COUNT(*) FROM sequestres; -- Should be 20
```

### 5. Financial Data
```sql
SELECT COUNT(*) FROM jetons_materiel; -- Should be ~60
SELECT COUNT(*) FROM transactions; -- Should be ~100
SELECT type, COUNT(*) FROM transactions GROUP BY type;
```

### 6. Dispute & Reputation
```sql
SELECT COUNT(*) FROM litiges; -- Should be 8
SELECT COUNT(*) FROM reputation_profiles; -- Should be 30
SELECT COUNT(*) FROM ratings; -- Should be ~40
```

### 7. Data Integrity Checks

#### Foreign Keys
```sql
-- All missions should have valid client_id
SELECT COUNT(*) FROM missions m 
LEFT JOIN users u ON m.client_id = u.id 
WHERE u.id IS NULL; -- Should be 0

-- All devis should have valid mission_id and artisan_id
SELECT COUNT(*) FROM devis d 
LEFT JOIN missions m ON d.mission_id = m.id 
WHERE m.id IS NULL; -- Should be 0

-- All chantiers should have valid mission_id
SELECT COUNT(*) FROM chantiers c 
LEFT JOIN missions m ON c.mission_id = m.id 
WHERE m.id IS NULL; -- Should be 0
```

#### Geographic Data
```sql
-- All artisans should have location
SELECT COUNT(*) FROM artisan_profiles 
WHERE latitude IS NULL OR longitude IS NULL; -- Should be 0

-- All missions should have location
SELECT COUNT(*) FROM missions 
WHERE latitude IS NULL OR longitude IS NULL; -- Should be 0
```

#### Timestamps
```sql
-- All records should have created_at
SELECT COUNT(*) FROM users WHERE created_at IS NULL; -- Should be 0
SELECT COUNT(*) FROM missions WHERE created_at IS NULL; -- Should be 0
```

### 8. Test Login

Try logging in with test credentials:
```bash
# Using curl
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"artisan1@prosartisan.sn","password":"password"}'
```

Expected: Success response with token

### 9. Sample Queries

#### Get open missions
```sql
SELECT id, description, trade_category, status 
FROM missions 
WHERE status = 'OPEN' 
LIMIT 5;
```

#### Get top artisans
```sql
SELECT u.name, rp.current_score, rp.average_rating 
FROM reputation_profiles rp
JOIN users u ON u.id = rp.artisan_id
ORDER BY rp.current_score DESC
LIMIT 10;
```

#### Get active chantiers
```sql
SELECT c.id, u1.name as client, u2.name as artisan, c.status
FROM chantiers c
JOIN users u1 ON c.client_id = u1.id
JOIN users u2 ON c.artisan_id = u2.id
WHERE c.status = 'IN_PROGRESS';
```

## Common Issues & Solutions

### Issue: Foreign key constraint fails
**Solution:** Run `php artisan migrate:fresh` before seeding

### Issue: Duplicate entry error
**Solution:** Database not fresh, run `php artisan migrate:fresh --seed`

### Issue: Class not found
**Solution:** Run `composer dump-autoload`

### Issue: Memory limit exceeded
**Solution:** Increase PHP memory limit or reduce seeder counts

### Issue: Timeout
**Solution:** Increase `max_execution_time` in php.ini

## Success Criteria

✅ All user counts match expected values
✅ All foreign key relationships are valid
✅ No NULL values in required fields
✅ Geographic coordinates are within Dakar bounds
✅ Timestamps are realistic (past 1-200 days)
✅ Test login works with seeded credentials
✅ Sample queries return expected results
✅ No database errors in logs

## Next Steps After Verification

1. Test API endpoints with seeded data
2. Verify mobile app can fetch data
3. Test backoffice with seeded users
4. Run integration tests
5. Perform load testing

---

**Last Updated:** January 29, 2026
