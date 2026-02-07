-- Migration 017: Fix EvaluationResponses Schema and Data Consistency
-- Purpose: align the table with schema_complete.sql and user requirements
-- 1. Ensure Generated Columns use snake_case keys (student_id, course_id, teacher_id)
-- 2. Clean up any legacy or incorrect columns

-- Disable foreign key checks to avoid issues during alteration
SET FOREIGN_KEY_CHECKS=0;

-- 1. Drop existing generated columns (if they exist) to ensure clean state
-- We use a stored procedure to drop columns only if they exist to avoid errors
DROP PROCEDURE IF EXISTS DropEvaluationColumns;
DELIMITER //
CREATE PROCEDURE DropEvaluationColumns()
BEGIN
    -- DROP INDEXES FIRST (Crucial to avoid Duplicate Entry errors during column drop)
    IF EXISTS (SELECT * FROM information_schema.STATISTICS WHERE TABLE_NAME = 'EvaluationResponses' AND INDEX_NAME = 'idx_student_id') THEN
        DROP INDEX idx_student_id ON EvaluationResponses;
    END IF;
    IF EXISTS (SELECT * FROM information_schema.STATISTICS WHERE TABLE_NAME = 'EvaluationResponses' AND INDEX_NAME = 'idx_course_id') THEN
        DROP INDEX idx_course_id ON EvaluationResponses;
    END IF;
    IF EXISTS (SELECT * FROM information_schema.STATISTICS WHERE TABLE_NAME = 'EvaluationResponses' AND INDEX_NAME = 'idx_teacher_id') THEN
        DROP INDEX idx_teacher_id ON EvaluationResponses;
    END IF;
    IF EXISTS (SELECT * FROM information_schema.STATISTICS WHERE TABLE_NAME = 'EvaluationResponses' AND INDEX_NAME = 'idx_unique_student_response') THEN
        DROP INDEX idx_unique_student_response ON EvaluationResponses;
    END IF;

    -- DROP COLUMNS
    IF EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME = 'EvaluationResponses' AND COLUMN_NAME = 'IDStudent') THEN
        ALTER TABLE EvaluationResponses DROP COLUMN IDStudent;
    END IF;
    IF EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME = 'EvaluationResponses' AND COLUMN_NAME = 'IDCourse') THEN
        ALTER TABLE EvaluationResponses DROP COLUMN IDCourse;
    END IF;
    IF EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME = 'EvaluationResponses' AND COLUMN_NAME = 'student_id') THEN
        ALTER TABLE EvaluationResponses DROP COLUMN student_id;
    END IF;
    IF EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME = 'EvaluationResponses' AND COLUMN_NAME = 'course_id') THEN
        ALTER TABLE EvaluationResponses DROP COLUMN course_id;
    END IF;
    IF EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME = 'EvaluationResponses' AND COLUMN_NAME = 'teacher_id') THEN
        ALTER TABLE EvaluationResponses DROP COLUMN teacher_id;
    END IF;
END //
DELIMITER ;
CALL DropEvaluationColumns();
DROP PROCEDURE IF EXISTS DropEvaluationColumns;

-- 2. Re-create Columns as VIRTUAL generated columns (User Requirement)
-- They extract from Metadata using snake_case keys
ALTER TABLE EvaluationResponses 
ADD COLUMN teacher_id VARCHAR(50) COLLATE utf8mb4_unicode_ci 
GENERATED ALWAYS AS (json_unquote(json_extract(`Metadata`,'$.teacher_id'))) VIRTUAL;

ALTER TABLE EvaluationResponses 
ADD COLUMN course_id VARCHAR(50) COLLATE utf8mb4_unicode_ci 
GENERATED ALWAYS AS (json_unquote(json_extract(`Metadata`,'$.course_id'))) VIRTUAL;

ALTER TABLE EvaluationResponses 
ADD COLUMN student_id VARCHAR(50) COLLATE utf8mb4_unicode_ci 
GENERATED ALWAYS AS (json_unquote(json_extract(`Metadata`,'$.student_id'))) VIRTUAL;

-- 3. Re-create Indexes
CREATE INDEX idx_teacher_id ON EvaluationResponses(teacher_id);
CREATE INDEX idx_course_id ON EvaluationResponses(course_id);
CREATE INDEX idx_student_id ON EvaluationResponses(student_id);

-- Legacy index for duplicate checking (using the new columns)
CREATE INDEX idx_unique_check ON EvaluationResponses(FormType, student_id, course_id, Semester);

SET FOREIGN_KEY_CHECKS=1;

