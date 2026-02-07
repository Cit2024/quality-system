-- ============================================================================
-- Migration 021: Create Missing Support Tables
-- Purpose: Add FormProcessingRules and SystemMessages tables
-- Date: 2026-02-07
-- ============================================================================

SET FOREIGN_KEY_CHECKS=0;

SELECT '============================================' as '';
SELECT 'CREATING MISSING SUPPORT TABLES' as '';
SELECT '============================================' as '';
SELECT '' as '';

-- ============================================================================
-- TABLE 1: FormProcessingRules
-- ============================================================================
SELECT '📋 Creating FormProcessingRules Table' as '';
SELECT '-------------------------------------------' as '';

CREATE TABLE IF NOT EXISTS `FormProcessingRules` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `FormType` varchar(50) NOT NULL COMMENT 'References FormTypes.Slug',
  `FormTarget` varchar(50) NOT NULL COMMENT 'References EvaluatorTypes.Slug',
  `RuleClass` varchar(100) NOT NULL COMMENT 'PHP class name for processing rule',
  `RuleConfig` json DEFAULT NULL COMMENT 'Configuration for the rule',
  `OrderIndex` int(11) DEFAULT '0' COMMENT 'Execution order',
  `IsActive` tinyint(1) DEFAULT '1' COMMENT 'Enable/disable rule',
  `CreatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  KEY `idx_form_rules` (`FormType`, `FormTarget`, `OrderIndex`),
  KEY `idx_active` (`IsActive`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT '✅ FormProcessingRules table created' as 'Result';

-- Insert Default Rules
INSERT IGNORE INTO `FormProcessingRules` (`FormType`, `FormTarget`, `RuleClass`, `RuleConfig`, `OrderIndex`) VALUES
('teacher_evaluation', 'student', 'StudentTeacherLookupRule', '{"required_fields": ["Semester", "IDCourse", "IDGroup"], "target_field": "teacher_id", "source_fields": {"semester": "Semester", "course": "IDCourse", "group": "IDGroup"}}', 10),
('course_evaluation', 'student', 'StudentTeacherLookupRule', '{"required_fields": ["Semester", "IDCourse", "IDGroup"], "target_field": "teacher_id", "source_fields": {"semester": "Semester", "course": "IDCourse", "group": "IDGroup"}}', 10),
('teacher_evaluation', 'student', 'UniqueSubmissionRule', '{"unique_keys": ["student_id", "course_id", "Semester"]}', 20),
('course_evaluation', 'student', 'UniqueSubmissionRule', '{"unique_keys": ["student_id", "course_id", "Semester"]}', 20);

SELECT CONCAT('✅ Inserted ', ROW_COUNT(), ' default processing rules') as 'Result';

SELECT '' as '';

-- ============================================================================
-- TABLE 2: SystemMessages
-- ============================================================================
SELECT '📋 Creating SystemMessages Table' as '';
SELECT '-------------------------------------------' as '';

CREATE TABLE IF NOT EXISTS `SystemMessages` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Key` varchar(100) NOT NULL COMMENT 'Unique message identifier',
  `Message` text NOT NULL COMMENT 'Message text (supports Arabic)',
  `Type` enum('success', 'error', 'info', 'warning') DEFAULT 'info',
  `CreatedAt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `key_unique` (`Key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT '✅ SystemMessages table created' as 'Result';

-- Insert default messages
INSERT INTO `SystemMessages` (`Key`, `Message`, `Type`) VALUES
('submission_success', 'تم استلام تقييمك بنجاح', 'success'),
('submission_duplicate', 'لقد قمت بإرسال هذا التقييم مسبقاً', 'warning'),
('form_id_missing', 'رقم النموذج مفقود', 'error'),
('form_not_found', 'النموذج غير موجود أو غير منشور', 'error'),
('field_required', 'الحقل {field} مطلوب', 'error'),
('field_number_required', 'الحقل {field} يجب أن يكون رقماً', 'error'),
('metadata_size_exceeded', 'حجم البيانات الوصفية كبير جداً', 'error'),
('cit_connection_error', 'خطأ في الاتصال بقاعدة بيانات الجامعة', 'error'),
('missing_course_details', 'بيانات المقرر مفقودة (الفصل، المقرر، أو المجموعة)', 'error'),
('teacher_not_found', 'لم يتم العثور على عضو هيئة تدريس لهذا المقرر', 'error'),
('database_error', 'حدث خطأ في قاعدة البيانات', 'error'),
('unknown_error', 'حدث خطأ غير معروف', 'error')
ON DUPLICATE KEY UPDATE `Message` = VALUES(`Message`), `Type` = VALUES(`Type`);

SELECT CONCAT('✅ Inserted ', ROW_COUNT(), ' system messages') as 'Result';

SELECT '' as '';

-- ============================================================================
-- VERIFICATION
-- ============================================================================
SELECT '============================================' as '';
SELECT 'VERIFICATION' as '';
SELECT '============================================' as '';

-- Check FormProcessingRules
SELECT 
    COUNT(*) as 'Processing Rules Count',
    CASE 
        WHEN COUNT(*) >= 4 THEN '✅ PASS'
        ELSE '⚠️  WARNING'
    END as 'Status'
FROM FormProcessingRules;

-- Check SystemMessages
SELECT 
    COUNT(*) as 'System Messages Count',
    CASE 
        WHEN COUNT(*) >= 12 THEN '✅ PASS'
        ELSE '⚠️  WARNING'
    END as 'Status'
FROM SystemMessages;

SELECT '' as '';
SELECT '✅ MIGRATION 021 COMPLETE' as '';
SELECT '============================================' as '';

SET FOREIGN_KEY_CHECKS=1;

