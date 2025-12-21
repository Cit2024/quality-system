-- ===================================================================
-- Migration 005: Cleanup Form Table Duplicate Columns
-- ===================================================================
-- Purpose: Remove unused FormTypeID and EvaluatorTypeID columns from Form table.
-- These FK columns were added but never used. The system uses the legacy
-- string-based FormType and FormTarget columns instead.
-- ===================================================================

-- Create procedure to safely drop foreign key if it exists
DELIMITER $$

DROP PROCEDURE IF EXISTS DropFKIfExists$$
CREATE PROCEDURE DropFKIfExists(
    IN tableName VARCHAR(64),
    IN constraintName VARCHAR(64)
)
BEGIN
    DECLARE constraintExists INT;
    
    SELECT COUNT(*) INTO constraintExists
    FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = tableName
      AND CONSTRAINT_NAME = constraintName
      AND CONSTRAINT_TYPE = 'FOREIGN KEY';
    
    IF constraintExists > 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', tableName, '` DROP FOREIGN KEY `', constraintName, '`');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

-- Drop foreign key constraints if they exist
CALL DropFKIfExists('Form', 'FK_Form_FormType');
CALL DropFKIfExists('Form', 'FK_Form_EvaluatorType');

-- Drop procedure after use
DROP PROCEDURE IF EXISTS DropFKIfExists;

-- Create procedure to safely drop column if it exists
DELIMITER $$

DROP PROCEDURE IF EXISTS DropColumnIfExists$$
CREATE PROCEDURE DropColumnIfExists(
    IN tableName VARCHAR(64),
    IN columnName VARCHAR(64)
)
BEGIN
    DECLARE columnExists INT;
    
    SELECT COUNT(*) INTO columnExists
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = tableName
      AND COLUMN_NAME = columnName;
    
    IF columnExists > 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', tableName, '` DROP COLUMN `', columnName, '`');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

-- Drop the unused FK columns if they exist
CALL DropColumnIfExists('Form', 'FormTypeID');
CALL DropColumnIfExists('Form', 'EvaluatorTypeID');

-- Drop procedure after use
DROP PROCEDURE IF EXISTS DropColumnIfExists;

-- ===================================================================
-- Verification Query (run after migration to confirm):
-- SHOW COLUMNS FROM Form;
-- Expected: Should NOT see FormTypeID or EvaluatorTypeID columns
-- ===================================================================

COMMIT;
