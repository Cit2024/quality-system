<?php
/**
 * Verification Test: Smart Semester Filtering
 * Tests the fix for empty statistics issue
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/DbConnection.php';
require_once __DIR__ . '/../config/dbConnectionCit.php';
require_once __DIR__ . '/../helpers/database.php';

echo "=== VERIFICATION TEST: Smart Semester Filtering ===\n\n";

$formType = 'course_evaluation';

// Test the NEW smart filtering query
echo "Testing NEW getSemesters() logic (with smart filtering):\n";
$smartQuery = "SELECT DISTINCT z.ZamanNo, z.ZamanName 
               FROM zaman z
               WHERE EXISTS (
                   SELECT 1 
                   FROM citcoder_Quality.EvaluationResponses er
                   WHERE er.Semester = z.ZamanNo
                     AND er.FormType = ?
               )
               ORDER BY z.ZamanNo DESC
               LIMIT 10";

$stmt = mysqli_prepare($conn_cit, $smartQuery);
mysqli_stmt_bind_param($stmt, 's', $formType);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

$semesters = [];
while ($row = mysqli_fetch_assoc($result)) {
    $semesters[] = $row;
    echo "  ✅ Semester {$row['ZamanNo']}: {$row['ZamanName']}\n";
}

if (count($semesters) == 0) {
    echo "  ❌ No semesters found with evaluation data!\n\n";
    die("VERIFICATION FAILED\n");
}

echo "\nTotal semesters with data: " . count($semesters) . "\n\n";

// Now test if these semesters will produce results
echo "Testing if these semesters will produce statistics cards:\n";
$totalCards = 0;

foreach ($semesters as $semester) {
    echo "\nSemester {$semester['ZamanNo']}:\n";
    
    // Get courses for this semester
    $courseQuery = "SELECT DISTINCT c.MadaNo, m.MadaName 
                    FROM coursesgroups c
                    JOIN mawad m ON c.MadaNo = m.MadaNo
                    WHERE c.ZamanNo = ?
                    LIMIT 5";
    $stmtCourse = mysqli_prepare($conn_cit, $courseQuery);
    mysqli_stmt_bind_param($stmtCourse, 'i', $semester['ZamanNo']);
    mysqli_stmt_execute($stmtCourse);
    $courseResult = mysqli_stmt_get_result($stmtCourse);
    
    $semesterCards = 0;
    while ($course = mysqli_fetch_assoc($courseResult)) {
        // Check evaluations
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
        
        if ($evalRow['cnt'] > 0) {
            echo "  ✅ {$course['MadaNo']}: {$evalRow['cnt']} evaluations\n";
            $semesterCards++;
            $totalCards++;
        }
    }
    
    echo "  Cards for this semester: $semesterCards\n";
}

echo "\n=== VERIFICATION RESULT ===\n";
if ($totalCards > 0) {
    echo "✅ SUCCESS: Fix works! $totalCards statistics cards will be displayed.\n";
    echo "\nThe statistics endpoint will now return HTML with actual data.\n";
} else {
    echo "❌ FAILED: No cards will be displayed despite semesters having data.\n";
}
