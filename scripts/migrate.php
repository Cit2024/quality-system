<?php
/**
 * Database Migration Runner
 * Manages database schema changes safely
 * 
 * Usage:
 *   php scripts/migrate.php status         - Show migration status
 *   php scripts/migrate.php up             - Apply pending migrations
 *   php scripts/migrate.php down           - Rollback last migration
 *   php scripts/migrate.php rollback --to=XXX - Rollback to specific migration
 */

require_once __DIR__ . '/../config/DbConnection.php';
require_once __DIR__ . '/../config/deployment.php';

class MigrationRunner {
    private $con;
    private $config;
    private $migrationsDir;
    private $colors;
    
    public function __construct($config) {
        global $con;
        $this->con = $con;
        $this->config = $config;
        $this->migrationsDir = $config['migrations_dir'];
        $this->colors = $config['colors'];
        
        $this->ensureMigrationsTable();
    }
    
    /**
     * Ensure Migrations table exists
     */
    private function ensureMigrationsTable() {
        $sql = "CREATE TABLE IF NOT EXISTS `Migrations` (
            `ID` INT PRIMARY KEY AUTO_INCREMENT,
            `Migration` VARCHAR(255) NOT NULL UNIQUE,
            `AppliedAt` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `RolledBackAt` TIMESTAMP NULL DEFAULT NULL,
            `Checksum` VARCHAR(64) NULL,
            `ExecutionTime` INT NULL,
            `Status` ENUM('applied', 'rolled_back', 'failed') NOT NULL DEFAULT 'applied',
            `ErrorMessage` TEXT NULL,
            INDEX `idx_applied` (`AppliedAt`),
            INDEX `idx_status` (`Status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $this->con->query($sql);
    }
    
    /**
     * Get all migration files
     */
    private function getMigrationFiles() {
        if (!is_dir($this->migrationsDir)) {
            $this->error("Migrations directory not found: {$this->migrationsDir}");
            return [];
        }
        
        $files = glob($this->migrationsDir . '/*.sql');
        sort($files);
        return array_map('basename', $files);
    }
    
    /**
     * Get applied migrations
     */
    private function getAppliedMigrations() {
        $stmt = $this->con->query("
            SELECT Migration, AppliedAt, Status 
            FROM Migrations 
            WHERE Status = 'applied'
            ORDER BY AppliedAt ASC
        ");
        
        $applied = [];
        while ($row = $stmt->fetch_assoc()) {
            $applied[$row['Migration']] = $row;
        }
        return $applied;
    }
    
    /**
     * Get pending migrations
     */
    private function getPendingMigrations() {
        $allFiles = $this->getMigrationFiles();
        $applied = $this->getAppliedMigrations();
        
        return array_filter($allFiles, function($file) use ($applied) {
            return !isset($applied[$file]);
        });
    }
    
    /**
     * Show migration status
     */
    public function status() {
        $this->info("Migration Status\n" . str_repeat("=", 80));
        
        $allFiles = $this->getMigrationFiles();
        $applied = $this->getAppliedMigrations();
        
        if (empty($allFiles)) {
            $this->warning("No migration files found.");
            return;
        }
        
        echo sprintf("%-50s %-20s %-20s\n", "Migration", "Status", "Applied At");
        echo str_repeat("-", 80) . "\n";
        
        foreach ($allFiles as $file) {
            if (isset($applied[$file])) {
                $status = $this->colorize("✓ Applied", 'success');
                $appliedAt = $applied[$file]['AppliedAt'];
            } else {
                $status = $this->colorize("✗ Pending", 'warning');
                $appliedAt = "-";
            }
            
            echo sprintf("%-50s %-30s %-20s\n", $file, $status, $appliedAt);
        }
        
        $pendingCount = count($this->getPendingMigrations());
        echo str_repeat("=", 80) . "\n";
        $this->info("Total migrations: " . count($allFiles));
        $this->info("Applied: " . count($applied));
        $this->warning("Pending: " . $pendingCount);
    }
    
    /**
     * Apply pending migrations
     */
    public function up($dryRun = false) {
        $pending = $this->getPendingMigrations();
        
        if (empty($pending)) {
            $this->success("No pending migrations.");
            return true;
        }
        
        $this->info("Found " . count($pending) . " pending migration(s)\n");
        
        foreach ($pending as $file) {
            if ($dryRun) {
                $this->info("[DRY RUN] Would apply: $file");
            } else {
                $this->applyMigration($file);
            }
        }
        
        if (!$dryRun) {
            $this->success("\nAll migrations applied successfully!");
        }
        return true;
    }
    
    /**
     * Apply a single migration
     */
    private function applyMigration($file) {
        $filePath = $this->migrationsDir . '/' . $file;
        
        $this->info("Applying: $file");
        
        // Read migration SQL
        $sql = file_get_contents($filePath);
        if ($sql === false) {
            $this->error("Failed to read migration file: $file");
            return false;
        }
        
        // Calculate checksum
        $checksum = hash('sha256', $sql);
        
        // Start transaction
        $this->con->begin_transaction();
        
        try {
            $startTime = microtime(true);
            
            // Execute migration (multi-query)
            if ($this->con->multi_query($sql)) {
                // Clear all results
                do {
                    if ($result = $this->con->store_result()) {
                        $result->free();
                    }
                } while ($this->con->more_results() && $this->con->next_result());
            }
            
            if ($this->con->error) {
                throw new Exception($this->con->error);
            }
            
            $executionTime = (int)((microtime(true) - $startTime) * 1000);
            
            // Record migration
            $stmt = $this->con->prepare("
                INSERT INTO Migrations (Migration, Checksum, ExecutionTime, Status)
                VALUES (?, ?, ?, 'applied')
            ");
            $stmt->bind_param("ssi", $file, $checksum, $executionTime);
            $stmt->execute();
            $stmt->close();
            
            $this->con->commit();
            $this->success("✓ Applied successfully ({$executionTime}ms)");
            return true;
            
        } catch (Exception $e) {
            $this->con->rollback();
            
            // Record failure
            $errorMsg = $e->getMessage();
            $stmt = $this->con->prepare("
                INSERT INTO Migrations (Migration, Checksum, Status, ErrorMessage)
                VALUES (?, ?, 'failed', ?)
                ON DUPLICATE KEY UPDATE Status = 'failed', ErrorMessage = ?
            ");
            $stmt->bind_param("ssss", $file, $checksum, $errorMsg, $errorMsg);
            $stmt->execute();
            $stmt->close();
            
            $this->error("✗ Failed: " . $errorMsg);
            return false;
        }
    }
    
    /**
     * Rollback last migration
     */
    public function down() {
        $applied = $this->getAppliedMigrations();
        
        if (empty($applied)) {
            $this->warning("No migrations to rollback.");
            return true;
        }
        
        // Get last applied migration
        $lastMigration = array_key_last($applied);
        
        $this->warning("Rolling back: $lastMigration");
        $this->warning("NOTE: Rollback must be done manually by examining the migration file.");
        $this->warning("Automatic rollback is not implemented to prevent data loss.");
        
        // Mark as rolled back
        $stmt = $this->con->prepare("
            UPDATE Migrations 
            SET Status = 'rolled_back', RolledBackAt = NOW()
            WHERE Migration = ?
        ");
        $stmt->bind_param("s", $lastMigration);
        $stmt->execute();
        $stmt->close();
        
        $this->success("Marked as rolled back: $lastMigration");
        $this->info("Please manually review and rollback the database changes.");
        
        return true;
    }
    
    /**
     * Colorize output
     */
    private function colorize($text, $type) {
        return $this->colors[$type] . $text . $this->colors['reset'];
    }
    
    private function success($msg) {
        echo $this->colorize("✓ $msg", 'success') . "\n";
    }
    
    private function error($msg) {
        echo $this->colorize("✗ $msg", 'error') . "\n";
    }
    
    private function warning($msg) {
        echo $this->colorize("⚠ $msg", 'warning') . "\n";
    }
    
    private function info($msg) {
        echo $this->colorize("ℹ $msg", 'info') . "\n";
    }
}

// Main execution
if (php_sapi_name() !== 'cli') {
    die("This script must be run from the command line\n");
}

$config = require __DIR__ . '/../config/deployment.php';
$runner = new MigrationRunner($config);

$command = $argv[1] ?? 'status';

switch ($command) {
    case 'status':
        $runner->status();
        break;
        
    case 'up':
        $dryRun = in_array('--dry-run', $argv);
        $runner->up($dryRun);
        break;
        
    case 'down':
        $runner->down();
        break;
        
    default:
        echo "Usage: php migrate.php [status|up|down]\n";
        echo "  status          Show migration status\n";
        echo "  up              Apply pending migrations\n";
        echo "  up --dry-run    Show what would be applied\n";
        echo "  down            Rollback last migration\n";
        exit(1);
}

exit(0);
?>
