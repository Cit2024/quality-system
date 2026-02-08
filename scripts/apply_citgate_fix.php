<?php
/**
 * Citgate Teacher Integrity Fix
 * Adds RegTeacherID to teachers_evaluation table and links to regteacher registry.
 */

require_once __DIR__ . '/../config/dbConnectionCit.php';

if (!$conn_cit) {
    die("❌ Connection failed: " . mysqli_connect_error() . "\n");
}

echo "🛠️ Starting Citgate Integrity Migration...\n";

// 1. Add RegTeacherID column if it doesn't exist
$checkColumn = mysqli_query($conn_cit, "SHOW COLUMNS FROM teachers_evaluation LIKE 'RegTeacherID'");
if (mysqli_num_rows($checkColumn) == 0) {
    echo "➕ Adding 'RegTeacherID' column to 'teachers_evaluation'...\n";
    $alterQuery = "ALTER TABLE teachers_evaluation ADD COLUMN RegTeacherID INT NULL AFTER password";
    if (mysqli_query($conn_cit, $alterQuery)) {
        echo "✅ Column added.\n";
    } else {
        die("❌ Failed to add column: " . mysqli_error($conn_cit) . "\n");
    }
} else {
    echo "ℹ️  Column 'RegTeacherID' already exists.\n";
}

// 2. Perform linking based on Name match
echo "🔍 Linking teachers to the registry based on name...\n";
$linkQuery = "
    UPDATE teachers_evaluation te
    INNER JOIN regteacher rt ON te.name = rt.name
    SET te.RegTeacherID = rt.id
    WHERE te.RegTeacherID IS NULL";

if (mysqli_query($conn_cit, $linkQuery)) {
    $affected = mysqli_affected_rows($conn_cit);
    echo "✅ Successfully linked $affected teachers.\n";
} else {
    echo "❌ Error linking teachers: " . mysqli_error($conn_cit) . "\n";
}

// 3. Final Check
$missingQuery = "SELECT COUNT(*) as count FROM teachers_evaluation WHERE RegTeacherID IS NULL";
$missingRes = mysqli_query($conn_cit, $missingQuery);
$missingCount = mysqli_fetch_assoc($missingRes)['count'];

echo "\n========================================\n";
echo "📊 Migration Summary:\n";
echo "Missing Links: $missingCount\n";
echo "========================================\n";

if ($missingCount > 0) {
    echo "⚠️  Some teachers could not be matched by name automatically.\n";
} else {
    echo "🎉 All teachers successfully linked!\n";
}
echo "🚀 Try the statistics page now.\n";
