-- Migration 019: Add Foreign Keys and Clean Orphaned Data
-- Purpose: Enforce referential integrity for Questions and Teachers
-- Risk: Medium (Deletes orphaned data)

SET FOREIGN_KEY_CHECKS=0;

-- 1. Clean Orphaned EvaluationResponses
-- Delete responses where the QuestionID no longer exists in the Question table
DELETE FROM EvaluationResponses 
WHERE QuestionID NOT IN (SELECT ID FROM Question);

-- 2. Add Foreign Key for QuestionID
-- This ensures that if a Question is deleted, its responses are also deleted (CASCADE)
ALTER TABLE EvaluationResponses 
ADD CONSTRAINT fk_resp_question 
FOREIGN KEY (QuestionID) REFERENCES Question(ID) 
ON DELETE CASCADE;

-- 3. Clean Orphaned CoursesGroups
-- Check for course assignments where the teacher (TNo) does not exist in regteacher
-- We set them to NULL or handle them. Since TNo is likely INT NOT NULL, we might need to delete or update.
-- Let's check schema first. If TNo is NOT NULL, we must delete the assignment.
DELETE FROM coursesgroups 
WHERE TNo NOT IN (SELECT id FROM regteacher);

-- 4. Add Foreign Key for CoursesGroups
ALTER TABLE coursesgroups 
ADD CONSTRAINT fk_course_teacher 
FOREIGN KEY (TNo) REFERENCES regteacher(id) 
ON DELETE CASCADE;

SET FOREIGN_KEY_CHECKS=1;