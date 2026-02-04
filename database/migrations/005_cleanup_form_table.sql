-- ===================================================================
-- Migration 005: Cleanup Form Table Duplicate Columns
-- ===================================================================
-- Purpose: Remove unused FormTypeID and EvaluatorTypeID columns from Form table.
-- ===================================================================

-- Drop foreign key constraints if they exist
-- Note: MySQL 5.7+ supports IF EXISTS for DROP FOREIGN KEY in some versions,
-- but consistent behavior requires checking information_schema or creating a procedure.
-- Since we cannot easily use DELIMITER in simple execution, we rely on a simplified approach
-- or just ignoring errors for cleanup tasks if they fail (which mysqli_multi_query might handle poorly).
-- However, we can use a direct block approach for safer execution.

-- Drop FK_Form_FormType
SET @dbname = DATABASE();
SET @tablename = 'Form';
SET @constraintname = 'FK_Form_FormType';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND CONSTRAINT_NAME = @constraintname
  ) > 0,
  CONCAT('ALTER TABLE ', @tablename, ' DROP FOREIGN KEY ', @constraintname),
  'SELECT 1'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Drop FK_Form_EvaluatorType
SET @constraintname = 'FK_Form_EvaluatorType';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND CONSTRAINT_NAME = @constraintname
  ) > 0,
  CONCAT('ALTER TABLE ', @tablename, ' DROP FOREIGN KEY ', @constraintname),
  'SELECT 1'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Drop FormTypeID column
SET @columnname = 'FormTypeID';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  CONCAT('ALTER TABLE ', @tablename, ' DROP COLUMN ', @columnname),
  'SELECT 1'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Drop EvaluatorTypeID column
SET @columnname = 'EvaluatorTypeID';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  CONCAT('ALTER TABLE ', @tablename, ' DROP COLUMN ', @columnname),
  'SELECT 1'
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

COMMIT;
