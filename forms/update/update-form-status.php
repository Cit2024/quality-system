<?php
session_start();

// Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// Include the database connection
include '../../config/DbConnection.php';

// Get the form data from the POST request
$formId = $_POST['id'];
$newStatus = $_POST['status'];

// Validate the status
if (!in_array($newStatus, ['draft', 'published'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid form status']);
    exit();
}

// Update the form status in the database
$query = "UPDATE Form SET FormStatus = ? WHERE ID = ?";
$stmt = mysqli_prepare($con, $query);
mysqli_stmt_bind_param($stmt, 'si', $newStatus, $formId);
mysqli_stmt_execute($stmt);

if (mysqli_stmt_affected_rows($stmt) > 0) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Update failed']);
}

mysqli_stmt_close($stmt);
mysqli_close($con);
?>