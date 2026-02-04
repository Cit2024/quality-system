<?php
/**
 * Deployment Configuration
 * Central configuration for deployment automation
 */

return [
    // Environment settings
    'environment' => getenv('APP_ENV') ?: 'production',
    
    // Deployment behavior
    'maintenance_mode' => true,              // Enable maintenance during deploy
    'backup_before_deploy' => true,          // Always backup before changes
    'run_migrations' => true,                // Apply database migrations
    'clear_caches' => true,                  // Clear all caches
    'verify_deployment' => true,             // Run post-deploy checks
    'rollback_on_error' => true,             // Auto-rollback on failure
    
    // Time limits
    'max_downtime_minutes' => 5,             // Maximum acceptable downtime
    'deployment_timeout_seconds' => 300,     // 5 minutes total timeout
    
    // Paths
    'project_root' => __DIR__ . '/..',
    'backup_dir' => __DIR__ . '/../backups',
    'temp_dir' => __DIR__ . '/../temp',
    'migrations_dir' => __DIR__ . '/../database/migrations',
    
    // Notifications
    'notification_enabled' => true,
    'notification_email' => 'admin@college.edu',
    'notification_webhook' => null,          // Slack/Discord webhook URL
    
    // Git settings
    'git_enabled' => true,
    'git_branch' => 'main',
    'git_remote' => 'origin',
    
    // Verification checks
    'verify_database_connection' => true,
    'verify_migrations_applied' => true,
    'verify_file_permissions' => true,
    'verify_critical_endpoints' => true,
    
    // Critical endpoints to verify post-deployment
    'critical_endpoints' => [
        '/index.php',
        '/forms/list-forms.php',
        '/evaluation-form.php?evaluation=course_evaluation&Evaluator=student',
    ],
    
    // Database settings (mapped from DbConnection.php variables)
    'db_host' => isset($host) ? $host : (defined('DB_HOST') ? DB_HOST : 'localhost'),
    'db_name' => isset($dbname) ? $dbname : (defined('DB_NAME') ? DB_NAME : 'citcoder_Quality'),
    'db_user' => isset($username) ? $username : (defined('DB_USER') ? DB_USER : 'root'),
    'db_pass' => isset($password) ? $password : (defined('DB_PASS') ? DB_PASS : ''),
    
    // Color codes for terminal output
    'colors' => [
        'success' => "\033[0;32m",  // Green
        'error' => "\033[0;31m",    // Red
        'warning' => "\033[0;33m",  // Yellow
        'info' => "\033[0;36m",     // Cyan
        'reset' => "\033[0m",       // Reset
    ],
];
?>
