<?php
/**
 * Deployment Orchestrator
 * Automates the entire deployment process with rollback capability
 * 
 * Usage:
 *   php scripts/deploy.php --env=production         - Deploy to production
 *   php scripts/deploy.php --env=production --dry-run  - Dry run (no changes)
 *   php scripts/deploy.php --migrations-only        - Only run migrations
 *   php scripts/deploy.php --force                  - Skip safety checks
 */

require_once __DIR__ . '/../config/DbConnection.php';
require_once __DIR__ . '/../config/deployment.php';

class DeploymentOrchestrator {
    private $con;
    private $config;
    private $colors;
    private $dryRun = false;
    private $force = false;
    private $migrationsOnly = false;
    private $startTime;
    private $backupFile = null;
    private $maintenanceEnabled = false;
    
    public function __construct($config, $options = []) {
        global $con;
        $this->con = $con;
        $this->config = $config;
        $this->colors = $config['colors'];
        $this->dryRun = $options['dry_run'] ?? false;
        $this->force = $options['force'] ?? false;
        $this->migrationsOnly = $options['migrations_only'] ?? false;
        $this->startTime = microtime(true);
    }
    
    /**
     * Run full deployment
     */
    public function deploy() {
        try {
            $this->printHeader();
            
            // Phase 1: Pre-deployment checks
            if (!$this->preDeploymentChecks()) {
                $this->error("Pre-deployment checks failed");
                return false;
            }
            
            // Phase 2: Backup
            if ($this->config['backup_before_deploy'] && !$this->migrationsOnly) {
                if (!$this->createBackup()) {
                    $this->error("Backup failed");
                    return false;
                }
            }
            
            // Phase 3: Enable maintenance mode
            if ($this->config['maintenance_mode'] && !$this->migrationsOnly) {
                $this->enableMaintenanceMode();
            }
            
            // Phase 4: Run migrations
            if ($this->config['run_migrations']) {
                if (!$this->runMigrations()) {
                    $this->rollback("Migration failed");
                    return false;
                }
            }
            
            // Phase 5: Clear caches
            if ($this->config['clear_caches'] && !$this->migrationsOnly) {
                $this->clearCaches();
            }
            
            // Phase 6: Verify deployment
            if ($this->config['verify_deployment']) {
                if (!$this->verifyDeployment()) {
                    $this->rollback("Verification failed");
                    return false;
                }
            }
            
            // Phase 7: Disable maintenance mode
            if ($this->maintenanceEnabled) {
                $this->disableMaintenanceMode();
            }
            
            // Phase 8: Log and notify
            $this->logDeployment('success');
            $this->success("\n🎉 Deployment completed successfully!");
            $this->printSummary();
            
            if ($this->config['notification_enabled']) {
                $this->sendNotification('success');
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->error("Deployment failed: " . $e->getMessage());
            $this->rollback($e->getMessage());
            return false;
        }
    }
    
    /**
     * Pre-deployment checks
     */
    private function preDeploymentChecks() {
        $this->info("\n=== Phase 1: Pre-Deployment Checks ===");
        
        $checks = [];
        
        // Check 1: Database connection
        $this->info("Checking database connection...");
        if (!$this->con || $this->con->connect_error) {
            $this->error("✗ Database connection failed");
            return false;
        }
        $this->success("✓ Database connected");
        $checks[] = true;
        
        // Check 2: PHP version
        $this->info("Checking PHP version...");
        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            $this->error("✗ PHP 8.0+ required, found: " . PHP_VERSION);
            return false;
        }
        $this->success("✓ PHP version: " . PHP_VERSION);
        $checks[] = true;
        
        // Check 3: Required extensions
        $this->info("Checking PHP extensions...");
        $required = ['mysqli', 'json', 'mbstring'];
        $missing = [];
        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }
        if (!empty($missing)) {
            $this->error("✗ Missing extensions: " . implode(', ', $missing));
            return false;
        }
        $this->success("✓ All required extensions loaded");
        $checks[] = true;
        
