-- Verification queries for seeded data
-- Run these after seeding to verify data integrity

-- Count users by role
SELECT role, COUNT(*) as count
FROM users
GROUP BY role
ORDER BY count DESC;

-- Count missions by status
SELECT status, COUNT(*) as count
FROM missions
GROUP BY status
ORDER BY count DESC;

-- Count devis by status
SELECT status, COUNT(*) as count
FROM devis
GROUP BY status
ORDER BY count DESC;

-- Count chantiers by status
SELECT status, COUNT(*) as count
FROM chantiers
GROUP BY status
ORDER BY count DESC;

-- Count jalons by status
SELECT status, COUNT(*) as count
FROM jalons
GROUP BY status
ORDER BY count DESC;

-- Count transactions by type and status
SELECT type, status, COUNT(*) as count
FROM transactions
GROUP BY type, status
ORDER BY type, status;

-- Count litiges by status
SELECT status, COUNT(*) as count
FROM litiges
GROUP BY status
ORDER BY count DESC;

-- Reputation profile statistics
SELECT
    MIN(current_score) as min_score,
    MAX(current_score) as max_score,
    AVG(current_score) as avg_score,
    COUNT(*) as total_profiles
FROM reputation_profiles;

-- Ratings statistics
SELECT
    score,
    COUNT(*) as count
FROM ratings
GROUP BY score
ORDER BY score DESC;

-- Total counts summary
SELECT
    (SELECT COUNT(*) FROM users) as total_users,
    (SELECT COUNT(*) FROM missions) as total_missions,
    (SELECT COUNT(*) FROM devis) as total_devis,
    (SELECT COUNT(*) FROM chantiers) as total_chantiers,
    (SELECT COUNT(*) FROM jalons) as total_jalons,
    (SELECT COUNT(*) FROM sequestres) as total_sequestres,
    (SELECT COUNT(*) FROM jetons_materiel) as total_jetons,
    (SELECT COUNT(*) FROM transactions) as total_transactions,
    (SELECT COUNT(*) FROM litiges) as total_litiges,
    (SELECT COUNT(*) FROM reputation_profiles) as total_reputation_profiles,
    (SELECT COUNT(*) FROM ratings) as total_ratings;
