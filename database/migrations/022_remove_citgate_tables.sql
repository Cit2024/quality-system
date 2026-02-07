-- ============================================================================
-- Migration 022: Remove Citgate Tables from Quality Database
-- ============================================================================
-- SIMPLIFIED VERSION - No FK constraints to drop
-- 
-- Based on schema analysis: citcoder_Quality.sql has NO foreign keys
-- referencing Citgate tables, so we can drop them directly.
-- ============================================================================

USE citcoder_Quality;

-- Drop views first (if they exist)
DROP VIEW IF EXISTS AuditLog_Recent;
DROP VIEW IF EXISTS AuditLog_Failures;

-- Drop Citgate tables (if they exist)
DROP TABLE IF EXISTS AuditLog;
DROP TABLE IF EXISTS coursesgroups;
DROP TABLE IF EXISTS teachers_evaluation;
DROP TABLE IF EXISTS tanzil;
DROP TABLE IF EXISTS sprofiles;
DROP TABLE IF EXISTS zaman;
DROP TABLE IF EXISTS divitions;
DROP TABLE IF EXISTS mawad;
DROP TABLE IF EXISTS regteacher;

-- ============================================================================
-- VERIFICATION - Run this separately to check results
-- ============================================================================

SELECT COUNT(*) as RemainingCitgateTables
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'citcoder_Quality'
  AND TABLE_NAME IN (
      'regteacher', 'teachers_evaluation', 'coursesgroups',
      'tanzil', 'sprofiles', 'zaman', 'divitions', 'mawad', 'AuditLog'
  );

-- Expected result: 0
