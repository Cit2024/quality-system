-- Add password column to Form table
SET @dbname = DATABASE();
SET @tablename = 'Form';
SET @columnname = 'password';
SET @preparedStatement = (SELECT IF(
  (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE
      TABLE_SCHEMA = @dbname
      AND TABLE_NAME = @tablename
      AND COLUMN_NAME = @columnname
  ) > 0,
  'SELECT 1',
  CONCAT('ALTER TABLE ', @tablename, ' ADD COLUMN ', @columnname, ' VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL')
));
PREPARE alterIfNotExists FROM @preparedStatement;
EXECUTE alterIfNotExists;
DEALLOCATE PREPARE alterIfNotExists;

-- Create FormAccessFields table
CREATE TABLE IF NOT EXISTS FormAccessFields (
    ID INT(11) NOT NULL AUTO_INCREMENT,
    FormID INT(11) NOT NULL,
    Label VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
    FieldType ENUM('text','number','password','email','date') CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
    IsRequired TINYINT(1) DEFAULT '0',
    OrderIndex INT(11) DEFAULT '0',
    PRIMARY KEY (ID),
    KEY FormID (FormID),
    CONSTRAINT FormAccessFields_ibfk_1 FOREIGN KEY (FormID) REFERENCES Form (ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- Create FormType_EvaluatorType table
CREATE TABLE IF NOT EXISTS FormType_EvaluatorType (
    FormTypeID INT NOT NULL,
    EvaluatorTypeID INT NOT NULL,
    PRIMARY KEY (FormTypeID, EvaluatorTypeID),
    FOREIGN KEY (FormTypeID) REFERENCES FormTypes(ID) ON DELETE CASCADE,
    FOREIGN KEY (EvaluatorTypeID) REFERENCES EvaluatorTypes(ID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;