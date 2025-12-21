-- ===================================================================
-- Note: The Slug column was added in migration 003_add_slug_to_access_fields.sql
-- Schema: FormAccessFields (ID, FormID, Label, Slug, FieldType, IsRequired, OrderIndex)

-- ===================================================================
-- Form ID 1: Course Evaluation (student evaluating course)
-- Required Parameters: IDStudent, Semester, IDCourse, IDGroup
-- ===================================================================

INSERT INTO FormAccessFields (FormID, Label, Slug, FieldType, IsRequired, OrderIndex) VALUES
(1, 'رقم قيد الطالب', 'IDStudent', 'text', 1, 1),
(1, 'الفصل الدراسي', 'Semester', 'text', 1, 2),
(1, 'رمز المقرر', 'IDCourse', 'text', 1, 3),
(1, 'رقم المجموعة', 'IDGroup', 'number', 1, 4);

-- ===================================================================
-- Form ID 2: Teacher Evaluation (student evaluating teacher)
-- Required Parameters: IDStudent, Semester, IDCourse, IDGroup
-- Note: Teacher ID is derived internally, not passed via URL
-- ===================================================================

INSERT INTO FormAccessFields (FormID, Label, Slug, FieldType, IsRequired, OrderIndex) VALUES
(2, 'رقم قيد الطالب', 'IDStudent', 'text', 1, 1),
(2, 'الفصل الدراسي', 'Semester', 'text', 1, 2),
(2, 'رمز المقرر', 'IDCourse', 'text', 1, 3),
(2, 'رقم المجموعة', 'IDGroup', 'number', 1, 4);

-- ===================================================================
-- Form ID 4: Program Evaluation (student)
-- Required Parameters: IDStudent, Semester, AcademicSemesterId
-- ===================================================================

INSERT INTO FormAccessFields (FormID, Label, Slug, FieldType, IsRequired, OrderIndex) VALUES
(4, 'رقم قيد الطالب', 'IDStudent', 'text', 1, 1),
(4, 'الفصل الدراسي', 'Semester', 'text', 1, 2),
(4, 'رقم الفصل الأكاديمي', 'AcademicSemesterId', 'number', 1, 3);

-- ===================================================================
-- Form ID 5: Facility Evaluation (student)
-- Required Parameters: IDStudent, Semester
-- ===================================================================

INSERT INTO FormAccessFields (FormID, Label, Slug, FieldType, IsRequired, OrderIndex) VALUES
(5, 'رقم قيد الطالب', 'IDStudent', 'text', 1, 1),
(5, 'الفصل الدراسي', 'Semester', 'text', 1, 2);

-- ===================================================================
-- Form ID 6: Graduate Program Evaluation (alumni evaluating program)
-- These are typically accessed manually, so basic fields are provided
-- You may customize these based on actual alumni data collection needs
-- ===================================================================

INSERT INTO FormAccessFields (FormID, Label, Slug, FieldType, IsRequired, OrderIndex) VALUES
(6, 'اسم الخريج', 'GraduateName', 'text', 1, 1),
(6, 'سنة التخرج', 'GraduationYear', 'number', 1, 2),
(6, 'التخصص', 'Specialization', 'text', 1, 3);

-- ===================================================================
-- End of FormAccessFields Population
-- ===================================================================

COMMIT;
