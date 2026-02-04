-- Add virtual columns for JSON keys to enable indexing
-- Check if columns exist before adding (MySQL specific syntax usually requires procedure, but simple ALTER IGNORE or checking manually is common)

-- 1. Add IDStudent virtual column
ALTER TABLE EvaluationResponses ADD COLUMN IDStudent VARCHAR(50) AS (JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.IDStudent'))) VIRTUAL;

-- 2. Add IDCourse virtual column
ALTER TABLE EvaluationResponses ADD COLUMN IDCourse VARCHAR(50) AS (JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.IDCourse'))) VIRTUAL;

-- 3. Add Unique Index
-- This prevents a student from submitting multiple responses for the same question in the same context
CREATE UNIQUE INDEX idx_unique_student_response 
ON EvaluationResponses (FormType, Semester, IDStudent, IDCourse, QuestionID);
