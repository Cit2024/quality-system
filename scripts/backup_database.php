<?php
/**
 * Database Backup Script
 * Automated backup with retention policy
 * 
 * Usage: php backup_database.php [--type=daily|weekly|monthly]
 */

require_once __DIR__ . '/../config/DbConnection.php';
require_once __DIR__ . '/../config/constants.php';

// Configuration
define('BACKUP_DIR', __DIR__ . '/../backups');
define('RETENTION_DAYS', 30);
define('WEEKLY_RETENTION', 12); // 12 weeks
define('MONTHLY_RETENTION', 12); // 12 months

// Parse command line arguments
$backupType = 'daily';
if (isset($argv[1])) {
    if (preg_match('/--type=(daily|weekly|monthly)/', $argv[1], $matches)) {
        $backupType = $matches[1];
    }
}

/**
 * Main backup function
 */
function performBackup($type) {
    global $con;
    
    try {
        // Create backup directories if they don't exist
        createBackupDirectories();
        
        // Generate backup filename
        $timestamp = date('Y-m-d_H-i-s');
        $backupDir = BACKUP_DIR . '/' . $type;
        $backupFile = $backupDir . '/backup_' . $timestamp . '.sql';
        $compressedFile = $backupFile . '.gz';
        
        echo "[" . date('Y-m-d H:i:s') . "] Starting $type backup...\n";
        
        // Get database credentials from connection
        $dbHost = DB_HOST;
        $dbName = DB_NAME;
        $dbUser = DB_USER;
        $dbPass = DB_PASS;
        
        // Build mysqldump command
        $command = sprintf(
            'mysqldump --host=%s --user=%s --password=%s --single-transaction --routines --triggers %s > %s 2>&1',
            escapeshellarg($dbHost),
            escapeshellarg($dbUser),
            escapeshellarg($dbPass),
            escapeshellarg($dbName),
            escapeshellarg($backupFile)
        );
        
        // Execute backup
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Backup failed: " . implode("\n", $output));
        }
        
        // Verify backup file exists and has content
        if (!file_exists($backupFile) || filesize($backupFile) === 0) {
            throw new Exception("Backup file is empty or doesn't exist");
        }
        
        $backupSize = filesize($backupFile);
        echo "[" . date('Y-m-d H:i:s') . "] Backup created: " . formatBytes($backupSize) . "\n";
        
        // Compress backup
        echo "[" . date('Y-m-d H:i:s') . "] Compressing backup...\n";
        exec("gzip -9 " . escapeshellarg($backupFile), $output, $returnCode);
        
        if ($returnCode === 0 && file_exists($compressedFile)) {
            $compressedSize = filesize($compressedFile);
            echo "[" . date('Y-m-d H:i:s') . "] Compressed: " . formatBytes($compressedSize) . 
                 " (saved " . formatBytes($backupSize - $compressedSize) . ")\n";
        } else {
            echo "[" . date('Y-m-d H:i:s') . "] Warning: Compression failed, keeping uncompressed backup\n";
            $compressedFile = $backupFile;
        }
        
        // Clean old backups
        cleanOldBackups($type);
        
        // Log success
        logBackup($type, $compressedFile, filesize($compressedFile), 'success');
        
        echo "[" . date('Y-m-d H:i:s') . "] Backup completed successfully\n";
        return true;
        
    } catch (Exception $e) {
        $errorMsg = $e->getMessage();
        echo "[" . date('Y-m-d H:i:s') . "] ERROR: $errorMsg\n";
        logBackup($type, null, 0, 'failed', $errorMsg);
        return false;
    }
}

/**
 * Create backup directory structure
 */
function createBackupDirectories() {
    $dirs = [
        BACKUP_DIR,
        BACKUP_DIR . '/daily',
        BACKUP_DIR . '/weekly',
        BACKUP_DIR . '/monthly',
        BACKUP_DIR . '/logs'
    ];
    
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new Exception("Failed to create directory: $dir");
            }
        }
    }
}

/**
 * Clean old backups based on retention policy
 */
function cleanOldBackups($type) {
    $backupDir = BACKUP_DIR . '/' . $type;
    
    // Determine retention period
    $retentionDays = match($type) {
        'daily' => RETENTION_DAYS,
        'weekly' => WEEKLY_RETENTION * 7,
        'monthly' => MONTHLY_RETENTION * 30,
        default => RETENTION_DAYS
    };
    
    $cutoffTime = time() - ($retentionDays * 24 * 60 * 60);
    
    $files = glob($backupDir . '/backup_*.sql*');
    $deletedCount = 0;
    $deletedSize = 0;
    
    foreach ($files as $file) {
        if (filemtime($file) < $cutoffTime) {
            $size = filesize($file);
            if (unlink($file)) {
                $deletedCount++;
                $deletedSize += $size;
            }
        }
    }
    
    if ($deletedCount > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Cleaned $deletedCount old backup(s), freed " . 
             formatBytes($deletedSize) . "\n";
    }
}

/**
 * Log backup operation
 */
function logBackup($type, $filename, $size, $status, $error = null) {
    $logFile = BACKUP_DIR . '/logs/backup_log.txt';
    $logEntry = sprintf(
        "[%s] Type: %s | Status: %s | File: %s | Size: %s | Error: %s\n",
        date('Y-m-d H:i:s'),
        $type,
        $status,
        $filename ? basename($filename) : 'N/A',
        formatBytes($size),
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

// Execute backup
$success = performBackup($backupType);
exit($success ? 0 : 1);
?>
