<?php
/**
 * Fix Admin Passwords
 * Hashes plaintext passwords in the Admin table using password_hash()
 */

// Include database connection (using DbConnection.php as we are modifying Admin table in Quality DB)
require_once __DIR__ . '/../config/DbConnection.php';

if (!$con) {
    die("❌ Connection failed: " . mysqli_connect_error());
}

echo "🔍 Checking for plaintext passwords in Admin table...\n";

// Fetch all admins
$query = "SELECT ID, username, password FROM Admin WHERE is_deleted = 0";
$result = mysqli_query($con, $query);

if (!$result) {
    die("❌ Error fetching admins: " . mysqli_error($con) . "\n");
}

$count = 0;
$fixed = 0;

while ($admin = mysqli_fetch_assoc($result)) {
    $id = $admin['ID'];
    $username = $admin['username'];
    $password = $admin['password'];

    // Check if password needs re-hashing
    // password_get_info returns ['algo' => 0] if not a recognized hash
    $info = password_get_info($password);
    
    // A simple heuristic: if it's short or doesn't look like a bcrypt hash (starts with $2y$)
    $is_hashed = ($info['algo'] != 0 && strpos($password, '$2y$') === 0);

    if (!$is_hashed) {
        echo "⚠️  Found user '$username' with potentially plaintext password.\n";
        
        // Hash the password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update database
        $update_query = "UPDATE Admin SET password = ? WHERE ID = ?";
        $stmt = mysqli_prepare($con, $update_query);
        mysqli_stmt_bind_param($stmt, "si", $hashed_password, $id);
        
        if (mysqli_stmt_execute($stmt)) {
            echo "   ✅ Password fixed (hashed).\n";
            $fixed++;
        } else {
            echo "   ❌ Failed to update password: " . mysqli_error($con) . "\n";
        }
    } else {
        echo "✅ User '$username' already has a valid hash.\n";
    }
    $count++;
}

echo "\n========================================\n";
echo "Checked: $count users\n";
echo "Fixed:   $fixed users\n";
echo "========================================\n";

if ($fixed > 0) {
    echo "🎉 Try logging in now!\n";
} else {
    echo "👍 No changes needed.\n";
}
