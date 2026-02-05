CREATE TABLE IF NOT EXISTS `Form` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `FormType` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FormTarget` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Title` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Semester` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `IsActive` tinyint(1) DEFAULT '1',
  `FormStatus` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'draft',
  `note` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Section` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `IDForm` int(11) NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `OrderIndex` int(11) DEFAULT '0',
  PRIMARY KEY (`ID`),
  KEY `IDForm` (`IDForm`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Question` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `IDSection` int(11) NOT NULL,
  `TitleQuestion` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `TypeQuestion` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Choices` json DEFAULT NULL,
  `OrderIndex` int(11) DEFAULT '0',
  PRIMARY KEY (`ID`),
  KEY `IDSection` (`IDSection`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `EvaluationResponses` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `FormType` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `FormTarget` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Semester` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `AnsweredAt` datetime DEFAULT NULL,
  `QuestionID` int(11) DEFAULT NULL,
  `AnswerValue` json DEFAULT NULL,
  `Metadata` json DEFAULT NULL,
  `teacher_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `course_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `student_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `Admin` (
  `ID` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `isCanCreate` tinyint(1) DEFAULT '0',
  `isCanDelete` tinyint(1) DEFAULT '0',
  `isCanUpdate` tinyint(1) DEFAULT '0',
  `isCanRead` tinyint(1) DEFAULT '0',
  `isCanGetAnalysis` tinyint(1) DEFAULT '0',
  `is_deleted` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`ID`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `teachers_evaluation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `zaman` (
  `ZamanNo` int(11) NOT NULL AUTO_INCREMENT,
  `ZamanName` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`ZamanNo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `zaman` (ZamanNo, ZamanName) VALUES (1, 'Spring 2026');

CREATE TABLE IF NOT EXISTS `tanzil` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `KidNo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ZamanNo` int(11) NOT NULL,
  `MadaNo` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `mawad` (
  `MadaNo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `MadaName` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`MadaNo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sprofiles` (
  `KidNo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `KesmNo` int(11) NOT NULL,
  PRIMARY KEY (`KidNo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `divitions` (
  `KesmNo` int(11) NOT NULL AUTO_INCREMENT,
  `dname` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`KesmNo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `regteacher` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `coursesgroups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ZamanNo` int(11) NOT NULL,
  `MadaNo` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `GNo` int(11) NOT NULL,
  `TNo` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
