<?php
/**
 * Execution Flow Trace for get_statistics.php
 * Simulates exact code path with logging
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/DbConnection.php';
require_once __DIR__ . '/../config/dbConnectionCit.php';
require_once __DIR__ . '/../helpers/database.php';

echo "=== EXECUTION FLOW TRACE ===\n\n";

// Simulate Global Mode (teacher_id = 0)
$isGlobal = true;
$teacherId = 0;
$formType = 'course_evaluation';

echo "Mode: GLOBAL\n";
echo "FormType: $formType\n\n";

// STEP 1: getSemesters() in Global Mode
echo "STEP 1: getSemesters()\n";
$semesterQuery = "SELECT ZamanNo, ZamanName FROM zaman ORDER BY ZamanNo DESC LIMIT 2";
$semResult = mysqli_query($conn_cit, $semesterQuery);
$semesters = [];
while ($row = mysqli_fetch_assoc($semResult)) {
    $semesters[] = $row;
    echo "  Found: ZamanNo={$row['ZamanNo']}, Name={$row['ZamanName']}\n";
}
echo "  Total semesters: " . count($semesters) . "\n\n";

// STEP 2: processSemesters()
echo "STEP 2: processSemesters()\n";
$processed = [];

foreach ($semesters as $semester) {
    echo "\n  Processing Semester {$semester['ZamanNo']}:\n";
    
    // STEP 3: getSemesterCourses() in Global Mode
    $courseQuery = "SELECT DISTINCT c.MadaNo, m.MadaName 
                    FROM coursesgroups c
                    JOIN mawad m ON c.MadaNo = m.MadaNo
                    WHERE c.ZamanNo = ?";
    $stmt = mysqli_prepare($conn_cit, $courseQuery);
    mysqli_stmt_bind_param($stmt, 'i', $semester['ZamanNo']);
    mysqli_stmt_execute($stmt);
    $courseResult = mysqli_stmt_get_result($stmt);
    
    $courses = [];
    while ($course = mysqli_fetch_assoc($courseResult)) {
        $courses[] = $course;
    }
    echo "    Found {count($courses)} courses\n";
    
    $processedCourses = [];
    
    foreach ($courses as $course) {
        echo "    Course: {$course['MadaNo']} - {$course['MadaName']}\n";
        
        // STEP 4: getTotalStudents()
        $studentQuery = "SELECT COUNT(DISTINCT KidNo) AS total 
                         FROM tanzil 
                         WHERE ZamanNo = ? AND MadaNo = ?";
        $stmtStudents = mysqli_prepare($conn_cit, $studentQuery);
        mysqli_stmt_bind_param($stmtStudents, 'is', $semester['ZamanNo'], $course['MadaNo']);
        mysqli_stmt_execute($stmtStudents);
        $studentResult = mysqli_stmt_get_result($stmtStudents);
        $studentRow = mysqli_fetch_assoc($studentResult);
        $totalStudents = $studentRow['total'] ?? 0;
        echo "      Total students: $totalStudents\n";
        
        // STEP 5: getCourseEvaluations()
        $evalQuery = "SELECT COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.student_id'))) AS cnt 
                      FROM EvaluationResponses 
                      WHERE FormType = ?
                        AND Semester = ?
                        AND JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.course_id')) = ?";
        $stmtEval = mysqli_prepare($con, $evalQuery);
        mysqli_stmt_bind_param($stmtEval, 'sis', $formType, $semester['ZamanNo'], $course['MadaNo']);
        mysqli_stmt_execute($stmtEval);
        $evalResult = mysqli_stmt_get_result($stmtEval);
        $evalRow = mysqli_fetch_assoc($evalResult);
        $evaluations = $evalRow['cnt'] ?? 0;
        echo "      Evaluations: $evaluations\n";
        
        // CRITICAL: Line 139 in get_statistics.php
        if ($evaluations == 0) {
            echo "      ❌ SKIPPED (Line 139: evaluations == 0)\n";
            continue;
        }
        
        echo "      ✅ INCLUDED in processedCourses\n";
        $processedCourses[] = [
            'course_code' => $course['MadaNo'],
            'course_name' => $course['MadaName'],
            'evaluations' => $evaluations,
            'total_students' => $totalStudents,
        ];
    }
    
    echo "    Processed courses for semester: " . count($processedCourses) . "\n";
    
    if (!empty($processedCourses)) {
        $processed[] = [
            'semester_no' => $semester['ZamanNo'],
            'semester_name' => $semester['ZamanName'],
            'courses' => $processedCourses
        ];
        echo "    ✅ Semester added to processed array\n";
    } else {
        echo "    ❌ Semester SKIPPED (no courses with evaluations)\n";
    }
}

echo "\n\nFINAL RESULT:\n";
echo "Total semesters with data: " . count($processed) . "\n";
echo "Total courses across all semesters: ";
$totalCourses = 0;
foreach ($processed as $sem) {
    $totalCourses += count($sem['courses']);
}
echo "$totalCourses\n\n";

if (count($processed) == 0) {
    echo "❌ ROOT CAUSE: processSemesters() returns empty array\n";
    echo "This explains why html is empty.\n";
} else {
    echo "✅ Data exists, would generate HTML\n";
    echo "\nSample data structure:\n";
    print_r($processed);
}

echo "\n=== TRACE COMPLETE ===\n";
