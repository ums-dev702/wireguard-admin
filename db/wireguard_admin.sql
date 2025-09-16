-- MySQL Dump File
-- WireGuard Admin Database Schema
-- Author: Alvin Kiveu
-- Date: 2025-09-16

-- Create database (you can change the name if you want)
CREATE DATABASE IF NOT EXISTS wireguard_admin
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE wireguard_admin;

-- ---------------------------
-- Table structure for `users`
-- ---------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100),
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'user') DEFAULT 'user',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_unicode_ci;

-- ---------------------------
-- Table structure for `peers`
-- ---------------------------
CREATE TABLE IF NOT EXISTS peers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    public_key TEXT NOT NULL,
    private_key TEXT NOT NULL,
    preshared_key TEXT,
    allowed_ips VARCHAR(255),
    endpoint VARCHAR(255),
    dns_servers VARCHAR(255),
    persistent_keepalive INT DEFAULT 25,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_unicode_ci;

-- --------------------------------
-- Table structure for `server_config`
-- --------------------------------
CREATE TABLE IF NOT EXISTS server_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_ip VARCHAR(45) NOT NULL,
    server_port INT NOT NULL,
    subnet VARCHAR(18) NOT NULL,
    dns_servers VARCHAR(255),
    mtu INT DEFAULT 1420,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_unicode_ci;

-- ------------------------------
-- Table structure for `audit_log`
-- ------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB 
  DEFAULT CHARSET=utf8mb4 
  COLLATE=utf8mb4_unicode_ci;

-- ------------------------------
-- Initial Data (Optional)
-- ------------------------------
INSERT INTO users (username, email, password, role, status)
VALUES ('admin', 'admin@admin.com', '$2y$10$nlcmbSO/fnC1rwnpbu/MouYW6EEp4MpMN2gMDn6RO9N7ft6q0i2jy', 'admin', 'active');

