<?php
// statistics/router.php (corrected)
session_start();
require_once __DIR__.'/../config/DbConnection.php';
require_once __DIR__.'/../helpers/FormTypes.php';

$formTypes = FormTypes::getFormTypes($con);
$formTargets = FormTypes::getFormTargets($con);

// Sanitize inputs
$target = preg_replace('/[^a-z_]/', '', $_GET['target'] ?? 'student');
$type = preg_replace('/[^a-z_]/', '', $_GET['type'] ?? 'course_evaluation');

// Validate against variables
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


// Secure file inclusion
$baseDir = realpath(__DIR__.'/analytics/targets');
$requestedFile = realpath(__DIR__."/analytics/targets/$target/types/$type.php");

// Verify the resolved path is within the intended directory
if (!$requestedFile || strpos($requestedFile, $baseDir) !== 0) {
    header("HTTP/1.1 404 Not Found");
    exit("Analytics module not found");
}

require_once $requestedFile;