<?php
require_once __DIR__ . '/../../config/DbConnection.php';

echo "Starting migration: Adding unique index for race condition protection...\n";

try {
    // 1. Check if IDStudent column exists
    $checkCol = $con->query("SHOW COLUMNS FROM EvaluationResponses LIKE 'IDStudent'");
    if ($checkCol->num_rows == 0) {
        echo "Adding virtual column IDStudent...\n";
        $sql = "ALTER TABLE EvaluationResponses ADD COLUMN IDStudent VARCHAR(50) AS (JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.IDStudent'))) VIRTUAL";
        if (!$con->query($sql)) {
            throw new Exception("Failed to add IDStudent column: " . $con->error);
        }
    } else {
        echo "Column IDStudent already exists.\n";
    }

    // 2. Check if IDCourse column exists
    $checkCol = $con->query("SHOW COLUMNS FROM EvaluationResponses LIKE 'IDCourse'");
    if ($checkCol->num_rows == 0) {
        echo "Adding virtual column IDCourse...\n";
        $sql = "ALTER TABLE EvaluationResponses ADD COLUMN IDCourse VARCHAR(50) AS (JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.IDCourse'))) VIRTUAL";
        if (!$con->query($sql)) {
            throw new Exception("Failed to add IDCourse column: " . $con->error);
        }
    } else {
        echo "Column IDCourse already exists.\n";
    }

    // 3. Add Unique Index
    // We drop it first if it exists to ensure correct definition
    $checkIndex = $con->query("SHOW INDEX FROM EvaluationResponses WHERE Key_name = 'idx_unique_student_response'");
    if ($checkIndex->num_rows > 0) {
        echo "Dropping existing index idx_unique_student_response...\n";
        $con->query("DROP INDEX idx_unique_student_response ON EvaluationResponses");
    }

    echo "Adding unique index idx_unique_student_response...\n";
    // Unique per Student + Course + Semester + FormType + Question
    // This prevents the same student from answering the same question in the same context twice.
    $sql = "CREATE UNIQUE INDEX idx_unique_student_response ON EvaluationResponses (FormType, Semester, IDStudent, IDCourse, QuestionID)";
    
    if (!$con->query($sql)) {
        throw new Exception("Failed to add unique index: " . $con->error);
    }
    
    echo "Migration 011 completed successfully.\n";

} catch (Exception $e) {
    die("Migration failed: " . $e->getMessage() . "\n");
}
?>
