<?php
// database/migrations/010_hash_existing_passwords.php

// Adjust path to config/DbConnection.php depending on where this script is run
// Assuming run from project root: php database/migrations/010_hash_existing_passwords.php
$configPath = __DIR__ . '/../../config/DbConnection.php';

if (!file_exists($configPath)) {
    die("Error: Could not find config/DbConnection.php at $configPath\n");
}

require_once $configPath;

echo "Starting password migration to ARGON2ID...\n";

// 1. Fetch all admins
$query = "SELECT ID, username, password FROM Admin WHERE is_deleted = 0";
$result = mysqli_query($con, $query);

if (!$result) {
    die("Database error: " . mysqli_error($con) . "\n");
}

$count = 0;
$updated = 0;

while ($admin = mysqli_fetch_assoc($result)) {
    $count++;
    $currentPassword = $admin['password'];
    
    // Check if already hashed (basic check for algorithm prefix)
    // ARGON2ID hashes usually start with $argon2id$
    if (strpos($currentPassword, '$argon2id$') === 0) {
        echo "Skipping Admin ID {$admin['ID']} ({$admin['username']}): Already hashed.\n";
        continue;
    }

    echo "Hashing password for Admin ID {$admin['ID']} ({$admin['username']})...\n";
    
    $hashedPassword = password_hash($currentPassword, PASSWORD_ARGON2ID);
    
    $updateQuery = "UPDATE Admin SET password = ? WHERE ID = ?";
    $stmt = mysqli_prepare($con, $updateQuery);
    mysqli_stmt_bind_param($stmt, "si", $hashedPassword, $admin['ID']);
    
    if (mysqli_stmt_execute($stmt)) {
        $updated++;
        echo "  -> Success\n";
    } else {
        echo "  -> Failed: " . mysqli_error($con) . "\n";
    }
    mysqli_stmt_close($stmt);
}

echo "\nMigration complete.\n";
echo "Total Admins: $count\n";
echo "Updated: $updated\n";
?>
