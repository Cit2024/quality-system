<?php
/**
 * PHPUnit Bootstrap File
 * Initializes test environment
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', '1');

// Define test environment
define('TESTING', true);

// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

// Load configuration files
require_once __DIR__ . '/../config/constants.php';

// Set test database connection constants
if (!defined('DB_HOST')) {
    define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
    define('DB_NAME', getenv('DB_NAME') ?: 'citcoder_Quality_test');
    define('DB_USER', getenv('DB_USER') ?: 'root');
    define('DB_PASS', getenv('DB_PASS') ?: '');
}

// Start session for tests that need it
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Helper function to reset database for tests
function resetTestDatabase() {
    global $con;
    
    if (!$con) {
        require_once __DIR__ . '/../config/DbConnection.php';
    }
    
    // Truncate test tables
    $tables = ['AuditLog', 'EvaluationResponses', 'FormAccessFields'];
    foreach ($tables as $table) {
        mysqli_query($con, "TRUNCATE TABLE `$table`");
    }
}

// Helper function to create test user session
function createTestSession($userType = 'admin', $userID = 1) {
    $_SESSION['user_id'] = $userID;
    $_SESSION['user_type'] = $userType;
    $_SESSION['username'] = 'test_user';
}

// Helper function to clear test session
function clearTestSession() {
    session_unset();
    session_destroy();
    session_start();
}

echo "PHPUnit Bootstrap Complete\n";
?>
