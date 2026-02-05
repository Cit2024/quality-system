<?php
/**
 * Performance Data Seeder
 * Generates large volume of evaluation data to test database performance.
 */

require_once __DIR__ . '/../config/DbConnection.php';

// Check if we are in testing mode (CLI) or connected to local test DB
// We DON'T want to flood production.
if (strpos($host, 'cit.edu.ly') !== false && !in_array('--force', $argv)) {
    die("ERROR: Attempting to seed PRODUCTION database ($host). Pass --force to override.\n");
}

echo "Starting Performance Seed on $host...\n";

// Configuration
$BATCH_SIZE = 1000;
$TOTAL_RECORDS = 10000;
$SEMESTERS = ['Fall2024', 'Spring2025', 'Fall2025', 'Spring2026'];
$FORM_TYPES = ['Course-Eval', 'Instructor-Eval', 'Lab-Safety'];

$startTime = microtime(true);

// 1. Ensure basic config exists
$con->query("INSERT IGNORE INTO FormTypes (Slug, Name) VALUES ('Course-Eval', 'Course Evaluation')");
$con->query("INSERT IGNORE INTO FormTypes (Slug, Name) VALUES ('Instructor-Eval', 'Instructor Evaluation')");
$con->query("INSERT IGNORE INTO FormTypes (Slug, Name) VALUES ('Lab-Safety', 'Lab Safety Audit')");

// 2. Generator Loop
$con->begin_transaction();

$stmt = $con->prepare("
    INSERT INTO EvaluationResponses 
    (FormType, FormTarget, Semester, AnsweredAt, QuestionID, Metadata) 
    VALUES (?, ?, ?, ?, ?, ?)
");

if (!$stmt) {
    die("Prepare failed: " . $con->error . "\n");
}

$count = 0;
for ($i = 0; $i < $TOTAL_RECORDS; $i++) {
    $formType = $FORM_TYPES[array_rand($FORM_TYPES)];
    $semester = $SEMESTERS[array_rand($SEMESTERS)];
    $target = "Course " . rand(100, 500);
    $questionId = rand(1, 50);
    
    // Random date in last year
    $timestamp = mt_rand(strtotime('-1 year'), time());
    $answeredAt = date('Y-m-d H:i:s', $timestamp);
    
    // JSON Metadata
    $studentId = "std_" . rand(1000, 9999);
    $courseId = "cse_" . rand(100, 900);
    
    $metadata = json_encode([
        'IDStudent' => $studentId,
        'student_id' => $studentId,
        'IDCourse' => $courseId,
        'course_id' => $courseId,
        'score' => rand(1, 5),
        'comment' => "Performance test generated comment #$i"
    ]);

    $stmt->bind_param("ssssis", $formType, $target, $semester, $answeredAt, $questionId, $metadata);
    $stmt->execute();
    
    $count++;
    if ($count % $BATCH_SIZE === 0) {
        $con->commit();
        $con->begin_transaction();
        $elapsed = microtime(true) - $startTime;
        $rate = $count / $elapsed;
        echo "Inserted $count records... (" . round($rate, 2) . " rec/s)\n";
    }
}

$con->commit();
$totalTime = microtime(true) - $startTime;

echo "\nCompleted!\n";
echo "Total Records: $TOTAL_RECORDS\n";
echo "Total Time: " . round($totalTime, 2) . "s\n";
echo "Average Rate: " . round($TOTAL_RECORDS / $totalTime, 2) . " records/second\n";
