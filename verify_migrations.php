<?php
/**
 * Database Verification Script - Updated for Database Separation
 * Checks if migrations were applied correctly
 * Run this in browser: http://localhost/quality-system/verify_migrations.php
 */

require_once 'config/DbConnection.php';
require_once 'config/dbConnectionCit.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Verification</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #4CAF50; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; border-bottom: 2px solid #ddd; padding-bottom: 8px; }
        .check { margin: 15px 0; padding: 15px; border-radius: 5px; }
        .pass { background: #d4edda; border-left: 4px solid #28a745; }
        .fail { background: #f8d7da; border-left: 4px solid #dc3545; }
        .warn { background: #fff3cd; border-left: 4px solid #ffc107; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; }
        .status { font-weight: bold; font-size: 18px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: right; border: 1px solid #ddd; }
        th { background: #f8f9fa; font-weight: bold; }
        .code { background: #f4f4f4; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 Database Migration Verification</h1>
    <p><strong>Date:</strong> <?= date('Y-m-d H:i:s') ?></p>

    <?php
    $errors = 0;
    $warnings = 0;

    // CHECK 1: Database Separation
    echo "<h2>📋 Check 1: Database Architecture Separation</h2>";
    
    // Verify Citgate tables do NOT exist in Quality DB
    $citgate_tables = ['regteacher', 'teachers_evaluation', 'coursesgroups', 'tanzil', 'sprofiles', 'zaman', 'divitions', 'mawad', 'AuditLog'];
    $found_in_quality = [];
    
    foreach ($citgate_tables as $table) {
        $result = mysqli_query($con, "SHOW TABLES LIKE '$table'");
        if (mysqli_num_rows($result) > 0) {
            $found_in_quality[] = $table;
        }
    }
    
    if (empty($found_in_quality)) {
        echo '<div class="check pass"><span class="status">✅ PASS:</span> Citgate tables correctly separated (not in Quality DB)</div>';
    } else {
        echo '<div class="check fail"><span class="status">❌ FAIL:</span> Found ' . count($found_in_quality) . ' Citgate tables in Quality DB</div>';
        echo '<div class="code">Tables found: ' . implode(', ', $found_in_quality) . '</div>';
        echo '<div class="code">Action: Run Migration 022 (022_remove_citgate_tables.sql)</div>';
        $errors++;
    }
    
    // Verify Citgate tables exist in Citgate DB
    $missing_in_citgate = [];
    foreach ($citgate_tables as $table) {
        if ($table === 'AuditLog') continue; // Skip AuditLog for Citgate check
        $result = mysqli_query($conn_cit, "SHOW TABLES LIKE '$table'");
        if (mysqli_num_rows($result) == 0) {
            $missing_in_citgate[] = $table;
        }
    }
    
    if (empty($missing_in_citgate)) {
        echo '<div class="check pass"><span class="status">✅ PASS:</span> All required tables exist in Citgate DB</div>';
    } else {
        echo '<div class="check fail"><span class="status">❌ FAIL:</span> Missing tables in Citgate DB: ' . implode(', ', $missing_in_citgate) . '</div>';
        $errors++;
    }

    // CHECK 2: Virtual Columns
    echo "<h2>📋 Check 2: Virtual Columns</h2>";
    $result = mysqli_query($con, "SHOW COLUMNS FROM EvaluationResponses WHERE Field IN ('student_id', 'course_id', 'teacher_id')");
    $count = mysqli_num_rows($result);
    if ($count == 3) {
        echo '<div class="check pass"><span class="status">✅ PASS:</span> All 3 virtual columns exist</div>';
        
        // Test virtual columns
        $result = mysqli_query($con, "SELECT student_id, course_id, teacher_id FROM EvaluationResponses LIMIT 1");
        if ($row = mysqli_fetch_assoc($result)) {
            echo '<div class="check pass"><span class="status">✅ PASS:</span> Virtual columns working</div>';
            echo '<div class="code">Sample: student_id=' . ($row['student_id'] ?? 'NULL') . ', course_id=' . ($row['course_id'] ?? 'NULL') . ', teacher_id=' . ($row['teacher_id'] ?? 'NULL') . '</div>';
        }
    } else {
        echo '<div class="check fail"><span class="status">❌ FAIL:</span> Virtual columns missing (' . $count . '/3 found)</div>';
        $errors++;
    }

    // CHECK 3: Foreign Keys (Quality DB only)
    echo "<h2>📋 Check 3: Foreign Key Constraints</h2>";
    $quality_fks = ['fk_resp_question', 'fk_form_type_slug', 'fk_form_target_slug'];
    $result = mysqli_query($con, "
        SELECT CONSTRAINT_NAME 
        FROM information_schema.TABLE_CONSTRAINTS 
        WHERE CONSTRAINT_SCHEMA = DATABASE() 
        AND CONSTRAINT_NAME IN ('" . implode("','", $quality_fks) . "')
    ");
    $found = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $found[] = $row['CONSTRAINT_NAME'];
    }
    
    echo '<table><tr><th>Constraint</th><th>Status</th></tr>';
    foreach ($quality_fks as $fk) {
        $exists = in_array($fk, $found);
        echo '<tr><td>' . $fk . '</td><td>' . ($exists ? '✅ Exists' : '⚠️ Missing') . '</td></tr>';
        if (!$exists) $warnings++;
    }
    echo '</table>';
    
    // Check that cross-database FKs are removed
    $removed_fks = ['fk_teacher_link', 'fk_course_teacher', 'fk_evaluation_coursesgroups'];
    $result = mysqli_query($con, "
        SELECT CONSTRAINT_NAME 
        FROM information_schema.TABLE_CONSTRAINTS 
        WHERE CONSTRAINT_SCHEMA = DATABASE() 
        AND CONSTRAINT_NAME IN ('" . implode("','", $removed_fks) . "')
    ");
    $still_exist = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $still_exist[] = $row['CONSTRAINT_NAME'];
    }
    
    if (empty($still_exist)) {
        echo '<div class="check pass"><span class="status">✅ PASS:</span> Cross-database FKs correctly removed</div>';
    } else {
        echo '<div class="check warn"><span class="status">⚠️ WARNING:</span> Cross-database FKs still exist: ' . implode(', ', $still_exist) . '</div>';
        $warnings++;
    }

    // CHECK 4: Redundant Columns Removed
    echo "<h2>📋 Check 4: Schema Cleanup</h2>";
    $result = mysqli_query($con, "SHOW COLUMNS FROM Form WHERE Field IN ('FormTypeID', 'EvaluatorTypeID')");
    $count = mysqli_num_rows($result);
    if ($count == 0) {
        echo '<div class="check pass"><span class="status">✅ PASS:</span> Redundant columns removed</div>';
    } else {
        echo '<div class="check warn"><span class="status">⚠️ WARNING:</span> Redundant columns still exist - Migration 020 not fully applied</div>';
        $warnings++;
    }

    // CHECK 5: Support Tables
    echo "<h2>📋 Check 5: Support Tables</h2>";
    $support_tables = ['FormProcessingRules', 'SystemMessages'];
    $missing_tables = [];
    
    foreach ($support_tables as $table) {
        $result = mysqli_query($con, "SHOW TABLES LIKE '$table'");
        if (mysqli_num_rows($result) == 0) {
            $missing_tables[] = $table;
        }
    }
    
    if (empty($missing_tables)) {
        echo '<div class="check pass"><span class="status">✅ PASS:</span> All support tables exist</div>';
    } else {
        echo '<div class="check fail"><span class="status">❌ FAIL:</span> Missing support tables: ' . implode(', ', $missing_tables) . '</div>';
        echo '<div class="code">Action: Run Migration 021 (021_create_support_tables.sql)</div>';
        $errors++;
    }

    // SUMMARY
    echo "<h2>📊 Summary</h2>";
    if ($errors == 0 && $warnings == 0) {
        echo '<div class="check pass"><span class="status">✅ ALL CHECKS PASSED</span><br>Database migrations applied successfully!</div>';
    } elseif ($errors == 0) {
        echo '<div class="check warn"><span class="status">⚠️ WARNINGS FOUND</span><br>Migrations applied but some manual actions needed. Errors: ' . $errors . ', Warnings: ' . $warnings . '</div>';
    } else {
        echo '<div class="check fail"><span class="status">❌ ERRORS FOUND</span><br>Migrations not fully applied. Errors: ' . $errors . ', Warnings: ' . $warnings . '</div>';
    }

    // Next Steps
    echo "<h2>📌 Next Steps</h2>";
    echo "<ol>";
    if ($errors > 0) {
        if (!empty($found_in_quality)) {
            echo "<li>Run <code>022_remove_citgate_tables.sql</code> to remove Citgate tables from Quality DB</li>";
        }
        if (!empty($missing_tables)) {
            echo "<li>Run <code>021_create_support_tables.sql</code> to create missing support tables</li>";
        }
    }
    if ($warnings > 0) {
        echo "<li>Review warnings above and take manual actions if needed</li>";
    }
    echo "<li>Test teacher login at <a href='login.php'>login.php</a></li>";
    echo "<li>Test teacher dashboard at <a href='teacher_dashboard.php'>teacher_dashboard.php</a></li>";
    echo "<li>Test admin dashboard at <a href='dashboard.php'>dashboard.php</a></li>";
    echo "</ol>";
    ?>

</div>
</body>
</html>
