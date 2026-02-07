-- ============================================================================
-- Migration 023: Add Indices to Virtual Columns (Manual)
-- ============================================================================
-- Run these commands one by one in phpMyAdmin
-- If you get "Duplicate key name" error, that's fine (index already exists)
-- ============================================================================

USE citcoder_Quality;

-- Command 1: Index for teacher_id
CREATE INDEX idx_virtual_teacher ON EvaluationResponses(teacher_id);

-- Command 2: Index for course_id
CREATE INDEX idx_virtual_course ON EvaluationResponses(course_id);

-- Command 3: Index for student_id
CREATE INDEX idx_virtual_student ON EvaluationResponses(student_id);

-- Command 4: Verify indices
SHOW INDEX FROM EvaluationResponses;