        // Check 4: Write permissions
        $this->info("Checking file permissions...");
        $dirs = [
            $this->config['backup_dir'],
            $this->config['project_root'] . '/cache',
            $this->config['project_root'] . '/logs',
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            if (!is_writable($dir)) {
                $this->error("✗ Directory not writable: $dir");
                return false;
            }
        }
        $this->success("✓ File permissions OK");
        $checks[] = true;
        
        // Check 5: Migrations directory
        $this->info("Checking migrations directory...");
        if (!is_dir($this->config['migrations_dir'])) {
            $this->error("✗ Migrations directory not found");
            return false;
        }
        $migrationsCount = count(glob($this->config['migrations_dir'] . '/*.sql'));
        $this->success("✓ Found $migrationsCount migration files");
        $checks[] = true;
        
        $passed = count(array_filter($checks));
        $total = count($checks);
        $this->info("\nPre-deployment checks: $passed/$total passed");
        
        return $passed === $total;
    }
    
    /**
     * Create backup
     */
    private function createBackup() {
        $this->info("\n=== Phase 2: Creating Backup ===");
        
        if ($this->dryRun) {
            $this->warning("[DRY RUN] Would create backup");
            return true;
        }
        
        $backupScript = $this->config['project_root'] . '/scripts/backup_database.php';
        
        if (!file_exists($backupScript)) {
            $this->error("Backup script not found");
            return false;
        }
        
        $this->info("Running backup script...");
        
        // Execute backup
        $output = [];
        $returnCode = 0;
        exec("php " . escapeshellarg($backupScript) . " --type=daily 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            $this->error("Backup failed:");
            foreach ($output as $line) {
                echo "  $line\n";
            }
            return false;
        }
        
        // Find the latest backup file
        $backupDir = $this->config['backup_dir'] . '/daily';
        $files = glob($backupDir . '/backup_*.sql*');
        if (empty($files)) {
            $this->error("No backup file created");
            return false;
        }
        
        // Get the most recent file
        usort($files, function($a, $b) {
            return filemtime($b) - filemtime($a);
        });
        $this->backupFile = $files[0];
        
        $size = filesize($this->backupFile);
        $sizeFormatted = $this->formatBytes($size);
        
        $this->success("✓ Backup created: " . basename($this->backupFile) . " ($sizeFormatted)");
        
        return true;
    }
    
    /**
     * Enable maintenance mode
     */
    private function enableMaintenanceMode() {
        $this->info("\n=== Phase 3: Enabling Maintenance Mode ===");
        
        if ($this->dryRun) {
            $this->warning("[DRY RUN] Would enable maintenance mode");
            return;
        }
        
        // Create maintenance flag file
        $flagFile = $this->config['project_root'] . '/.maintenance';
        file_put_contents($flagFile, json_encode([
            'enabled' => true,
            'started_at' => date('Y-m-d H:i:s'),
            'reason' => 'Deployment in progress'
        ]));
        
        $this->maintenanceEnabled = true;
        $this->warning("⚠ Maintenance mode ENABLED");
    }
    
    /**
     * Run migrations
     */
    private function runMigrations() {
        $this->info("\n=== Phase 4: Running Migrations ===");
        
        $migrateScript = $this->config['project_root'] . '/scripts/migrate.php';
        
        if (!file_exists($migrateScript)) {
            $this->error("Migration script not found");
            return false;
        }
        
        if ($this->dryRun) {
            $this->warning("[DRY RUN] Would run migrations");
            return true;
        }
        
        // Execute migrations
        $output = [];
        $returnCode = 0;
        exec("php " . escapeshellarg($migrateScript) . " up 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            $this->error("Migrations failed:");
            foreach ($output as $line) {
                echo "  $line\n";
            }
            return false;
        }
        
        foreach ($output as $line) {
            echo "  $line\n";
        }
        
        $this->success("✓ Migrations completed");
        return true;
    }
    
    /**
     * Clear caches
     */
    private function clearCaches() {
        $this->info("\n=== Phase 5: Clearing Caches ===");
        
        if ($this->dryRun) {
            $this->warning("[DRY RUN] Would clear caches");
            return;
        }
        
        $cacheDir = $this->config['project_root'] . '/cache';
        if (is_dir($cacheDir)) {
            $files = glob($cacheDir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            $this->success("✓ Cache cleared");
        } else {
            $this->info("No cache directory found");
        }
        
        // Clear OPcache if available
        if (function_exists('opcache_reset')) {
            opcache_reset();
            $this->success("✓ OPcache cleared");
        }
    }
    
    /**
     * Verify deployment
     */
    private function verifyDeployment() {
        $this->info("\n=== Phase 6: Verifying Deployment ===");
        
        if ($this->dryRun) {
            $this->warning("[DRY RUN] Would verify deployment");
            return true;
        }
        
        $verifyScript = $this->config['project_root'] . '/scripts/verify.php';
        
        if (!file_exists($verifyScript)) {
            $this->warning("Verification script not found, skipping");
            return true;
        }
        
        // Execute verification
        $output = [];
        $returnCode = 0;
        exec("php " . escapeshellarg($verifyScript) . " 2>&1", $output, $returnCode);
        
        if ($returnCode !== 0) {
            $this->error("Verification failed:");
            foreach ($output as $line) {
                echo "  $line\n";
            }
            return false;
        }
        
        $this->success("✓ Verification passed");
        return true;
    }
    
    /**
     * Disable maintenance mode
     */
    private function disableMaintenanceMode() {
        $this->info("\n=== Phase 7: Disabling Maintenance Mode ===");
        
        if ($this->dryRun) {
            $this->warning("[DRY RUN] Would disable maintenance mode");
            return;
        }
        
        $flagFile = $this->config['project_root'] . '/.maintenance';
        if (file_exists($flagFile)) {
            unlink($flagFile);
        }
        
        $this->maintenanceEnabled = false;
        $this->success("✓ Maintenance mode DISABLED");
    }
    
    /**
     * Rollback on failure
     */
    private function rollback($reason) {
        $this->error("\n⚠ ROLLING BACK: $reason");
        
        if (!$this->config['rollback_on_error']) {
            $this->warning("Auto-rollback disabled, manual intervention required");
            return;
        }
        
        if ($this->dryRun) {
            $this->warning("[DRY RUN] Would rollback");
            return;
        }
        
        // Disable maintenance mode first
        if ($this->maintenanceEnabled) {
            $this->disableMaintenanceMode();
        }
        
        // Restore backup if available
        if ($this->backupFile && file_exists($this->backupFile)) {
            $this->warning("Restoring database backup...");
            
            $restoreScript = $this->config['project_root'] . '/scripts/restore_database.php';
            if (file_exists($restoreScript)) {
                // Auto-restore (non-interactive)
                $dbHost = $this->config['db_host'];
                $dbName = $this->config['db_name'];
                $dbUser = $this->config['db_user'];
                $dbPass = $this->config['db_pass'];
                
                // Decompress if needed
                $sqlFile = $this->backupFile;
                if (pathinfo($this->backupFile, PATHINFO_EXTENSION) === 'gz') {
                    $sqlFile = str_replace('.gz', '', $this->backupFile);
                    exec("gunzip -c " . escapeshellarg($this->backupFile) . " > " . escapeshellarg($sqlFile));
                }
                
                // Restore
                $cmd = sprintf(
                    'mysql --host=%s --user=%s --password=%s %s < %s 2>&1',
                    escapeshellarg($dbHost),
                    escapeshellarg($dbUser),
                    escapeshellarg($dbPass),
                    escapeshellarg($dbName),
                    escapeshellarg($sqlFile)
                );
                
                exec($cmd, $output, $returnCode);
                
                if ($returnCode === 0) {
                    $this->success("✓ Database restored from backup");
                } else {
                    $this->error("✗ Restore failed, manual intervention required");
                }
                
                // Clean up decompressed file
                if ($sqlFile !== $this->backupFile && file_exists($sqlFile)) {
                    unlink($sqlFile);
                }
            }
        }
        
        $this->logDeployment('failed', $reason);
        
        if ($this->config['notification_enabled']) {
            $this->sendNotification('failed', $reason);
        }
    }
    
    /**
     * Log deployment
     */
    private function logDeployment($status, $error = null) {
        $logFile = $this->config['project_root'] . '/logs/deployment.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $duration = round(microtime(true) - $this->startTime, 2);
        
        $logEntry = sprintf(
            "[%s] Status: %s | Duration: %ss | Backup: %s | Error: %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($status),
            $duration,
            $this->backupFile ? basename($this->backupFile) : 'N/A',
            $error ?? 'None'
        );
        
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
    
    /**
     * Send notification
     */
    private function sendNotification($status, $error = null) {
        if (!$this->config['notification_email']) {
            return;
        }
        
        $subject = sprintf(
            "[Quality System] Deployment %s - %s",
            strtoupper($status),
            date('Y-m-d H:i:s')
        );
        
        $message = "Deployment Status: " . strtoupper($status) . "\n";
        $message .= "Environment: " . $this->config['environment'] . "\n";
        $message .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
        $message .= "Duration: " . round(microtime(true) - $this->startTime, 2) . "s\n";
        
        if ($error) {
            $message .= "Error: $error\n";
        }
        
        // Send email (simplified, would use proper email library in production)
        @mail($this->config['notification_email'], $subject, $message);
    }
    
    /**
     * Print header
     */
    private function printHeader() {
        echo "\n";
        echo $this->colorize(str_repeat("=", 80), 'info') . "\n";
        echo $this->colorize("  Quality System - Deployment Orchestrator", 'info') . "\n";
        echo $this->colorize(str_repeat("=", 80), 'info') . "\n";
        echo "\nEnvironment: " . $this->config['environment'] . "\n";
        echo "Started: " . date('Y-m-d H:i:s') . "\n";
        
        if ($this->dryRun) {
            echo $this->colorize("\n⚠ DRY RUN MODE - No changes will be made\n", 'warning');
        }
        if ($this->force) {
            echo $this->colorize("⚠ FORCE MODE - Safety checks bypassed\n", 'warning');
        }
        if ($this->migrationsOnly) {
            echo $this->colorize("ℹ Migrations only mode\n", 'info');
        }
    }
    
    /**
     * Print summary
     */
    private function printSummary() {
        $duration = round(microtime(true) - $this->startTime, 2);
        
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "Deployment Summary:\n";
        echo "  Total Time: {$duration}s\n";
        echo "  Backup: " . ($this->backupFile ? basename($this->backupFile) : 'N/A') . "\n";
        echo "  Status: " . $this->colorize("SUCCESS", 'success') . "\n";
        echo str_repeat("=", 80) . "\n";
    }
    
    /**
     * Format bytes
     */
    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Colorize output
     */
    private function colorize($text, $type) {
        return $this->colors[$type] . $text . $this->colors['reset'];
    }
    
    private function success($msg) {
        echo $this->colorize($msg, 'success') . "\n";
    }
    
    private function error($msg) {
        echo $this->colorize($msg, 'error') . "\n";
    }
    
    private function warning($msg) {
        echo $this->colorize($msg, 'warning') . "\n";
    }
    
    private function info($msg) {
        echo $this->colorize($msg, 'info') . "\n";
    }
}

// Main execution
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line\n");
}

// Parse arguments
$options = [
    'dry_run' => in_array('--dry-run', $argv),
    'force' => in_array('--force', $argv),
    'migrations_only' => in_array('--migrations-only', $argv),
    'notify' => in_array('--notify', $argv),
];

// Validate environment
$env = null;
foreach ($argv as $arg) {
    if (strpos($arg, '--env=') === 0) {
        $env = substr($arg, 6);
    }
}

if (!$env && !$options['migrations_only']) {
    echo "Error: Environment required\n";
    echo "Usage: php deploy.php --env=production [options]\n";
    echo "Options:\n";
    echo "  --dry-run          Show what would be done\n";
    echo "  --force            Skip safety checks\n";
    echo "  --migrations-only  Only run migrations\n";
    echo "  --notify           Send notifications\n";
    exit(1);
}

// Load config
$config = require __DIR__ . '/../config/deployment.php';

// Update config with CLI options
if ($options['notify']) {
    $config['notification_enabled'] = true;
}

// Run deployment
$orchestrator = new DeploymentOrchestrator($config, $options);
$success = $orchestrator->deploy();

exit($success ? 0 : 1);
?>
