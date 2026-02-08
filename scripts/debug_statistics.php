<?php
/**
 * Simplified Diagnostic Script
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== STATISTICS DIAGNOSTIC TRACE ===\n\n";

// Test database connections
echo "STEP 0: Testing Database Connections\n";
require_once __DIR__ . '/../config/DbConnection.php';
require_once __DIR__ . '/../config/dbConnectionCit.php';

if (!$con) {
    die("❌ Quality DB connection failed: " . mysqli_connect_error() . "\n");
}
echo "✅ Quality DB connected\n";

if (!$conn_cit) {
    die("❌ Citgate DB connection failed: " . mysqli_connect_error() . "\n");
}
echo "✅ Citgate DB connected\n\n";

// STEP 1: Check evaluation data
echo "STEP 1: Checking EvaluationResponses\n";
$query1 = "SELECT COUNT(*) as total FROM EvaluationResponses WHERE FormType = 'course_evaluation'";
$result1 = mysqli_query($con, $query1);
if (!$result1) {
    die("❌ Query failed: " . mysqli_error($con) . "\n");
}
$row1 = mysqli_fetch_assoc($result1);
echo "Total course evaluations: {$row1['total']}\n\n";

// STEP 2: Check semester data types
echo "STEP 2: Checking Semester Values\n";
$query2 = "SELECT DISTINCT Semester FROM EvaluationResponses WHERE FormType = 'course_evaluation' ORDER BY Semester DESC LIMIT 5";
$result2 = mysqli_query($con, $query2);
if (!$result2) {
    die("❌ Query failed: " . mysqli_error($con) . "\n");
}
echo "Semester values in EvaluationResponses:\n";
while ($row = mysqli_fetch_assoc($result2)) {
    $val = $row['Semester'];
    $type = gettype($val);
    $len = strlen($val);
    echo "  Value: '$val' | PHP Type: $type | Length: $len\n";
}
echo "\n";

// STEP 3: Check ZamanNo values
echo "STEP 3: Checking ZamanNo Values\n";
$query3 = "SELECT ZamanNo, ZamanName FROM zaman ORDER BY ZamanNo DESC LIMIT 5";
$result3 = mysqli_query($conn_cit, $query3);
if (!$result3) {
    die("❌ Query failed: " . mysqli_error($conn_cit) . "\n");
}
echo "ZamanNo values in zaman table:\n";
while ($row = mysqli_fetch_assoc($result3)) {
    $val = $row['ZamanNo'];
    $type = gettype($val);
    echo "  ZamanNo: $val | Name: {$row['ZamanName']} | PHP Type: $type\n";
}
echo "\n";

// STEP 4: Test the actual query with type casting
echo "STEP 4: Testing Query with Semester='74' (string)\n";
$testSem = '74';
$testCourse = 'ت.ا 701';
$query4 = "SELECT COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.student_id'))) AS cnt 
           FROM EvaluationResponses 
           WHERE FormType = 'course_evaluation'
             AND Semester = '$testSem'
             AND JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.course_id')) = '$testCourse'";
$result4 = mysqli_query($con, $query4);
if (!$result4) {
    die("❌ Query failed: " . mysqli_error($con) . "\n");
}
$row4 = mysqli_fetch_assoc($result4);
echo "Result with string comparison: {$row4['cnt']} students\n\n";

// STEP 5: Test with integer
echo "STEP 5: Testing Query with Semester=74 (integer)\n";
$testSemInt = 74;
$query5 = "SELECT COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.student_id'))) AS cnt 
           FROM EvaluationResponses 
           WHERE FormType = 'course_evaluation'
             AND Semester = $testSemInt
             AND JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.course_id')) = '$testCourse'";
$result5 = mysqli_query($con, $query5);
if (!$result5) {
    die("❌ Query failed: " . mysqli_error($con) . "\n");
}
$row5 = mysqli_fetch_assoc($result5);
echo "Result with integer comparison: {$row5['cnt']} students\n\n";

// STEP 6: Test prepared statement (how get_statistics.php does it)
echo "STEP 6: Testing Prepared Statement (as in get_statistics.php)\n";
$stmt = mysqli_prepare($con, "SELECT COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.student_id'))) AS cnt 
                               FROM EvaluationResponses 
                               WHERE FormType = 'course_evaluation'
                                 AND Semester = ?
                                 AND JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.course_id')) = ?");
if (!$stmt) {
    die("❌ Prepare failed: " . mysqli_error($con) . "\n");
}

// Test with integer parameter (as code does)
mysqli_stmt_bind_param($stmt, 'is', $testSemInt, $testCourse);
mysqli_stmt_execute($stmt);
$result6 = mysqli_stmt_get_result($stmt);
$row6 = mysqli_fetch_assoc($result6);
echo "Prepared statement with int param: {$row6['cnt']} students\n\n";

// STEP 7: Check column types
echo "STEP 7: Checking Column Definitions\n";
$query7 = "SHOW COLUMNS FROM EvaluationResponses WHERE Field = 'Semester'";
$result7 = mysqli_query($con, $query7);
$col = mysqli_fetch_assoc($result7);
echo "EvaluationResponses.Semester Type: {$col['Type']}\n";

$query8 = "SHOW COLUMNS FROM zaman WHERE Field = 'ZamanNo'";
$result8 = mysqli_query($conn_cit, $query8);
$col2 = mysqli_fetch_assoc($result8);
echo "zaman.ZamanNo Type: {$col2['Type']}\n\n";

echo "=== ROOT CAUSE IDENTIFIED ===\n";
echo "If prepared statement result differs from string comparison,\n";
echo "the issue is data type mismatch in parameter binding.\n";
