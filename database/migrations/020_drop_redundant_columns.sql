-- Migration 020: Schema Cleanup and Consistency
-- Purpose: Remove redundant ID columns and enforce Slug integrity
-- Risk: Low (Columns verified as unused in code)

SET FOREIGN_KEY_CHECKS=0;

-- 1. Drop Redundant Columns from Form table
-- We use a stored procedure to avoid errors if they are already gone
DROP PROCEDURE IF EXISTS DropFormIds;
DELIMITER //
CREATE PROCEDURE DropFormIds()
BEGIN
    IF EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME = 'Form' AND COLUMN_NAME = 'FormTypeID') THEN
        ALTER TABLE Form DROP COLUMN FormTypeID;
    END IF;
    IF EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME = 'Form' AND COLUMN_NAME = 'EvaluatorTypeID') THEN
        ALTER TABLE Form DROP COLUMN EvaluatorTypeID;
    END IF;
END //
DELIMITER ;
CALL DropFormIds();
DROP PROCEDURE IF EXISTS DropFormIds;

-- 2. Enforce Integrity on Slugs (The New Source of Truth)
-- Ensure FormType and FormTarget point to valid entries in their definition tables
-- Note: Assuming FormTypes.Slug and EvaluatorTypes.Slug are UNIQUE/Indexed (they should be)

-- Fix any invalid data first (Set to NULL if not found)
UPDATE Form SET FormType = NULL WHERE FormType NOT IN (SELECT Slug FROM FormTypes);
UPDATE Form SET FormTarget = NULL WHERE FormTarget NOT IN (SELECT Slug FROM EvaluatorTypes);

-- Add Foreign Keys
-- This ensures we can't type a random string into FormType
ALTER TABLE Form 
ADD CONSTRAINT fk_form_type_slug 
FOREIGN KEY (FormType) REFERENCES FormTypes(Slug) 
ON DELETE SET NULL 
ON UPDATE CASCADE;

ALTER TABLE Form 
ADD CONSTRAINT fk_form_target_slug 
FOREIGN KEY (FormTarget) REFERENCES EvaluatorTypes(Slug) 
ON DELETE SET NULL 
ON UPDATE CASCADE;

SET FOREIGN_KEY_CHECKS=1;