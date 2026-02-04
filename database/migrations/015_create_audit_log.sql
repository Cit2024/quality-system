-- Migration: Create AuditLog table for tracking critical operations
-- Created: 2026-02-03
-- Purpose: Compliance, security monitoring, and user action history

CREATE TABLE IF NOT EXISTS `AuditLog` (
    `ID` INT PRIMARY KEY AUTO_INCREMENT,
    `UserID` INT NULL COMMENT 'ID of user who performed action (NULL for system actions)',
    `UserType` ENUM('admin', 'teacher', 'student', 'alumni', 'employer', 'system') NOT NULL DEFAULT 'system',
    `Action` VARCHAR(100) NOT NULL COMMENT 'Action performed (e.g., create_form, delete_question)',
    `EntityType` VARCHAR(50) NOT NULL COMMENT 'Type of entity affected (e.g., Form, Question, Section)',
    `EntityID` INT NULL COMMENT 'ID of affected entity',
    `OldValue` TEXT NULL COMMENT 'Previous value (JSON for complex data)',
    `NewValue` TEXT NULL COMMENT 'New value (JSON for complex data)',
    `IPAddress` VARCHAR(45) NULL COMMENT 'IPv4 or IPv6 address',
    `UserAgent` VARCHAR(255) NULL COMMENT 'Browser user agent string',
    `SessionID` VARCHAR(128) NULL COMMENT 'PHP session ID for correlation',
    `Status` ENUM('success', 'failed', 'partial') NOT NULL DEFAULT 'success',
    `ErrorMessage` TEXT NULL COMMENT 'Error details if status is failed',
    `CreatedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes for common queries
    INDEX `idx_user` (`UserID`, `UserType`),
    INDEX `idx_entity` (`EntityType`, `EntityID`),
    INDEX `idx_created` (`CreatedAt`),
    INDEX `idx_action` (`Action`),
    INDEX `idx_status` (`Status`),
    INDEX `idx_session` (`SessionID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Audit log for tracking all critical system operations';

-- Create a view for recent audit entries (last 30 days)
CREATE OR REPLACE VIEW `AuditLog_Recent` AS
SELECT 
    ID,
    UserID,
    UserType,
    Action,
    EntityType,
    EntityID,
    IPAddress,
    Status,
    CreatedAt
FROM AuditLog
WHERE CreatedAt >= DATE_SUB(NOW(), INTERVAL 30 DAY)
ORDER BY CreatedAt DESC;

-- Create a view for failed operations
CREATE OR REPLACE VIEW `AuditLog_Failures` AS
SELECT 
    ID,
    UserID,
    UserType,
    Action,
    EntityType,
    EntityID,
    ErrorMessage,
    IPAddress,
    CreatedAt
FROM AuditLog
WHERE Status = 'failed'
ORDER BY CreatedAt DESC;
