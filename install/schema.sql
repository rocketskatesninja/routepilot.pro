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

-- Note: The actual table creation will be handled by Laravel migrations
-- This file is for reference and manual database setup if needed 