-- ===================================================================
-- Migration 009: Convert Icons to Font Awesome
-- ===================================================================
-- Purpose: Convert icon storage from SVG file paths to Font Awesome
-- class names for easier maintenance and consistency
-- ===================================================================

-- Update FormTypes icons to Font Awesome classes
UPDATE `FormTypes` SET `Icon` = 'fa-solid fa-book-bookmark' WHERE `Slug` = 'course_evaluation';
UPDATE `FormTypes` SET `Icon` = 'fa-solid fa-person-chalkboard' WHERE `Slug` = 'teacher_evaluation';
UPDATE `FormTypes` SET `Icon` = 'fa-solid fa-layer-group' WHERE `Slug` = 'program_evaluation';
UPDATE `FormTypes` SET `Icon` = 'fa-solid fa-building' WHERE `Slug` = 'facility_evaluation';
UPDATE `FormTypes` SET `Icon` = 'fa-solid fa-user-tie' WHERE `Slug` = 'leaders_evaluation';

-- Update EvaluatorTypes icons to Font Awesome classes
UPDATE `EvaluatorTypes` SET `Icon` = 'fa-solid fa-graduation-cap' WHERE `Slug` = 'student';
UPDATE `EvaluatorTypes` SET `Icon` = 'fa-solid fa-chalkboard-user' WHERE `Slug` = 'teacher';
UPDATE `EvaluatorTypes` SET `Icon` = 'fa-solid fa-user-tie' WHERE `Slug` = 'admin';
UPDATE `EvaluatorTypes` SET `Icon` = 'fa-solid fa-user-graduate' WHERE `Slug` = 'alumni';
UPDATE `EvaluatorTypes` SET `Icon` = 'fa-solid fa-briefcase' WHERE `Slug` = 'employer';

-- ===================================================================
-- Verification Query:
-- SELECT Slug, Icon FROM FormTypes;
-- SELECT Slug, Icon FROM EvaluatorTypes;
-- ===================================================================

COMMIT;
