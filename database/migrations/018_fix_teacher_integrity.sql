-- Migration 018: Fix Teacher Integrity
-- Purpose: Link local login users (teachers_evaluation) to external data (regteacher)
-- Risk: High (Modifies auth table structure)

SET FOREIGN_KEY_CHECKS=0;

-- 1. Add the linking column
-- We use NULL so existing rows are valid (though unconnected) initially
DROP PROCEDURE IF EXISTS AddRegTeacherID;
DELIMITER //
CREATE PROCEDURE AddRegTeacherID()
BEGIN
    IF NOT EXISTS (SELECT * FROM information_schema.COLUMNS WHERE TABLE_NAME = 'teachers_evaluation' AND COLUMN_NAME = 'RegTeacherID') THEN
        ALTER TABLE teachers_evaluation ADD COLUMN RegTeacherID INT NULL;
    END IF;
END //
DELIMITER ;
CALL AddRegTeacherID();
DROP PROCEDURE IF EXISTS AddRegTeacherID;

-- 2. Data Migration (Best Effort)
-- Try to link admins/teachers based on 'name' if it matches exactly
-- This is a one-time setup help.
UPDATE teachers_evaluation te
INNER JOIN regteacher rt ON te.name = rt.name
SET te.RegTeacherID = rt.id
WHERE te.RegTeacherID IS NULL;

-- 3. Add Foreign Key
-- Ensure we valid IDs only (cleanup invalid ones if any, though unlikely with the JOIN above)
UPDATE teachers_evaluation SET RegTeacherID = NULL WHERE RegTeacherID NOT IN (SELECT id FROM regteacher);

ALTER TABLE teachers_evaluation 
ADD CONSTRAINT fk_teacher_link 
FOREIGN KEY (RegTeacherID) REFERENCES regteacher(id) 
ON DELETE SET NULL;

SET FOREIGN_KEY_CHECKS=1;