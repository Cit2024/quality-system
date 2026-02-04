<?php
/**
 * Deployment Verification Script
 * Verifies system health post-deployment
 * 
 * Usage:
 *   php scripts/verify.php           - Run all checks
 *   php scripts/verify.php --verbose - Detailed output
 */

require_once __DIR__ . '/../config/DbConnection.php';
require_once __DIR__ . '/../config/deployment.php';

class DeploymentVerifier {
    private $con;
    private $config;
    private $verbose;
    private $colors;
    private $failures = [];
    
    public function __construct($config, $verbose = false) {
        global $con;
        $this->con = $con;
        $this->config = $config;
        $this->verbose = $verbose;
        $this->colors = $config['colors'];
    }
    
    /**
     * Run all verification checks
     */
    public function runAll() {
        $this->info("Deployment Verification\n" . str_repeat("=", 80));
        
        $checks = [
            'Database Connection' => [$this, 'checkDatabaseConnection'],
            'PHP Extensions' => [$this, 'checkPHPExtensions'],
            'File Permissions' => [$this, 'checkFilePermissions'],
            'Migration Status' => [$this, 'checkMigrations'],
            'Configuration Files' => [$this, 'checkConfiguration'],
            'Critical Tables' => [$this, 'checkCriticalTables'],
            'Audit Logging' => [$this, 'checkAuditLog'],
            'Backup System' => [$this, 'checkBackupSystem'],
        ];
        
        $passed = 0;
        $total = count($checks);
        
        foreach ($checks as $name => $callback) {
            echo "\n";
            $this->info("Checking: $name");
            
            try {
                if (call_user_func($callback)) {
                    $this->success("✓ $name: OK");
                    $passed++;
                } else {
                    $this->error("✗ $name: FAILED");
                }
            } catch (Exception $e) {
                $this->error("✗ $name: ERROR - " . $e->getMessage());
                $this->failures[] = "$name: " . $e->getMessage();
            }
        }
        
        // Summary
        echo "\n" . str_repeat("=", 80) . "\n";
        $this->info("Verification Summary:");
        $this->info("Passed: $passed / $total");
        
        if ($passed === $total) {
            $this->success("\n🎉 All checks passed! System is healthy.");
            return true;
        } else {
            $failed = $total - $passed;
            $this->error("\n⚠ $failed check(s) failed. Please review and fix.");
            
            if (!empty($this->failures)) {
                echo "\nFailures:\n";
                foreach ($this->failures as $failure) {
                    $this->error("  - $failure");
                }
            }
            return false;
        }
    }
    
    /**
     * Check database connection
     */
    private function checkDatabaseConnection() {
        if (!$this->con || $this->con->connect_error) {
            throw new Exception("Database connection failed");
        }
        
        // Test query
        $result = $this->con->query("SELECT 1");
        if (!$result) {
            throw new Exception("Test query failed");
        }
        
        if ($this->verbose) {
            echo "  Connected to: " . $this->config['db_name'] . "\n";
        }
        
        return true;
    }
    
    /**
     * Check required PHP extensions
     */
    private function checkPHPExtensions() {
        $required = ['mysqli', 'json', 'mbstring'];
        $missing = [];
        
        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }
        
        if (!empty($missing)) {
            throw new Exception("Missing extensions: " . implode(', ', $missing));
        }
        
        if ($this->verbose) {
            echo "  PHP Version: " . PHP_VERSION . "\n";
            echo "  Extensions: " . implode(', ', $required) . "\n";
        }
        
