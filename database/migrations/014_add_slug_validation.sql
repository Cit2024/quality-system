-- Migration 014: Add Slug Validation
-- Ensure slugs are unique per form in FormAccessFields

SET @dbname = DATABASE();
SET @tablename = 'FormAccessFields';
SET @constraintname = 'unique_slug_per_form';

-- Only add the constraint if it doesn't exist
SET @preparedStatement = (SELECT IF(
  (SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS 
   WHERE TABLE_SCHEMA = @dbname 
     AND TABLE_NAME = @tablename 
     AND CONSTRAINT_NAME = @constraintname) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD CONSTRAINT ', @constraintname, ' UNIQUE KEY (FormID, Slug)')
));

PREPARE stmt FROM @preparedStatement;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
