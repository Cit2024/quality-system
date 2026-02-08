<?php
/**
 * Router Debug Script
 * Simulates router.php execution with detailed logging
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== ROUTER DEBUG TRACE ===\n\n";

// Simulate GET parameters
$_GET = [
    'target' => 'student',
    'type' => 'course_evaluation',
    'courseId' => 'م.ع 302',
    'semester' => '74'
];

echo "Input Parameters:\n";
foreach ($_GET as $key => $value) {
    echo "  $key = '$value'\n";
}
echo "\n";

// Step 1: Check database connections
echo "STEP 1: Database Connections\n";
require_once __DIR__ . '/../config/DbConnection.php';
require_once __DIR__ . '/../helpers/FormTypes.php';

if (!$con) {
    die("❌ Database connection failed\n");
}
echo "✅ Database connected\n\n";

// Step 2: Check ConfigurationLoader
echo "STEP 2: ConfigurationLoader Check\n";
$configLoaderPath = __DIR__.'/analytics/config/ConfigurationLoader.php';
echo "Path: $configLoaderPath\n";
echo "Exists: " . (file_exists($configLoaderPath) ? 'YES' : 'NO') . "\n";
echo "Readable: " . (is_readable($configLoaderPath) ? 'YES' : 'NO') . "\n";

$useConfigLoader = false;
if (file_exists($configLoaderPath) && is_readable($configLoaderPath)) {
    try {
        require_once $configLoaderPath;
        $useConfigLoader = true;
        echo "✅ ConfigurationLoader loaded\n";
    } catch (Exception $e) {
        echo "❌ ConfigurationLoader failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "⚠️  ConfigurationLoader not available\n";
}
echo "\n";

// Step 3: Validate form types
echo "STEP 3: Form Type Validation\n";
$formTypes = FormTypes::getFormTypes($con);
$formTargets = FormTypes::getFormTargets($con);

$target = preg_replace('/[^a-z_]/', '', $_GET['target'] ?? 'student');
$type = preg_replace('/[^a-z_]/', '', $_GET['type'] ?? 'course_evaluation');

echo "Sanitized target: '$target'\n";
echo "Sanitized type: '$type'\n";
echo "Target valid: " . (isset($formTargets[$target]) ? 'YES' : 'NO') . "\n";
echo "Type valid: " . (isset($formTypes[$type]) ? 'YES' : 'NO') . "\n";

if (isset($formTypes[$type])) {
    echo "Allowed targets for '$type': " . implode(', ', $formTypes[$type]['allowed_targets']) . "\n";
    echo "Combination valid: " . (in_array($target, $formTypes[$type]['allowed_targets'], true) ? 'YES' : 'NO') . "\n";
}
echo "\n";

// Step 4: Check ConfigurationLoader routing
if ($useConfigLoader) {
    echo "STEP 4: ConfigurationLoader Routing\n";
    
    $usesCustom = ConfigurationLoader::usesCustomFile($type, $target);
    echo "Uses custom file: " . ($usesCustom ? 'YES' : 'NO') . "\n";
    
    if ($usesCustom) {
        $customFile = ConfigurationLoader::getCustomFilePath($type, $target);
        echo "Custom file path: $customFile\n";
        echo "Custom file exists: " . (file_exists($customFile) ? 'YES' : 'NO') . "\n";
    }
    
    $config = ConfigurationLoader::getConfig($type, $target);
    echo "Has config: " . ($config ? 'YES' : 'NO') . "\n";
    echo "\n";
}

// Step 5: Check fallback file routing
echo "STEP 5: Fallback File Routing\n";
$baseDir = realpath(__DIR__.'/analytics/targets/views');
$requestedFile = realpath(__DIR__."/analytics/targets/views/$type.php");

echo "Base directory: $baseDir\n";
echo "Base dir exists: " . ($baseDir ? 'YES' : 'NO') . "\n";
echo "Requested file: $requestedFile\n";
echo "Requested file exists: " . ($requestedFile && file_exists($requestedFile) ? 'YES' : 'NO') . "\n";

if ($requestedFile && $baseDir) {
    echo "Path validation: " . (strpos($requestedFile, $baseDir) === 0 ? 'PASS' : 'FAIL') . "\n";
}
echo "\n";

// Step 6: List available files
echo "STEP 6: Available Analytics Files\n";
$viewsDir = __DIR__.'/analytics/targets/views';
if (is_dir($viewsDir)) {
    $files = scandir($viewsDir);
    echo "Files in analytics/targets/views/:\n";
    foreach ($files as $file) {
        if ($file !== '.' && $file !== '..') {
            echo "  - $file\n";
        }
    }
} else {
    echo "❌ Directory does not exist: $viewsDir\n";
}
echo "\n";

// Step 7: Final decision
echo "=== ROUTING DECISION ===\n";
if ($useConfigLoader) {
    if (ConfigurationLoader::usesCustomFile($type, $target)) {
        $customFile = ConfigurationLoader::getCustomFilePath($type, $target);
        if ($customFile && file_exists($customFile)) {
            echo "✅ Would route to: CUSTOM FILE\n";
            echo "   Path: $customFile\n";
        } else {
            echo "⚠️  Custom file configured but missing, falling back\n";
        }
    } elseif (ConfigurationLoader::getConfig($type, $target)) {
        echo "✅ Would route to: GENERIC TEMPLATE\n";
    }
}

if ($requestedFile && file_exists($requestedFile) && strpos($requestedFile, $baseDir) === 0) {
    echo "✅ Would route to: FALLBACK FILE\n";
    echo "   Path: $requestedFile\n";
} else {
    echo "❌ ROUTING FAILED - 404 Not Found\n";
    echo "   Reason: File not found or path validation failed\n";
}

echo "\n=== DEBUG COMPLETE ===\n";
