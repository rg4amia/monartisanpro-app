-- =============================================================================
-- SCRIPT DE MIGRATION PRODUCTION — ProsArtisan
-- Généré le : 2026-07-23
-- Version locale : Batch 11 (toutes les migrations locales sont à jour)
--
-- ⚠️  CE SCRIPT EST IDEMPOTENT : sûr à exécuter plusieurs fois.
--     Chaque bloc vérifie l'existence avant d'agir (IF NOT EXISTS / IGNORE).
--     Exécuter dans l'ordre, dans une transaction si possible.
--
-- USAGE SUR LE SERVEUR DE PRODUCTION :
--   mysql -u <user> -p <database> < production_migration.sql
-- =============================================================================

SET foreign_key_checks = 0;

-- =============================================================================
-- BLOC 1 — TABLE migrations (si absente — très rare)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- BLOC 2 — TABLE score_ledger_entries
-- (migration 2026_07_07_193329)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `score_ledger_entries` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` bigint unsigned NOT NULL,
  `event_type` varchar(255) NOT NULL,
  `points` int NOT NULL,
  `credibility_factor` decimal(3,2) NOT NULL DEFAULT '1.00',
  `evaluation_id` bigint unsigned DEFAULT NULL,
  `mission_id` bigint unsigned DEFAULT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `score_ledger_entries_user_id_foreign` (`user_id`),
  KEY `score_ledger_entries_evaluation_id_foreign` (`evaluation_id`),
  KEY `score_ledger_entries_mission_id_foreign` (`mission_id`),
  CONSTRAINT `score_ledger_entries_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `score_ledger_entries_evaluation_id_foreign` FOREIGN KEY (`evaluation_id`) REFERENCES `evaluations` (`id`) ON DELETE SET NULL,
  CONSTRAINT `score_ledger_entries_mission_id_foreign` FOREIGN KEY (`mission_id`) REFERENCES `missions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- BLOC 3 — TABLE evidence_vault
-- (migration 2026_07_07_213000)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `evidence_vault` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `litige_id` bigint unsigned NOT NULL,
  `uploaded_by` bigint unsigned NOT NULL,
  `file_url` varchar(255) NOT NULL,
  `sha256_hash` varchar(64) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `evidence_vault_litige_id_foreign` (`litige_id`),
  KEY `evidence_vault_uploaded_by_foreign` (`uploaded_by`),
  CONSTRAINT `evidence_vault_litige_id_foreign` FOREIGN KEY (`litige_id`) REFERENCES `litiges` (`id`) ON DELETE CASCADE,
  CONSTRAINT `evidence_vault_uploaded_by_foreign` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- BLOC 4 — TABLE jury_reviews
-- (migration 2026_07_08_000001)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `jury_reviews` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `litige_id` bigint unsigned NOT NULL,
  `jure_id` bigint unsigned NOT NULL,
  `verdict` enum('CONFORME','NON_CONFORME') DEFAULT NULL,
  `voted_at` timestamp NULL DEFAULT NULL,
  `compensation` bigint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `jury_reviews_litige_id_jure_id_unique` (`litige_id`,`jure_id`),
  KEY `jury_reviews_jure_id_foreign` (`jure_id`),
  CONSTRAINT `jury_reviews_litige_id_foreign` FOREIGN KEY (`litige_id`) REFERENCES `litiges` (`id`) ON DELETE CASCADE,
  CONSTRAINT `jury_reviews_jure_id_foreign` FOREIGN KEY (`jure_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- BLOC 5 — TABLE parrainages
