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
$field = $_POST['field'];
$value = $_POST['value'];

// Validate the field
if (!in_array($field, ['Title', 'Description'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid field']);
    exit();
}

// Update the form in the database
$query = "UPDATE Form SET $field = ? WHERE ID = ?";
$stmt = mysqli_prepare($con, $query);
mysqli_stmt_bind_param($stmt, 'si', $value, $formId);
mysqli_stmt_execute($stmt);

if (mysqli_stmt_affected_rows($stmt) > 0) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Update failed']);
}

mysqli_stmt_close($stmt);
mysqli_close($con);
?>