-- ===================================================================
-- Complete Database Schema for citcoder_Quality - REFERENCE ONLY
-- ===================================================================
-- ⚠️ WARNING: This file is for REFERENCE ONLY
-- DO NOT execute this on an existing database with data!
-- 
-- For existing databases, use: migrations/007_sync_to_complete_schema.sql
-- For new databases, this file can be used to create schema from scratch
-- ===================================================================
-- Updated: Dec 21, 2025
-- Includes all migrations up to 007
-- ===================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- ===================================================================
-- Table: Admin
-- Purpose: System administrators and their permissions
-- ===================================================================

CREATE TABLE IF NOT EXISTS `Admin` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `IDForm` int(11) DEFAULT NULL,
  `username` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `password` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `isCanCreate` tinyint(1) DEFAULT '0',
  `isCanDelete` tinyint(1) DEFAULT '0',
  `isCanUpdate` tinyint(1) DEFAULT '0',
  `isCanRead` tinyint(1) DEFAULT '0',
  `isCanGetAnalysis` tinyint(1) DEFAULT '0',
  `is_deleted` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`ID`),
  KEY `idx_admin_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ===================================================================
-- Table: FormTypes
-- Purpose: Define available form types (course, teacher, program, etc.)
-- ===================================================================

CREATE TABLE IF NOT EXISTS `FormTypes` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Icon` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Slug` (`Slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- Table: EvaluatorTypes
-- Purpose: Define available evaluator types (student, teacher, alumni, etc.)
-- ===================================================================

CREATE TABLE IF NOT EXISTS `EvaluatorTypes` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Slug` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `Icon` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`ID`),
  UNIQUE KEY `Slug` (`Slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- Table: FormType_EvaluatorType
-- Purpose: Many-to-many relationship defining allowed type combinations
-- ===================================================================

CREATE TABLE IF NOT EXISTS `FormType_EvaluatorType` (
  `FormTypeID` int(11) NOT NULL,
  `EvaluatorTypeID` int(11) NOT NULL,
  PRIMARY KEY (`FormTypeID`, `EvaluatorTypeID`),
  KEY `EvaluatorTypeID` (`EvaluatorTypeID`),
  CONSTRAINT `FormType_EvaluatorType_ibfk_1` FOREIGN KEY (`FormTypeID`) REFERENCES `FormTypes` (`ID`) ON DELETE CASCADE,
  CONSTRAINT `FormType_EvaluatorType_ibfk_2` FOREIGN KEY (`EvaluatorTypeID`) REFERENCES `EvaluatorTypes` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ===================================================================
-- Table: Form
-- Purpose: Form definitions and configurations
-- ===================================================================

CREATE TABLE IF NOT EXISTS `Form` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `Title` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `Description` text CHARACTER SET utf8 COLLATE utf8_unicode_ci,
  `FormStatus` enum('draft','published') CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT 'draft',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `FormType` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FormTarget` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `note` text CHARACTER SET utf8 COLLATE utf8_unicode_ci,
  `password` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `created_by` (`created_by`),
  KEY `idx_form_type_target` (`FormType`, `FormTarget`),
  KEY `idx_form_status` (`FormStatus`),
  CONSTRAINT `Form_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `Admin` (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ===================================================================
-- Table: Section
-- Purpose: Sections within forms (grouping of questions)
-- ===================================================================

CREATE TABLE IF NOT EXISTS `Section` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `IDForm` int(11) NOT NULL,
  `title` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`ID`),
  KEY `IDForm` (`IDForm`),
  KEY `idx_section_form` (`IDForm`),
  CONSTRAINT `Section_ibfk_1` FOREIGN KEY (`IDForm`) REFERENCES `Form` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ===================================================================
-- Table: Question
-- Purpose: Individual questions within sections
-- ===================================================================

CREATE TABLE IF NOT EXISTS `Question` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `IDSection` int(11) NOT NULL,
  `TypeQuestion` varchar(50) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `Choices` json DEFAULT NULL,
  `TitleQuestion` text CHARACTER SET utf8 COLLATE utf8_unicode_ci,
  PRIMARY KEY (`ID`),
  KEY `IDSection` (`IDSection`),
  KEY `idx_question_section` (`IDSection`),
  KEY `idx_question_type` (`TypeQuestion`),
  CONSTRAINT `Question_ibfk_1` FOREIGN KEY (`IDSection`) REFERENCES `Section` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ===================================================================
-- Table: FormAccessFields
-- Purpose: Dynamic metadata fields required for each form
-- ===================================================================

CREATE TABLE IF NOT EXISTS `FormAccessFields` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `FormID` int(11) NOT NULL,
  `Label` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `Slug` varchar(100) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
  `FieldType` enum('text','number','password','email','date') CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
  `IsRequired` tinyint(1) DEFAULT '0',
  `OrderIndex` int(11) DEFAULT '0',
  PRIMARY KEY (`ID`),
  KEY `FormID` (`FormID`),
  KEY `idx_access_fields_order` (`FormID`, `OrderIndex`),
  CONSTRAINT `FormAccessFields_ibfk_1` FOREIGN KEY (`FormID`) REFERENCES `Form` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- ===================================================================
-- Table: EvaluationResponses
-- Purpose: Stores all evaluation form submissions
-- ===================================================================

CREATE TABLE IF NOT EXISTS `EvaluationResponses` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `QuestionID` int(11) NOT NULL COMMENT 'Foreign key to questions table',
  `AnswerValue` json NOT NULL COMMENT 'Stores response data in JSON format',
  `AnsweredAt` datetime DEFAULT CURRENT_TIMESTAMP COMMENT 'Response timestamp',
  `FormType` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Form type slug',
  `FormTarget` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Evaluator type slug',
  `Semester` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Academic period',
  `Metadata` json NOT NULL COMMENT 'Additional context (student_id, course_id, etc.)',
  `teacher_id` varchar(50) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (json_unquote(json_extract(`Metadata`,'$.teacher_id'))) VIRTUAL,
  `course_id` varchar(50) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (json_unquote(json_extract(`Metadata`,'$.course_id'))) VIRTUAL,
  `student_id` varchar(50) COLLATE utf8mb4_unicode_ci GENERATED ALWAYS AS (json_unquote(json_extract(`Metadata`,'$.student_id'))) VIRTUAL,
  PRIMARY KEY (`ID`),
  KEY `idx_responses_form_type_target` (`FormType`, `FormTarget`, `Semester`),
  KEY `idx_responses_date` (`AnsweredAt`),
  KEY `idx_teacher_id` (`teacher_id`),
  KEY `idx_course_id` (`course_id`),
  KEY `idx_student_id` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

COMMIT;
