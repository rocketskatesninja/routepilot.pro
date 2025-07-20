-- RoutePilot Pro Database Schema
-- This file contains the complete database structure for the pool service management system

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS routepilot_pro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Use the database
USE routepilot_pro;

-- Create database user
CREATE USER IF NOT EXISTS 'routepilot_user'@'localhost' IDENTIFIED BY 'routepilot_password_2024';
GRANT ALL PRIVILEGES ON routepilot_pro.* TO 'routepilot_user'@'localhost';
FLUSH PRIVILEGES;

-- Create settings table
CREATE TABLE IF NOT EXISTS `settings` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `key` varchar(255) NOT NULL UNIQUE,
    `value` text NULL,
    `type` varchar(255) NOT NULL DEFAULT 'string',
    `group` varchar(255) NOT NULL DEFAULT 'general',
    `description` text NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create users table
CREATE TABLE IF NOT EXISTS `users` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `first_name` varchar(255) NOT NULL,
    `last_name` varchar(255) NOT NULL,
    `email` varchar(255) NOT NULL UNIQUE,
    `phone` varchar(255) NULL,
    `street_address` varchar(255) NULL,
    `street_address_2` varchar(255) NULL,
    `city` varchar(255) NULL,
    `state` varchar(255) NULL,
    `zip_code` varchar(255) NULL,
    `notes_by_client` text NULL,
    `notes_by_admin` text NULL,
    `profile_photo` varchar(255) NULL,
    `role` enum('admin','technician','customer') NOT NULL DEFAULT 'customer',
    `email_verified_at` timestamp NULL DEFAULT NULL,
    `password` varchar(255) NOT NULL,
    `appointment_reminders` tinyint(1) NOT NULL DEFAULT 1,
    `mailing_list` tinyint(1) NOT NULL DEFAULT 1,
    `monthly_billing` tinyint(1) NOT NULL DEFAULT 1,
    `service_reports` enum('full','invoice_only','none') NOT NULL DEFAULT 'full',
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `remember_token` varchar(100) NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create clients table
CREATE TABLE IF NOT EXISTS `clients` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `first_name` varchar(255) NOT NULL,
    `last_name` varchar(255) NOT NULL,
    `email` varchar(255) NOT NULL,
    `phone` varchar(255) NULL,
    `street_address` varchar(255) NULL,
    `street_address_2` varchar(255) NULL,
    `city` varchar(255) NULL,
    `state` varchar(255) NULL,
    `zip_code` varchar(255) NULL,
    `notes_by_client` text NULL,
    `notes_by_admin` text NULL,
    `profile_photo` varchar(255) NULL,
    `role` enum('client','tech','admin') NOT NULL DEFAULT 'client',
    `appointment_reminders` tinyint(1) NOT NULL DEFAULT 1,
    `mailing_list` tinyint(1) NOT NULL DEFAULT 1,
    `monthly_billing` tinyint(1) NOT NULL DEFAULT 1,
    `service_reports` enum('full','invoice_only','none') NOT NULL DEFAULT 'full',
    `status` enum('active','inactive') NOT NULL DEFAULT 'active',
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create locations table
CREATE TABLE IF NOT EXISTS `locations` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_id` bigint(20) UNSIGNED NULL,
    `nickname` varchar(255) NULL,
    `street_address` varchar(255) NOT NULL,
    `street_address_2` varchar(255) NULL,
    `city` varchar(255) NULL,
    `state` varchar(255) NULL,
    `zip_code` varchar(255) NULL,
    `photos` json NULL,
    `access` enum('residential','commercial') NULL,
    `pool_type` enum('fiberglass','vinyl_liner','concrete','gunite') NULL,
    `water_type` enum('chlorine','salt') NULL,
    `filter_type` varchar(255) NULL,
    `setting` enum('indoor','outdoor') NULL,
    `installation` enum('inground','above') NULL,
    `gallons` int NULL,
    `service_frequency` enum('semi_weekly','weekly','bi_weekly','monthly') NULL,
    `service_day_1` varchar(255) NULL,
    `service_day_2` varchar(255) NULL,
    `rate_per_visit` decimal(8,2) NULL,
    `chemicals_included` tinyint(1) NULL,
    `assigned_technician_id` bigint(20) UNSIGNED NULL,
    `is_favorite` tinyint(1) NULL,
    `status` enum('active','inactive') NULL,
    `notes` text NULL,
    `vacuum` tinyint(1) NOT NULL DEFAULT 0,
    `brush` tinyint(1) NOT NULL DEFAULT 0,
    `skim` tinyint(1) NOT NULL DEFAULT 0,
    `clean_skimmer_basket` tinyint(1) NOT NULL DEFAULT 0,
    `clean_pump_basket` tinyint(1) NOT NULL DEFAULT 0,
    `clean_pool_deck` tinyint(1) NOT NULL DEFAULT 0,
    `clean_filter_cartridge` tinyint(1) NOT NULL DEFAULT 0,
    `backwash_sand_filter` tinyint(1) NOT NULL DEFAULT 0,
    `adjust_water_level` tinyint(1) NOT NULL DEFAULT 0,
    `adjust_auto_fill` tinyint(1) NOT NULL DEFAULT 0,
    `adjust_pump_timer` tinyint(1) NOT NULL DEFAULT 0,
    `adjust_heater` tinyint(1) NOT NULL DEFAULT 0,
    `check_cover` tinyint(1) NOT NULL DEFAULT 0,
    `check_lights` tinyint(1) NOT NULL DEFAULT 0,
    `check_fountain` tinyint(1) NOT NULL DEFAULT 0,
    `check_heater` tinyint(1) NOT NULL DEFAULT 0,
    `other_services` json NULL,
    `other_services_cost` decimal(8,2) NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `locations_client_id_foreign` (`client_id`),
    KEY `locations_assigned_technician_id_foreign` (`assigned_technician_id`),
    CONSTRAINT `locations_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `locations_assigned_technician_id_foreign` FOREIGN KEY (`assigned_technician_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create reports table
CREATE TABLE IF NOT EXISTS `reports` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `client_id` bigint(20) UNSIGNED NOT NULL,
    `location_id` bigint(20) UNSIGNED NOT NULL,
    `technician_id` bigint(20) UNSIGNED NOT NULL,
    `invoice_id` bigint(20) UNSIGNED NULL,
    `service_date` date NOT NULL,
    `service_time` time NOT NULL,
    `pool_gallons` int NULL,
    `fac` decimal(4,2) NULL,
    `cc` decimal(4,2) NULL,
    `ph` decimal(3,1) NULL,
    `alkalinity` int NULL,
    `calcium` int NULL,
    `salt` int NULL,
    `cya` int NULL,
    `tds` int NULL,
    `vacuumed` tinyint(1) NOT NULL DEFAULT 0,
    `brushed` tinyint(1) NOT NULL DEFAULT 0,
    `skimmed` tinyint(1) NOT NULL DEFAULT 0,
    `cleaned_skimmer_basket` tinyint(1) NOT NULL DEFAULT 0,
    `cleaned_pump_basket` tinyint(1) NOT NULL DEFAULT 0,
    `cleaned_pool_deck` tinyint(1) NOT NULL DEFAULT 0,
    `cleaned_filter_cartridge` tinyint(1) NOT NULL DEFAULT 0,
    `backwashed_sand_filter` tinyint(1) NOT NULL DEFAULT 0,
    `adjusted_water_level` tinyint(1) NOT NULL DEFAULT 0,
    `adjusted_auto_fill` tinyint(1) NOT NULL DEFAULT 0,
    `adjusted_pump_timer` tinyint(1) NOT NULL DEFAULT 0,
    `adjusted_heater` tinyint(1) NOT NULL DEFAULT 0,
    `checked_cover` tinyint(1) NOT NULL DEFAULT 0,
    `checked_lights` tinyint(1) NOT NULL DEFAULT 0,
    `checked_fountain` tinyint(1) NOT NULL DEFAULT 0,
    `checked_heater` tinyint(1) NOT NULL DEFAULT 0,
    `chemicals_used` json NULL,
    `chemicals_cost` decimal(8,2) NOT NULL DEFAULT 0.00,
    `other_services` json NULL,
    `other_services_cost` decimal(8,2) NOT NULL DEFAULT 0.00,
    `total_cost` decimal(8,2) NOT NULL DEFAULT 0.00,
    `notes_to_client` text NULL,
    `notes_to_admin` text NULL,
    `photos` json NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `reports_client_id_foreign` (`client_id`),
    KEY `reports_location_id_foreign` (`location_id`),
    KEY `reports_technician_id_foreign` (`technician_id`),
    KEY `reports_invoice_id_foreign` (`invoice_id`),
    CONSTRAINT `reports_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `reports_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `reports_technician_id_foreign` FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create invoices table
CREATE TABLE IF NOT EXISTS `invoices` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_number` varchar(255) NOT NULL UNIQUE,
    `client_id` bigint(20) UNSIGNED NOT NULL,
    `location_id` bigint(20) UNSIGNED NOT NULL,
    `technician_id` bigint(20) UNSIGNED NULL,
    `recurring_profile_id` bigint(20) UNSIGNED NULL,
    `service_date` date NOT NULL,
    `due_date` date NOT NULL,
    `rate_per_visit` decimal(8,2) NOT NULL,
    `chemicals_cost` decimal(8,2) NOT NULL DEFAULT 0.00,
    `chemicals_included` tinyint(1) NOT NULL DEFAULT 1,
    `extras_cost` decimal(8,2) NOT NULL DEFAULT 0.00,
    `total_amount` decimal(8,2) NOT NULL,
    `balance` decimal(8,2) NOT NULL DEFAULT 0.00,
    `status` enum('draft','sent','paid','overdue','cancelled') NOT NULL DEFAULT 'draft',
    `notes` text NULL,
    `notification_sent` tinyint(1) NOT NULL DEFAULT 0,
    `paid_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `invoices_client_id_foreign` (`client_id`),
    KEY `invoices_location_id_foreign` (`location_id`),
    KEY `invoices_technician_id_foreign` (`technician_id`),
    KEY `invoices_recurring_profile_id_foreign` (`recurring_profile_id`),
    CONSTRAINT `invoices_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `invoices_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `invoices_technician_id_foreign` FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create recurring billing profiles table
CREATE TABLE IF NOT EXISTS `recurring_billing_profiles` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `description` text NULL,
    `client_id` bigint(20) UNSIGNED NOT NULL,
    `location_id` bigint(20) UNSIGNED NOT NULL,
    `technician_id` bigint(20) UNSIGNED NOT NULL,
    `rate_per_visit` decimal(10,2) NOT NULL,
    `chemicals_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
    `chemicals_included` tinyint(1) NOT NULL DEFAULT 0,
    `extras_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
    `frequency` enum('weekly','biweekly','monthly','quarterly','custom') NOT NULL,
    `frequency_value` int NOT NULL DEFAULT 1,
    `start_date` date NOT NULL,
    `end_date` date NULL,
    `day_of_week` int NULL,
    `day_of_month` int NULL,
    `status` enum('active','paused','cancelled') NOT NULL DEFAULT 'active',
    `auto_generate_invoices` tinyint(1) NOT NULL DEFAULT 1,
    `advance_notice_days` int NOT NULL DEFAULT 7,
    `next_billing_date` date NULL,
    `invoices_generated` int NOT NULL DEFAULT 0,
    `total_amount_generated` int NOT NULL DEFAULT 0,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `recurring_billing_profiles_client_id_foreign` (`client_id`),
    KEY `recurring_billing_profiles_location_id_foreign` (`location_id`),
    KEY `recurring_billing_profiles_technician_id_foreign` (`technician_id`),
    CONSTRAINT `recurring_billing_profiles_client_id_foreign` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`) ON DELETE CASCADE,
    CONSTRAINT `recurring_billing_profiles_location_id_foreign` FOREIGN KEY (`location_id`) REFERENCES `locations` (`id`) ON DELETE CASCADE,
    CONSTRAINT `recurring_billing_profiles_technician_id_foreign` FOREIGN KEY (`technician_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create activities table
CREATE TABLE IF NOT EXISTS `activities` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` bigint(20) UNSIGNED NULL,
    `action` varchar(255) NOT NULL,
    `model_type` varchar(255) NULL,
    `model_id` bigint(20) UNSIGNED NULL,
    `description` text NOT NULL,
    `properties` json NULL,
    `ip_address` varchar(255) NULL,
    `user_agent` varchar(255) NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `activities_user_id_foreign` (`user_id`),
    CONSTRAINT `activities_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create email templates table
CREATE TABLE IF NOT EXISTS `email_templates` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` varchar(255) NOT NULL,
    `subject` varchar(255) NOT NULL,
    `body` text NOT NULL,
    `variables` json NULL,
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create cache table
CREATE TABLE IF NOT EXISTS `cache` (
    `key` varchar(255) NOT NULL PRIMARY KEY,
    `value` mediumtext NOT NULL,
    `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create cache locks table
CREATE TABLE IF NOT EXISTS `cache_locks` (
    `key` varchar(255) NOT NULL PRIMARY KEY,
    `owner` varchar(255) NOT NULL,
    `expiration` int NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create jobs table
CREATE TABLE IF NOT EXISTS `jobs` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue` varchar(255) NOT NULL,
    `payload` longtext NOT NULL,
    `attempts` tinyint(3) UNSIGNED NOT NULL,
    `reserved_at` int(10) UNSIGNED NULL,
    `available_at` int(10) UNSIGNED NOT NULL,
    `created_at` int(10) UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create job batches table
CREATE TABLE IF NOT EXISTS `job_batches` (
    `id` varchar(255) NOT NULL PRIMARY KEY,
    `name` varchar(255) NOT NULL,
    `total_jobs` int NOT NULL,
    `pending_jobs` int NOT NULL,
    `failed_jobs` int NOT NULL,
    `failed_job_ids` longtext NOT NULL,
    `options` mediumtext NULL,
    `cancelled_at` int NULL,
    `created_at` int NOT NULL,
    `finished_at` int NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create failed jobs table
CREATE TABLE IF NOT EXISTS `failed_jobs` (
    `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid` varchar(255) NOT NULL UNIQUE,
    `connection` text NOT NULL,
    `queue` text NOT NULL,
    `payload` longtext NOT NULL,
    `exception` longtext NOT NULL,
    `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create password reset tokens table
CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
    `email` varchar(255) NOT NULL PRIMARY KEY,
    `token` varchar(255) NOT NULL,
    `created_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create sessions table
CREATE TABLE IF NOT EXISTS `sessions` (
    `id` varchar(255) NOT NULL PRIMARY KEY,
    `user_id` bigint(20) UNSIGNED NULL,
    `ip_address` varchar(45) NULL,
    `user_agent` text NULL,
    `payload` longtext NOT NULL,
    `last_activity` int NOT NULL,
    KEY `sessions_user_id_index` (`user_id`),
    KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default settings
INSERT INTO `settings` (`key`, `value`, `type`, `group`, `description`, `created_at`, `updated_at`) VALUES
('site_title', 'RoutePilot Pro', 'string', 'general', 'Site title', NOW(), NOW()),
('site_tagline', 'Professional Pool Service Management', 'string', 'general', 'Site tagline', NOW(), NOW()),
('company_name', 'RoutePilot Pro', 'string', 'general', 'Company name', NOW(), NOW()),
('company_email', 'admin@routepilot.pro', 'string', 'general', 'Company email', NOW(), NOW()),
('company_phone', '', 'string', 'general', 'Company phone', NOW(), NOW()),
('company_address', '', 'string', 'general', 'Company address', NOW(), NOW()),
('background_image', '', 'string', 'general', 'Site background image', NOW(), NOW()),
('background_enabled', '0', 'boolean', 'general', 'Enable background image', NOW(), NOW()),
('background_fixed', '0', 'boolean', 'general', 'Background image fixed (parallax effect)', NOW(), NOW()),
('backup_enabled', '0', 'boolean', 'database', 'Enable automatic backups', NOW(), NOW()),
('backup_frequency', 'daily', 'string', 'database', 'Backup frequency', NOW(), NOW()),
('backup_retention_days', '30', 'integer', 'database', 'Backup retention days', NOW(), NOW()),
('backup_notification_email', '', 'string', 'database', 'Backup notification email', NOW(), NOW()),
('mail_driver', 'smtp', 'string', 'mail', 'Mail driver', NOW(), NOW()),
('mail_host', 'smtp.mailtrap.io', 'string', 'mail', 'Mail host', NOW(), NOW()),
('mail_port', '2525', 'integer', 'mail', 'Mail port', NOW(), NOW()),
('mail_username', '', 'string', 'mail', 'Mail username', NOW(), NOW()),
('mail_password', '', 'string', 'mail', 'Mail password', NOW(), NOW()),
('mail_encryption', 'tls', 'string', 'mail', 'Mail encryption', NOW(), NOW()),
('mail_from_address', 'noreply@routepilot.pro', 'string', 'mail', 'Mail from address', NOW(), NOW()),
('mail_from_name', 'RoutePilot Pro', 'string', 'mail', 'Mail from name', NOW(), NOW()),
('login_throttle_attempts', '5', 'integer', 'security', 'Login throttle attempts', NOW(), NOW()),
('login_throttle_minutes', '15', 'integer', 'security', 'Login throttle minutes', NOW(), NOW()),
('max_file_upload_size', '10', 'integer', 'security', 'Max file upload size (MB)', NOW(), NOW()),
('allowed_file_types', 'jpg,jpeg,png,gif,pdf,doc,docx,xls,xlsx,txt', 'string', 'security', 'Allowed file types', NOW(), NOW()),
('session_lifetime', '120', 'integer', 'security', 'Session lifetime (minutes)', NOW(), NOW()),
('password_min_length', '8', 'integer', 'security', 'Password minimum length', NOW(), NOW()),
('require_password_complexity', '0', 'boolean', 'security', 'Require password complexity', NOW(), NOW());

-- Create default admin user (password: admin123)
INSERT INTO `users` (`first_name`, `last_name`, `email`, `password`, `role`, `created_at`, `updated_at`) VALUES
('Admin', 'User', 'admin@routepilot.pro', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', NOW(), NOW()); 