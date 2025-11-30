-- Create FormTypes table
CREATE TABLE IF NOT EXISTS FormTypes (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    Slug VARCHAR(50) NOT NULL UNIQUE,
    Name VARCHAR(100) NOT NULL,
    Icon VARCHAR(255)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create EvaluatorTypes table
CREATE TABLE IF NOT EXISTS EvaluatorTypes (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    Slug VARCHAR(50) NOT NULL UNIQUE,
    Name VARCHAR(100) NOT NULL,
    Icon VARCHAR(255)
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create FormAccessFields table
CREATE TABLE IF NOT EXISTS FormAccessFields (
    ID INT AUTO_INCREMENT PRIMARY KEY,
    FormID INT NOT NULL,
    Label VARCHAR(255) NOT NULL,
    FieldType ENUM('text', 'number', 'password', 'email', 'date') NOT NULL,
    IsRequired BOOLEAN DEFAULT FALSE,
    ValidationValue VARCHAR(255),
    OrderIndex INT DEFAULT 0,
    FOREIGN KEY (FormID) REFERENCES Form(ID) ON DELETE CASCADE
) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Add columns to Form table
-- Note: If these columns already exist, this will fail. 
-- In a production environment, you would check for existence first.

ALTER TABLE Form ADD COLUMN FormTypeID INT;
ALTER TABLE Form ADD COLUMN EvaluatorTypeID INT;

-- Add Foreign Keys
ALTER TABLE Form ADD CONSTRAINT FK_Form_FormType FOREIGN KEY (FormTypeID) REFERENCES FormTypes(ID);
ALTER TABLE Form ADD CONSTRAINT FK_Form_EvaluatorType FOREIGN KEY (EvaluatorTypeID) REFERENCES EvaluatorTypes(ID);
