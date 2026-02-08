<?php
/**
 * Inspect teachers_evaluation schema
 * Checks for column names in the teachers_evaluation table (Citgate DB)
 */

require_once __DIR__ . '/../config/dbConnectionCit.php';

if (!$conn_cit) {
    die("❌ Connection failed: " . mysqli_connect_error());
}

echo "🔍 Inspecting 'teachers_evaluation' table columns...\n";

$result = mysqli_query($conn_cit, "SHOW COLUMNS FROM teachers_evaluation");

if (!$result) {
    die("❌ Query failed: " . mysqli_error($conn_cit) . "\n");
}

echo str_pad("Field", 20) . str_pad("Type", 20) . "\n";
echo str_repeat("-", 40) . "\n";

$hasRegTeacherID = false;

while ($row = mysqli_fetch_assoc($result)) {
    echo str_pad($row['Field'], 20) . str_pad($row['Type'], 20) . "\n";
    if ($row['Field'] === 'RegTeacherID') {
        $hasRegTeacherID = true;
    }
}

echo "\n========================================\n";
if ($hasRegTeacherID) {
    echo "✅ Column 'RegTeacherID' EXISTS.\n";
} else {
    echo "❌ Column 'RegTeacherID' MISSING.\n";
}
echo "========================================\n";
