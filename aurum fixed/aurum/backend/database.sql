-- ============================================================
--  AURUM Hotel Booking System — Unified Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS hotel_management
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE hotel_management;

-- ============================================================
--  1. USERS  (guests and owners — frontend login)
-- ============================================================
CREATE TABLE IF NOT EXISTS users (
    user_id    INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(150) NOT NULL,
    email      VARCHAR(150) NOT NULL UNIQUE,
    password   VARCHAR(255),                        -- bcrypt hash
    role       ENUM('guest','owner','admin') DEFAULT 'guest',
    initials   VARCHAR(10),
    hotel_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  2. ADMIN USERS  (backend dashboard login)
-- ============================================================
CREATE TABLE IF NOT EXISTS admin_users (
    admin_id   INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(80)  NOT NULL UNIQUE,
    email      VARCHAR(150),
    password   VARCHAR(255) NOT NULL,               -- bcrypt hash
    role       ENUM('superadmin','manager','staff') DEFAULT 'staff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default superadmin (password: admin123) — CHANGE IN PRODUCTION
INSERT INTO admin_users (username, email, password, role)
VALUES ('superadmin', 'admin@aurum.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin')
ON DUPLICATE KEY UPDATE admin_id = admin_id;

-- ============================================================
--  3. HOTELS
-- ============================================================
CREATE TABLE IF NOT EXISTS hotels (
    hotel_id     INT AUTO_INCREMENT PRIMARY KEY,
    name         VARCHAR(200) NOT NULL,
    city         VARCHAR(100) NOT NULL,
    country      VARCHAR(100) NOT NULL,
    stars        TINYINT DEFAULT 5,
    price        DECIMAL(10,2) NOT NULL DEFAULT 0,
    rating       DECIMAL(3,2) DEFAULT 0,
    reviews      INT DEFAULT 0,
    description  TEXT,
    amenities    TEXT COMMENT 'comma-separated list',
    max_children INT DEFAULT 4,
    total_rooms  INT DEFAULT 10,
    initial      VARCHAR(10),
    color        VARCHAR(20),
    status       ENUM('active','pending','rejected') DEFAULT 'active',
    owner_id     INT DEFAULT NULL,
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  4. BOOKINGS
-- ============================================================
CREATE TABLE IF NOT EXISTS bookings (
    booking_id  INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    hotel_id    INT NOT NULL,
    hotel_name  VARCHAR(200),
    check_in    DATE NOT NULL,
    check_out   DATE NOT NULL,
    rooms       INT DEFAULT 1,
    guests      INT DEFAULT 2,
    total_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    status      ENUM('pending','confirmed','cancelled') DEFAULT 'confirmed',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)  REFERENCES users(user_id)   ON DELETE CASCADE,
    FOREIGN KEY (hotel_id) REFERENCES hotels(hotel_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  5. OWNER PROPERTIES  (submitted for review)
-- ============================================================
CREATE TABLE IF NOT EXISTS owner_properties (
    property_id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id    INT NOT NULL,
    name        VARCHAR(200) NOT NULL,
    city        VARCHAR(100),
    country     VARCHAR(100),
    stars       TINYINT DEFAULT 5,
    rooms       INT DEFAULT 10,
    price_from  DECIMAL(10,2) DEFAULT 0,
    description TEXT,
    amenities   TEXT,
    status      ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
--  6. AI CONVERSATIONS
-- ============================================================
CREATE TABLE IF NOT EXISTS ai_conversations (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    session_id        VARCHAR(100),
    user_message      TEXT,
    ai_response       TEXT,
    extracted_city    VARCHAR(100),
    extracted_budget  INT,
    extracted_rooms   INT,
    extracted_children INT,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
