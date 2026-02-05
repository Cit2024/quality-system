<?php
/**
 * Initialize Test Database
 * Creates and seeds the test database using Docker connection
 */

$host = '127.0.0.1';
$port = 3307;
$user = 'root';
$pass = 'rootpassword';
$testDb = 'quality_system_test';

echo "Initializing test database...\n";
echo "Connecting to $host:$port as $user\n";

// Connect to MySQL server (no DB selected yet)
$con = new mysqli($host, $user, $pass, '', $port);

if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error . "\n");
}

// Create database if not exists
echo "Creating database '$testDb'...\n";
$con->query("DROP DATABASE IF EXISTS `$testDb`");
if (!$con->query("CREATE DATABASE `$testDb`")) {
    die("Error creating database: " . $con->error . "\n");
}

// Select the new database
$con->select_db($testDb);

// Run migrations to set up schema
echo "Running migrations...\n";

// We can reuse the migration runner if we mock the config
// Or simpler: just cat all SQL files and run them
$migrationFiles = glob(__DIR__ . '/../database/migrations/*.sql');
sort($migrationFiles);

foreach ($migrationFiles as $file) {
    // Skip migration tracking for pure schema
    if (basename($file) === '016_create_migrations_table.sql') continue;
    
    // Skip data population that relies on specific IDs not present in test DB
    if (basename($file) === '004_populate_form_access_fields.sql') continue;
    
    echo "Applying " . basename($file) . "...\n";
    $sql = file_get_contents($file);
    
    // Quick and dirty multi-query execution
    // Remove DELIMITER hack if present in old files (though we fixed them)
    if ($con->multi_query($sql)) {
        do {
            if ($result = $con->store_result()) {
                $result->free();
            }
        } while ($con->more_results() && $con->next_result());
    }
    
    if ($con->error) {
        echo "Warning in " . basename($file) . ": " . $con->error . "\n";
    }
}

echo "Test database initialized successfully!\n";
