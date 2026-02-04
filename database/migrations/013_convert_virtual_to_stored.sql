-- Migration 013: Convert Virtual Columns to STORED
-- This migration converts virtual generated columns to STORED for better query performance
-- Virtual columns are computed on-the-fly, STORED columns are pre-computed and indexed

-- Step 1: Drop existing virtual columns
ALTER TABLE EvaluationResponses DROP COLUMN teacher_id;
ALTER TABLE EvaluationResponses DROP COLUMN course_id;
ALTER TABLE EvaluationResponses DROP COLUMN student_id;

-- Step 2: Re-add columns as STORED instead of VIRTUAL
ALTER TABLE EvaluationResponses 
ADD COLUMN teacher_id VARCHAR(50) 
AS (JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.teacher_id'))) STORED;

ALTER TABLE EvaluationResponses 
ADD COLUMN course_id VARCHAR(50) 
AS (JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.course_id'))) STORED;

ALTER TABLE EvaluationResponses 
ADD COLUMN student_id VARCHAR(50) 
AS (JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.student_id'))) STORED;

-- Step 3: Recreate indexes (they were dropped with the columns)
CREATE INDEX idx_teacher_id ON EvaluationResponses(teacher_id);
CREATE INDEX idx_course_id ON EvaluationResponses(course_id);
CREATE INDEX idx_student_id ON EvaluationResponses(student_id);

-- Performance Note: STORED columns take up disk space but provide faster queries
-- VIRTUAL columns save space but compute on every query
