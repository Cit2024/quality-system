<?php
session_start();

// Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// Include the database connection
include '../../config/DbConnection.php';

include '../form_constants.php';

// Get the form data from the POST request
$formId = $_POST['id'];
$newTarget = $_POST['target'];

// Validate the target
if (!array_key_exists($newTarget, FORM_TARGETS)) {
    echo json_encode(['status' => 'error', 'message' => 'هذا المُقيِّم غير صالح']);
    exit();
}

// Update the form target in the database
$query = "UPDATE Form SET FormTarget = ? WHERE ID = ?";
$stmt = mysqli_prepare($con, $query);
mysqli_stmt_bind_param($stmt, 'si', $newTarget, $formId);
mysqli_stmt_execute($stmt);

if (mysqli_stmt_affected_rows($stmt) > 0) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Update failed']);
}

mysqli_stmt_close($stmt);
mysqli_close($con);
?>