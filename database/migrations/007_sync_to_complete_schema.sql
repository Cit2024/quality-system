-- ===================================================================
-- Migration 007: Sync Existing Database to Complete Schema
-- ===================================================================
-- Purpose: Bring existing database up to date with complete schema
-- This migration is safe to run on databases that already have data
-- It only adds missing tables/columns without dropping or recreating
-- ===================================================================

-- ===================================================================
-- Add FormTypes table if it doesn't exist
-- ===================================================================

CREATE TABLE IF NOT EXISTS `FormTypes` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Icon` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Slug` (`Slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- Add EvaluatorTypes table if it doesn't exist
-- ===================================================================

CREATE TABLE IF NOT EXISTS `EvaluatorTypes` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Icon` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Slug` (`Slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- Add FormType_EvaluatorType table if it doesn't exist
-- ===================================================================

CREATE TABLE IF NOT EXISTS `FormType_EvaluatorType` (
  `FormTypeID` int(11) NOT NULL,
  `EvaluatorTypeID` int(11) NOT NULL,
  PRIMARY KEY (`FormTypeID`, `EvaluatorTypeID`),
  KEY `EvaluatorTypeID` (`EvaluatorTypeID`),
  CONSTRAINT `FormType_EvaluatorType_ibfk_1` FOREIGN KEY (`FormTypeID`) REFERENCES `FormTypes` (`ID`) ON DELETE CASCADE,
  CONSTRAINT `FormType_EvaluatorType_ibfk_2` FOREIGN KEY (`EvaluatorTypeID`) REFERENCES `EvaluatorTypes` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- Add FormAccessFields table if it doesn't exist
-- ===================================================================

CREATE TABLE IF NOT EXISTS `FormAccessFields` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `FormID` int(11) NOT NULL,
  `Label` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `Slug` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `FieldType` enum('text','number','password','email','date') CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `IsRequired` tinyint(1) DEFAULT '0',
  `OrderIndex` int(11) DEFAULT '0',
  PRIMARY KEY (`ID`),
  KEY `FormID` (`FormID`),
  CONSTRAINT `FormAccessFields_ibfk_1` FOREIGN KEY (`FormID`) REFERENCES `Form` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ===================================================================
-- Add missing columns to existing tables
-- ===================================================================

-- Add password column to Form table if it doesn't exist
SET @dbname = DATABASE();
SET @tablename = 'Form';
SET @columnname = 'password';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Add Slug column to FormAccessFields if it doesn't exist
SET @tablename = 'FormAccessFields';
SET @columnname = 'Slug';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL AFTER Label')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- ===================================================================
-- Add indexes if they don't exist
-- ===================================================================

-- Helper to check and create indexes safely
DELIMITER $$

DROP PROCEDURE IF EXISTS AddIndexIfNotExists$$
CREATE PROCEDURE AddIndexIfNotExists(
    IN tableName VARCHAR(128),
    IN indexName VARCHAR(128),
    IN indexDefinition VARCHAR(256)
)
BEGIN
    DECLARE indexExists INT;
    
    SELECT COUNT(*) INTO indexExists
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = tableName
      AND INDEX_NAME = indexName;
    
    IF indexExists = 0 THEN
        SET @sql = CONCAT('CREATE INDEX ', indexName, ' ON ', tableName, ' ', indexDefinition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

-- Add all performance indexes
CALL AddIndexIfNotExists('Form', 'idx_form_type_target', '(FormType, FormTarget)');
CALL AddIndexIfNotExists('Form', 'idx_form_status', '(FormStatus)');
CALL AddIndexIfNotExists('Section', 'idx_section_form', '(IDForm)');
CALL AddIndexIfNotExists('Question', 'idx_question_section', '(IDSection)');
CALL AddIndexIfNotExists('Question', 'idx_question_type', '(TypeQuestion)');
CALL AddIndexIfNotExists('FormAccessFields', 'idx_access_fields_order', '(FormID, OrderIndex)');
CALL AddIndexIfNotExists('EvaluationResponses', 'idx_responses_form_type_target', '(FormType, FormTarget, Semester)');
CALL AddIndexIfNotExists('EvaluationResponses', 'idx_responses_date', '(AnsweredAt)');
CALL AddIndexIfNotExists('Admin', 'idx_admin_username', '(username)');

-- Cleanup
DROP PROCEDURE IF EXISTS AddIndexIfNotExists;

-- ===================================================================
-- Verification
-- ===================================================================
-- After running this migration, verify with:
-- SHOW TABLES;
-- SHOW COLUMNS FROM Form;
-- SHOW COLUMNS FROM FormAccessFields;
-- SHOW INDEXES FROM Form;
-- ===================================================================

COMMIT;
