-- ===================================================================
-- Migration 007: Sync Existing Database to Complete Schema
-- ===================================================================

-- Add FormTypes table
CREATE TABLE IF NOT EXISTS `FormTypes` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Icon` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Slug` (`Slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add EvaluatorTypes table
CREATE TABLE IF NOT EXISTS `EvaluatorTypes` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Icon` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Slug` (`Slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add FormType_EvaluatorType table
CREATE TABLE IF NOT EXISTS `FormType_EvaluatorType` (
  `FormTypeID` int(11) NOT NULL,
  `EvaluatorTypeID` int(11) NOT NULL,
  PRIMARY KEY (`FormTypeID`, `EvaluatorTypeID`),
  KEY `EvaluatorTypeID` (`EvaluatorTypeID`),
  CONSTRAINT `FormType_EvaluatorType_ibfk_1` FOREIGN KEY (`FormTypeID`) REFERENCES `FormTypes` (`ID`) ON DELETE CASCADE,
  CONSTRAINT `FormType_EvaluatorType_ibfk_2` FOREIGN KEY (`EvaluatorTypeID`) REFERENCES `EvaluatorTypes` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add FormAccessFields table
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

-- Add columns using PREPARE pattern
SET @dbname = DATABASE();

-- Add password to Form
SET @tablename = 'Form';
SET @columnname = 'password';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL')
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add Slug to FormAccessFields
SET @tablename = 'FormAccessFields';
SET @columnname = 'Slug';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL AFTER Label')
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

COMMIT;
