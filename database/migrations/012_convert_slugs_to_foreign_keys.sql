-- Migration 012: Convert String Slugs to Foreign Keys
-- This migration adds FK columns to the Form table and populates them from existing slug values

SET @dbname = DATABASE();

-- Step 1: Add new FK columns if they don't exist
SET @tablename = 'Form';
SET @columnname = 'FormTypeID';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' INT(11) NULL AFTER FormType')
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @columnname = 'EvaluatorTypeID';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND COLUMN_NAME = @columnname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' INT(11) NULL AFTER FormTarget')
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 2: Populate FormTypeID from FormType slug
UPDATE Form f
INNER JOIN FormTypes ft ON f.FormType = ft.Slug
SET f.FormTypeID = ft.ID
WHERE f.FormType IS NOT NULL;

-- Step 3: Populate EvaluatorTypeID from FormTarget slug  
UPDATE Form f
INNER JOIN EvaluatorTypes et ON f.FormTarget = et.Slug
SET f.EvaluatorTypeID = et.ID
WHERE f.FormTarget IS NOT NULL;

-- Step 4: Add foreign key constraints safely
SET @constraintname = 'fk_form_formtype';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND CONSTRAINT_NAME = @constraintname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD CONSTRAINT ', @constraintname, ' FOREIGN KEY (FormTypeID) REFERENCES FormTypes(ID) ON DELETE SET NULL')
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @constraintname = 'fk_form_evaluatortype';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND CONSTRAINT_NAME = @constraintname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD CONSTRAINT ', @constraintname, ' FOREIGN KEY (EvaluatorTypeID) REFERENCES EvaluatorTypes(ID) ON DELETE SET NULL')
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Step 5: Add indexes for performance
SET @indexname = 'idx_form_type_id';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = @indexname) > 0,
  'SELECT 1',
  CONCAT('CREATE INDEX ', @indexname, ' ON ', @tablename, '(FormTypeID)')
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @indexname = 'idx_evaluator_type_id';
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = @dbname AND TABLE_NAME = @tablename AND INDEX_NAME = @indexname) > 0,
  'SELECT 1',
  CONCAT('CREATE INDEX ', @indexname, ' ON ', @tablename, '(EvaluatorTypeID)')
));
PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

COMMIT;
