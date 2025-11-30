<?php
// evaluation/submit.php
session_start();
require_once '../config/DbConnection.php';
require_once '../config/dbConnectionCit.php'; // Needed for student/teacher lookup
require_once '../helpers/ResponseHandler.php';

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../index.php");
    exit();
}

$handler = new ResponseHandler($con, $conn_cit);
$result = $handler->handleSubmission($_POST);

if ($result['success']) {
    // Success Redirect
    $redirectUrl = "evaluation-thankyou.php?success=1";
    
    // If it was a student evaluation, maybe redirect back to their portal?
    // We can check the form type or metadata if needed
    if (isset($_POST['return_url'])) {
        $redirectUrl .= "&path=" . urlencode($_POST['return_url']);
    }
    
    header("Location: $redirectUrl");
} else {
    // Error Redirect
    $errorMsg = urlencode($result['message']);
    header("Location: evaluation-thankyou.php?success=0&error=$errorMsg");
}
exit();
