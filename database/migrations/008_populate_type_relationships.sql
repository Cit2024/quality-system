-- ===================================================================
-- Migration 008: Populate FormType_EvaluatorType Relationships
-- ===================================================================
-- Purpose: Define valid form type and evaluator type combinations
-- Based on existing forms in the database
-- ===================================================================

-- First, ensure we have the base types populated
-- (These should have been created by migration 001, but we'll use INSERT IGNORE for safety)

-- Populate FormTypes
INSERT IGNORE INTO `FormTypes` (`Slug`, `Name`, `Icon`) VALUES
('course_evaluation', 'تقييم المقرر', 'book'),
('teacher_evaluation', 'تقييم الأستاذ', 'person'),
('program_evaluation', 'تقييم البرنامج', 'school'),
('facility_evaluation', 'تقييم المرافق', 'business'),
('leaders_evaluation', 'تقييم القيادات', 'supervisor_account');

-- Populate EvaluatorTypes
INSERT IGNORE INTO `EvaluatorTypes` (`Slug`, `Name`, `Icon`) VALUES
('student', 'طالب', 'school'),
('teacher', 'أستاذ', 'person'),
('employer', 'موظف', 'work'),
('alumni', 'خريج', 'school');

-- ===================================================================
-- Define allowed combinations based on existing forms:
-- Form 1: course_evaluation → student
-- Form 2: teacher_evaluation → student
-- Form 4: program_evaluation → student, alumni
-- Form 5: facility_evaluation → student, teacher, employer
-- Form 9: leaders_evaluation → teacher
-- ===================================================================

-- Clear any existing relationships first (in case migration is re-run)
DELETE FROM `FormType_EvaluatorType`;

-- Insert valid combinations
INSERT INTO `FormType_EvaluatorType` (`FormTypeID`, `EvaluatorTypeID`)
SELECT ft.ID, et.ID
FROM `FormTypes` ft
CROSS JOIN `EvaluatorTypes` et
WHERE 
  -- course_evaluation can be done by student
  (ft.Slug = 'course_evaluation' AND et.Slug = 'student')
  
  -- teacher_evaluation can be done by student
  OR (ft.Slug = 'teacher_evaluation' AND et.Slug = 'student')
  
  -- program_evaluation can be done by student or alumni
  OR (ft.Slug = 'program_evaluation' AND et.Slug IN ('student', 'alumni'))
  
  -- facility_evaluation can be done by student, teacher, or employer
  OR (ft.Slug = 'facility_evaluation' AND et.Slug IN ('student', 'teacher', 'employer'))
  
  -- leaders_evaluation can be done by teacher
  OR (ft.Slug = 'leaders_evaluation' AND et.Slug = 'teacher');

-- ===================================================================
-- Verification Query:
-- SELECT ft.Name as 'Form Type', et.Name as 'Evaluator Type'
-- FROM FormTypes ft
-- JOIN FormType_EvaluatorType fte ON ft.ID = fte.FormTypeID
-- JOIN EvaluatorTypes et ON fte.EvaluatorTypeID = et.ID
-- ORDER BY ft.Name, et.Name;
-- ===================================================================

COMMIT;
