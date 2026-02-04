<?php
/**
 * Database Restore Script
 * Restore database from backup file
 * 
 * Usage: php restore_database.php [backup_file.sql.gz]
 */

require_once __DIR__ . '/../config/DbConnection.php';

// Configuration
define('BACKUP_DIR', __DIR__ . '/../backups');

/**
 * List available backups
 */
function listAvailableBackups() {
    $backups = [];
    $types = ['daily', 'weekly', 'monthly'];
    
    foreach ($types as $type) {
        $dir = BACKUP_DIR . '/' . $type;
        if (is_dir($dir)) {
            $files = glob($dir . '/backup_*.sql*');
            foreach ($files as $file) {
                $backups[] = [
                    'type' => $type,
                    'file' => $file,
                    'name' => basename($file),
                    'size' => filesize($file),
                    'date' => filemtime($file)
                ];
            }
        }
    }
    
    // Sort by date (newest first)
    usort($backups, function($a, $b) {
        return $b['date'] - $a['date'];
    });
    
    return $backups;
}

/**
 * Display available backups
 */
function displayBackups($backups) {
    echo "\nAvailable Backups:\n";
    echo str_repeat("=", 80) . "\n";
    echo sprintf("%-5s %-10s %-30s %-15s %-20s\n", "#", "Type", "Filename", "Size", "Date");
    echo str_repeat("-", 80) . "\n";
    
    foreach ($backups as $index => $backup) {
        echo sprintf(
            "%-5d %-10s %-30s %-15s %-20s\n",
            $index + 1,
            $backup['type'],
            substr($backup['name'], 0, 30),
            formatBytes($backup['size']),
            date('Y-m-d H:i:s', $backup['date'])
        );
    }
    echo str_repeat("=", 80) . "\n";
}

/**
 * Restore database from backup
 */
function restoreDatabase($backupFile) {
    global $con;
    
    try {
        echo "\n[" . date('Y-m-d H:i:s') . "] Starting restore from: " . basename($backupFile) . "\n";
        
        // Verify backup file exists
        if (!file_exists($backupFile)) {
            throw new Exception("Backup file not found: $backupFile");
        }
        
        // Check if file is compressed
        $isCompressed = (pathinfo($backupFile, PATHINFO_EXTENSION) === 'gz');
        
        // Decompress if needed
        $sqlFile = $backupFile;
        if ($isCompressed) {
            echo "[" . date('Y-m-d H:i:s') . "] Decompressing backup...\n";
            $sqlFile = str_replace('.gz', '', $backupFile);
            exec("gunzip -c " . escapeshellarg($backupFile) . " > " . escapeshellarg($sqlFile), $output, $returnCode);
            
            if ($returnCode !== 0) {
                throw new Exception("Failed to decompress backup");
            }
        }
        
        // Verify SQL file
        if (!file_exists($sqlFile) || filesize($sqlFile) === 0) {
            throw new Exception("SQL file is empty or doesn't exist");
        }
        
        echo "[" . date('Y-m-d H:i:s') . "] Verifying backup integrity...\n";
        
        // Get database credentials
        $dbHost = DB_HOST;
        $dbName = DB_NAME;
        $dbUser = DB_USER;
        $dbPass = DB_PASS;
        
        // Confirm restore
        echo "\nWARNING: This will replace ALL data in database '$dbName'\n";
        echo "Are you sure you want to continue? (yes/no): ";
        $handle = fopen("php://stdin", "r");
        $confirmation = trim(fgets($handle));
        fclose($handle);
        
        if (strtolower($confirmation) !== 'yes') {
            echo "Restore cancelled.\n";
            if ($isCompressed && file_exists($sqlFile)) {
                unlink($sqlFile);
            }
            return false;
        }
        
        echo "\n[" . date('Y-m-d H:i:s') . "] Restoring database...\n";
        
        // Build mysql restore command
        $command = sprintf(
            'mysql --host=%s --user=%s --password=%s %s < %s 2>&1',
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbName),
            escapeshellarg($sqlFile)
        );
        
        // Execute restore
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Restore failed: " . implode("\n", $output));
        }
        
        // Clean up decompressed file if it was created
        if ($isCompressed && file_exists($sqlFile)) {
            unlink($sqlFile);
        }
        
        // Log success
        logRestore($backupFile, 'success');
        
        echo "[" . date('Y-m-d H:i:s') . "] Restore completed successfully!\n";
        echo "[" . date('Y-m-d H:i:s') . "] Please verify your data and test critical functionality.\n";
        
        return true;
        
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        echo "[" . date('Y-m-d H:i:s') . "] ERROR: $errorMsg\n";
        logRestore($backupFile, 'failed', $errorMsg);
        
        // Clean up decompressed file on error
        if (isset($isCompressed) && $isCompressed && isset($sqlFile) && file_exists($sqlFile)) {
            unlink($sqlFile);
        }
        
        return false;
    }
}

/**
 * Log restore operation
 */
function logRestore($filename, $status, $error = null) {
    $logFile = BACKUP_DIR . '/logs/restore_log.txt';
    $logEntry = sprintf(
        "[%s] Status: %s | File: %s | Error: %s\n",
        date('Y-m-d H:i:s'),
        $status,
        basename($filename),
        $error ?? 'None'
    );
    
    file_put_contents($logFile, $logEntry, FILE_APPEND);
}

/**
 * Format bytes to human readable
 */
function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

// Main execution
echo "\n=== Database Restore Utility ===\n";

// Check if backup file provided as argument
if (isset($argv[1])) {
    $backupFile = $argv[1];
    
    // If relative path, prepend backup directory
    if (!file_exists($backupFile)) {
        $backupFile = BACKUP_DIR . '/' . $backupFile;
    }
    
    $success = restoreDatabase($backupFile);
    exit($success ? 0 : 1);
}

// Interactive mode - list backups and let user choose
$backups = listAvailableBackups();

if (empty($backups)) {
    echo "\nNo backups found in " . BACKUP_DIR . "\n";
    exit(1);
}

displayBackups($backups);

echo "\nEnter backup number to restore (or 'q' to quit): ";
$handle = fopen("php://stdin", "r");
$input = trim(fgets($handle));
fclose($handle);

if (strtolower($input) === 'q') {
    echo "Cancelled.\n";
    exit(0);
}

$index = intval($input) - 1;

if (!isset($backups[$index])) {
    echo "Invalid selection.\n";
    exit(1);
}

$success = restoreDatabase($backups[$index]['file']);
exit($success ? 0 : 1);
?>
