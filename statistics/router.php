<?php
// statistics/router.php
session_start();
require_once __DIR__.'/../config/DbConnection.php';
require_once __DIR__.'/../helpers/FormTypes.php';
require_once __DIR__.'/analytics/config/ConfigurationLoader.php';

$formTypes = FormTypes::getFormTypes($con);
$formTargets = FormTypes::getFormTargets($con);

// Sanitize inputs
$target = preg_replace('/[^a-z_]/', '', $_GET['target'] ?? 'student');
$type = preg_replace('/[^a-z_]/', '', $_GET['type'] ?? 'course_evaluation');

// Validate against database
if (!isset($formTargets[$target])) {
    header("HTTP/1.1 400 Bad Request");
    exit("Invalid evaluation target");
}

if (!isset($formTypes[$type])) {
    header("HTTP/1.1 400 Bad Request");
    exit("Invalid form type");
}

// Check allowed combinations
if (!in_array($target, $formTypes[$type]['allowed_targets'], true)) {
    header("HTTP/1.1 403 Forbidden");
    exit("Invalid target-type combination");
}

// NEW: Check configuration for routing strategy
try {
    // Check if this combination uses a custom file
    if (ConfigurationLoader::usesCustomFile($type, $target)) {
        $customFile = ConfigurationLoader::getCustomFilePath($type, $target);
        
        if ($customFile && file_exists($customFile)) {
            require_once $customFile;
            exit;
        } else {
            // Custom file configured but missing - log error and fall back
            error_log("Custom file configured but not found: $customFile");
        }
    }
    
    // Check if configuration exists for generic template
    $config = ConfigurationLoader::getConfig($type, $target);
    
    if ($config) {
        // Use generic template
        require_once __DIR__.'/analytics/templates/GenericEvaluationTemplate.php';
        $template = new GenericEvaluationTemplate($type, $target);
        $template->render($_GET);
        exit;
    }
    
    // No configuration found - fall back to old system
    $baseDir = realpath(__DIR__.'/analytics/targets');
    $requestedFile = realpath(__DIR__."/analytics/targets/$target/types/$type.php");

    // Verify the resolved path is within the intended directory
    if (!$requestedFile || strpos($requestedFile, $baseDir) !== 0) {
        header("HTTP/1.1 404 Not Found");
        exit("Analytics module not found");
    }

    require_once $requestedFile;
    
} catch (Exception $e) {
    error_log("Router error: " . $e->getMessage());
    header("HTTP/1.1 500 Internal Server Error");
    exit("Unable to load statistics module");
}