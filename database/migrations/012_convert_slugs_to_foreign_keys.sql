-- Migration 012: Convert String Slugs to Foreign Keys
-- This migration adds FK columns to the Form table and populates them from existing slug values

-- Step 1: Add new FK columns
ALTER TABLE Form ADD COLUMN FormTypeID INT(11) NULL AFTER FormType;
ALTER TABLE Form ADD COLUMN EvaluatorTypeID INT(11) NULL AFTER FormTarget;

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

-- Step 4: Add foreign key constraints
ALTER TABLE Form 
ADD CONSTRAINT fk_form_formtype 
FOREIGN KEY (FormTypeID) REFERENCES FormTypes(ID) ON DELETE SET NULL;

ALTER TABLE Form 
ADD CONSTRAINT fk_form_evaluatortype 
FOREIGN KEY (EvaluatorTypeID) REFERENCES EvaluatorTypes(ID) ON DELETE SET NULL;

-- Step 5: Add indexes for performance
CREATE INDEX idx_form_type_id ON Form(FormTypeID);
CREATE INDEX idx_evaluator_type_id ON Form(EvaluatorTypeID);

-- NOTE: The old FormType and FormTarget columns are kept for backward compatibility
-- After verifying all code works with the new FK columns, drop them in a future migration
