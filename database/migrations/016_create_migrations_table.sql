-- Migration: 016_create_migrations_table.sql
-- Created: 2026-02-04
-- Purpose: Track applied migrations for deployment automation

CREATE TABLE IF NOT EXISTS `Migrations` (
    `ID` INT PRIMARY KEY AUTO_INCREMENT,
    `Migration` VARCHAR(255) NOT NULL UNIQUE COMMENT 'Migration filename',
    `AppliedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `RolledBackAt` TIMESTAMP NULL DEFAULT NULL,
    `Checksum` VARCHAR(64) NULL COMMENT 'SHA256 of migration file',
    `ExecutionTime` INT NULL COMMENT 'Execution time in milliseconds',
    `Status` ENUM('applied', 'rolled_back', 'failed') NOT NULL DEFAULT 'applied',
    `ErrorMessage` TEXT NULL,
    
    INDEX `idx_applied` (`AppliedAt`),
    INDEX `idx_status` (`Status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Tracks database migrations for deployment automation';

-- Insert self-reference (this migration)
INSERT INTO Migrations (Migration, Checksum, Status) 
VALUES ('016_create_migrations_table.sql', SHA2('016_create_migrations_table.sql', 256), 'applied')
ON DUPLICATE KEY UPDATE Status = 'applied';
