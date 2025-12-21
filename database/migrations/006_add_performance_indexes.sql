-- ===================================================================
-- Migration 006: Add Performance Indexes
-- ===================================================================
-- Purpose: Add indexes to improve query performance on frequently
-- accessed columns and foreign key relationships
-- ===================================================================

-- Create procedure to safely add index if it doesn't exist
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
        SET @sql = CONCAT('CREATE INDEX ', indexName, ' ON `', tableName, '` ', indexDefinition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$

DELIMITER ;

-- Add all performance indexes
CALL AddIndexIfNotExists('Form', 'idx_form_type_target', '(`FormType`, `FormTarget`)');
CALL AddIndexIfNotExists('Form', 'idx_form_status', '(`FormStatus`)');
CALL AddIndexIfNotExists('Section', 'idx_section_form', '(`IDForm`)');
CALL AddIndexIfNotExists('Question', 'idx_question_section', '(`IDSection`)');
CALL AddIndexIfNotExists('Question', 'idx_question_type', '(`TypeQuestion`)');
CALL AddIndexIfNotExists('FormAccessFields', 'idx_access_fields_order', '(`FormID`, `OrderIndex`)');
CALL AddIndexIfNotExists('EvaluationResponses', 'idx_responses_form_type_target', '(`FormType`, `FormTarget`, `Semester`)');
CALL AddIndexIfNotExists('EvaluationResponses', 'idx_responses_date', '(`AnsweredAt`)');
CALL AddIndexIfNotExists('Admin', 'idx_admin_username', '(`username`)');

-- Drop procedure after use
DROP PROCEDURE IF EXISTS AddIndexIfNotExists;

-- ===================================================================
-- Verification Query (run after migration):
-- SHOW INDEXES FROM Form;
-- SHOW INDEXES FROM EvaluationResponses;
-- ===================================================================

COMMIT;