-- (migration 2026_07_08_000003)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `parrainages` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `parrain_id` bigint unsigned NOT NULL,
  `filleul_id` bigint unsigned NOT NULL,
  `score_caution` bigint NOT NULL DEFAULT '0',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `parrainages_filleul_id_unique` (`filleul_id`),
  KEY `parrainages_parrain_id_foreign` (`parrain_id`),
  CONSTRAINT `parrainages_parrain_id_foreign` FOREIGN KEY (`parrain_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `parrainages_filleul_id_foreign` FOREIGN KEY (`filleul_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- BLOC 6 — TABLE settings + valeurs par défaut
-- (migrations 2026_07_08_000005, 2026_07_12_000002, 2026_07_18_091230)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(255) NOT NULL,
  `value` text,
  `type` varchar(255) NOT NULL DEFAULT 'string',
  `group` varchar(255) NOT NULL DEFAULT 'general',
  `label` varchar(255) DEFAULT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `settings_key_unique` (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertion des paramètres (INSERT IGNORE = idempotent)
INSERT IGNORE INTO `settings` (`key`, `value`, `type`, `group`, `label`, `description`, `created_at`, `updated_at`) VALUES
  ('commission_service',    '0.00', 'float',  'commissions', 'Commission sur Main dOeuvre (Artisans)', 'Frais de plateforme (ratio).', NOW(), NOW()),
  ('commission_fournisseur','0.05', 'float',  'commissions', 'Commission Fournisseur / Materiaux',     'Commission materiaux (ratio).', NOW(), NOW()),
  ('platform_fee_ratio',   '0.03', 'float',  'commissions', 'Frais de Service Plateforme',            'Frais client final (ratio).', NOW(), NOW()),
  ('commission_livreur',   '0.10', 'float',  'commissions', 'Commission sur Course Livreur',          'Commission livreur (ratio).', NOW(), NOW()),
  ('commission_categories', '{"macon":0.05,"plombier":0.05,"electricien":0.07,"peintre":0.04}', 'json', 'commissions', 'Commission par Categorie Artisan', 'Par metier (JSON).', NOW(), NOW()),
  ('otp_delivery_channel', 'sms',  'string', 'general',    'Canal OTP',                              'sms, whatsapp ou both.', NOW(), NOW()),
  ('block_client',         'none', 'string', 'app_access', 'Blocage Acces Client',                   'none, new, old, all.', NOW(), NOW()),
  ('block_artisan',        'none', 'string', 'app_access', 'Blocage Acces Artisan',                  'none, new, old, all.', NOW(), NOW()),
  ('block_fournisseur',    'none', 'string', 'app_access', 'Blocage Acces Fournisseur',              'none, new, old, all.', NOW(), NOW()),
  ('block_livreur',        'none', 'string', 'app_access', 'Blocage Acces Livreur',                  'none, new, old, all.', NOW(), NOW()),
  ('app_access_disabled_message', 'Acces temporairement restreint. Merci de votre patience.', 'string', 'app_access', 'Message erreur acces', 'Message acces desactive.', NOW(), NOW());

-- =============================================================================
-- BLOC 7 — TABLE artisan_stocks
-- (migration 2026_07_23_113041)
-- =============================================================================
CREATE TABLE IF NOT EXISTS `artisan_stocks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `artisan_id` bigint unsigned NOT NULL,
  `description` text NOT NULL,
  `quantity` int NOT NULL DEFAULT '1',
  `unit_cost` bigint NOT NULL DEFAULT '0',
  `condition` enum('neuf','occasion') NOT NULL DEFAULT 'neuf',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `artisan_stocks_artisan_id_foreign` (`artisan_id`),
  CONSTRAINT `artisan_stocks_artisan_id_foreign` FOREIGN KEY (`artisan_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- BLOC 8 — COLONNES USERS : device_fingerprint, score_frozen, cgu_accepted_at
-- (migrations 2026_07_08_000002 et 2026_07_17_194704)
-- =============================================================================
ALTER TABLE `users`
  ADD COLUMN IF NOT EXISTS `device_fingerprint` varchar(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `score_frozen` tinyint(1) NOT NULL DEFAULT '0',
  ADD COLUMN IF NOT EXISTS `cgu_accepted_at` timestamp NULL DEFAULT NULL;

-- =============================================================================
-- BLOC 9 — COLONNES ORDERS : vehicle_class, surge + colonnes livraison/litige
-- (migrations 2026_07_08_000004 et 2026_07_23_170000)
-- =============================================================================
ALTER TABLE `orders`
  ADD COLUMN IF NOT EXISTS `vehicle_class` varchar(255) DEFAULT 'moto',
  ADD COLUMN IF NOT EXISTS `surge_multiplier` decimal(3,2) NOT NULL DEFAULT '1.00',
  ADD COLUMN IF NOT EXISTS `delivered_at` timestamp NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `pickup_photo_url` varchar(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `delivery_photo_url` varchar(255) DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `waiting_time_minutes` int NOT NULL DEFAULT '0',
  ADD COLUMN IF NOT EXISTS `dispute_reason` text,
  ADD COLUMN IF NOT EXISTS `dispute_opened_at` timestamp NULL DEFAULT NULL;

-- =============================================================================
-- BLOC 10 — MISSIONS : colonne payment_type
-- (migration 2026_07_14_120000)
-- =============================================================================
ALTER TABLE `missions`
  ADD COLUMN IF NOT EXISTS `payment_type` varchar(255) NOT NULL DEFAULT 'total';

-- =============================================================================
-- BLOC 11 — MISSIONS : status ENUM -> VARCHAR(50)
-- ⚡ CRITIQUE : sans cette migration, "Demander un devis" plante en 500
-- (migrations 2026_07_07_000001 et 2026_07_23_212000)
-- =============================================================================
ALTER TABLE `missions`
  MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'draft';

-- Migrer anciens statuts français vers les valeurs FSM
UPDATE `missions` SET `status` = 'draft'          WHERE `status` = 'en_attente';
UPDATE `missions` SET `status` = 'funded_locked'   WHERE `status` = 'financee';
UPDATE `missions` SET `status` = 'in_progress'     WHERE `status` = 'en_cours';
UPDATE `missions` SET `status` = 'completed'       WHERE `status` = 'terminee';
UPDATE `missions` SET `status` = 'disputed'        WHERE `status` = 'litige';

-- =============================================================================
-- BLOC 12 — USERS : ajouter rôle livreur
-- (migration 2026_07_23_121006)
-- =============================================================================
ALTER TABLE `users`
  MODIFY COLUMN `role` ENUM('client','artisan','fournisseur','referent','admin','livreur') NOT NULL;

-- =============================================================================
-- BLOC 13 — wallet_transactions : cle_idempotence
-- (migration 2026_07_07_000002)
-- =============================================================================
ALTER TABLE `wallet_transactions`
  ADD COLUMN IF NOT EXISTS `cle_idempotence` varchar(100) NOT NULL DEFAULT '';

UPDATE `wallet_transactions`
  SET `cle_idempotence` = CONCAT('legacy-', `id`)
  WHERE `cle_idempotence` = '' OR `cle_idempotence` IS NULL;

ALTER IGNORE TABLE `wallet_transactions`
  ADD UNIQUE INDEX `idx_wallet_idempotence` (`operation`, `cle_idempotence`);

-- =============================================================================
-- BLOC 14 — Enregistrer toutes les migrations dans la table migrations
--           (pour que php artisan migrate:status les affiche comme "Ran")
-- =============================================================================
INSERT IGNORE INTO `migrations` (`migration`, `batch`) VALUES
  ('2026_07_07_000001_add_fsm_states_to_missions_table',            99),
  ('2026_07_07_000002_add_idempotence_to_wallet_transactions_table',99),
  ('2026_07_07_193329_create_score_ledger_entries_table',           99),
  ('2026_07_07_193427_change_score_nzassa_default_on_users_table',  99),
  ('2026_07_07_213000_create_evidence_vault_table',                 99),
  ('2026_07_08_000001_create_jury_reviews_table',                   99),
  ('2026_07_08_000002_add_device_fingerprint_to_users_table',       99),
  ('2026_07_08_000003_create_parrainages_table',                    99),
  ('2026_07_08_000004_add_surge_pricing_to_orders_table',           99),
  ('2026_07_08_000005_create_settings_table',                       99),
  ('2026_07_10_000000_add_google_2fa_secret_to_users_table',        99),
  ('2026_07_12_000000_create_permissions_and_roles_tables',         99),
  ('2026_07_12_000001_seed_permissions_table',                      99),
  ('2026_07_12_000002_add_otp_delivery_channel_setting',            99),
  ('2026_07_14_120000_add_payment_type_to_missions_table',          99),
  ('2026_07_17_194704_add_cgu_accepted_at_to_users_table',          99),
  ('2026_07_18_091230_add_app_access_settings',                     99),
  ('2026_07_23_113041_create_artisan_stocks_table',                 99),
  ('2026_07_23_121006_add_livreur_to_users_role_enum',              99),
  ('2026_07_23_170000_add_photos_and_dispute_to_orders_table',      99),
  ('2026_07_23_212000_update_missions_status_to_varchar',           99);

SET foreign_key_checks = 1;

SELECT 'Migration de production terminee avec succes !' AS statut;