        return true;
    }
    
    /**
     * Check file permissions
     */
    private function checkFilePermissions() {
        $dirs = [
            $this->config['backup_dir'] => 'writable',
            $this->config['project_root'] . '/cache' => 'writable',
            $this->config['project_root'] . '/logs' => 'writable',
        ];
        
        foreach ($dirs as $dir => $permission) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            if (!is_writable($dir)) {
                throw new Exception("Directory not writable: $dir");
            }
            
            if ($this->verbose) {
                echo "  ✓ $dir\n";
            }
        }
        
        return true;
    }
    
    /**
     * Check migration status
     */
    private function checkMigrations() {
        // Check if Migrations table exists
        $result = $this->con->query("SHOW TABLES LIKE 'Migrations'");
        if ($result->num_rows === 0) {
            throw new Exception("Migrations table does not exist");
        }
        
        // Get pending migrations
        $migrationsDir = $this->config['migrations_dir'];
        $files = glob($migrationsDir . '/*.sql');
        $totalMigrations = count($files);
        
        $stmt = $this->con->query("
            SELECT COUNT(*) as count 
            FROM Migrations 
            WHERE Status = 'applied'
        ");
        $row = $stmt->fetch_assoc();
        $appliedCount = $row['count'];
        
        if ($this->verbose) {
            echo "  Total migrations: $totalMigrations\n";
            echo "  Applied: $appliedCount\n";
        }
        
        // Check for failed migrations
        $stmt = $this->con->query("
            SELECT Migration, ErrorMessage 
            FROM Migrations 
            WHERE Status = 'failed'
        ");
        
        if ($stmt->num_rows > 0) {
            $failures = [];
            while ($row = $stmt->fetch_assoc()) {
                $failures[] = $row['Migration'];
            }
            throw new Exception("Failed migrations: " . implode(', ', $failures));
        }
        
        return true;
    }
    
    /**
     * Check configuration files
     */
    private function checkConfiguration() {
        $files = [
            'config/DbConnection.php',
            'config/constants.php',
            'config/error_messages.php',
            'config/deployment.php',
        ];
        
        foreach ($files as $file) {
            $path = $this->config['project_root'] . '/' . $file;
            if (!file_exists($path)) {
                throw new Exception("Missing configuration file: $file");
            }
            
            if ($this->verbose) {
                echo "  ✓ $file\n";
            }
        }
        
        return true;
    }
    
    /**
     * Check critical database tables
     */
    private function checkCriticalTables() {
        $tables = [
            'Form',
            'Section',
            'Question',
            'FormTypes',
            'EvaluatorTypes',
            'EvaluationResponses',
            'FormAccessFields',
            'AuditLog',
            'Migrations',
        ];
        
        foreach ($tables as $table) {
            $result = $this->con->query("SHOW TABLES LIKE '$table'");
            if ($result->num_rows === 0) {
                throw new Exception("Missing table: $table");
            }
            
            if ($this->verbose) {
                $count = $this->con->query("SELECT COUNT(*) as count FROM `$table`")->fetch_assoc()['count'];
                echo "  ✓ $table ($count rows)\n";
            }
        }
        
        return true;
    }
    
    /**
     * Check audit logging is functional
     */
    private function checkAuditLog() {
        // Try to write a test entry
        $stmt = $this->con->prepare("
            INSERT INTO AuditLog (Action, EntityType, Status, UserType)
            VALUES ('deployment_verification', 'System', 'success', 'system')
        ");
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to write to audit log");
        }
        
        $insertId = $this->con->insert_id;
        $stmt->close();
        
        // Verify entry was written
        $stmt = $this->con->prepare("SELECT ID FROM AuditLog WHERE ID = ?");
        $stmt->bind_param("i", $insertId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("Audit log entry not found");
        }
        
        $stmt->close();
        
        if ($this->verbose) {
            echo "  Test entry ID: $insertId\n";
        }
        
        return true;
    }
    
    /**
     * Check backup system
     */
    private function checkBackupSystem() {
        $backupScript = $this->config['project_root'] . '/scripts/backup_database.php';
        
        if (!file_exists($backupScript)) {
            throw new Exception("Backup script not found");
        }
        
        if (!is_executable($backupScript)) {
            // Try to make it executable
            chmod($backupScript, 0755);
        }
        
        // Check backup directories
        $dirs = ['daily', 'weekly', 'monthly', 'logs'];
        foreach ($dirs as $dir) {
            $path = $this->config['backup_dir'] . '/' . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }
            
            if ($this->verbose) {
                echo "  ✓ Backup dir: $dir/\n";
            }
        }
        
        return true;
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

$config = require __DIR__ . '/../config/deployment.php';
$verbose = in_array('--verbose', $argv) || in_array('-v', $argv);

$verifier = new DeploymentVerifier($config, $verbose);
$success = $verifier->runAll();

exit($success ? 0 : 1);
?>
