-- STRATOSPHERE - Complete Database Schema
-- Run this script to create or update the database structure.
-- Requires: MySQL 5.7+ / MariaDB 10.3+

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ────────────────────────────────────────────────────
-- Accounts
-- ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `Accounts` (
    `Id`         INT          NOT NULL AUTO_INCREMENT,
    `Username`   VARCHAR(50)  NOT NULL,
    `Password`   VARCHAR(255) NOT NULL,   -- bcrypt hash
    `Email`      VARCHAR(100) NOT NULL DEFAULT '',
    `CreatedAt`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`Id`),
    UNIQUE KEY `uq_username` (`Username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default user: admin / changeme  (password_hash('changeme', PASSWORD_BCRYPT))
INSERT IGNORE INTO `Accounts` (`Id`, `Username`, `Password`, `Email`)
VALUES (1, 'admin', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com');

-- ────────────────────────────────────────────────────
-- LoginAttempts  (rate-limiting)
-- ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `LoginAttempts` (
    `Id`           INT         NOT NULL AUTO_INCREMENT,
    `ip`           VARCHAR(45) NOT NULL,
    `attempted_at` TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`Id`),
    INDEX `idx_ip_time` (`ip`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ────────────────────────────────────────────────────
-- Devices
-- ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `Devices` (
    `Id`            INT            NOT NULL AUTO_INCREMENT,
    `BrandName`     VARCHAR(50)    NOT NULL DEFAULT '',
    `ModelName`     VARCHAR(50)    NOT NULL DEFAULT '',
    `ModelOs`       VARCHAR(30)    NOT NULL DEFAULT '',
    `BatteryLevel`  TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `ConnectType`   VARCHAR(30)    NOT NULL DEFAULT '',
    `BoardHardware` VARCHAR(50)    NOT NULL DEFAULT '',
    `Latitude`      DECIMAL(10,7)  NULL DEFAULT NULL,
    `Longitude`     DECIMAL(10,7)  NULL DEFAULT NULL,
    `Command`       VARCHAR(50)    NOT NULL DEFAULT 'NONE',
    `LastSeen`      TIMESTAMP      NULL DEFAULT NULL,
    `CreatedAt`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`Id`),
    INDEX `idx_last_seen` (`LastSeen`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ────────────────────────────────────────────────────
-- CommandLog  (audit trail)
-- ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `CommandLog` (
    `Id`        INT         NOT NULL AUTO_INCREMENT,
    `DeviceId`  INT         NOT NULL,
    `Command`   VARCHAR(50) NOT NULL,
    `UserId`    INT         NOT NULL DEFAULT 0,
    `SentAt`    TIMESTAMP   NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`Id`),
    INDEX `idx_device` (`DeviceId`),
    INDEX `idx_sent`   (`SentAt`),
    CONSTRAINT `fk_log_device`  FOREIGN KEY (`DeviceId`) REFERENCES `Devices`(`Id`) ON DELETE CASCADE,
    CONSTRAINT `fk_log_account` FOREIGN KEY (`UserId`)   REFERENCES `Accounts`(`Id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
