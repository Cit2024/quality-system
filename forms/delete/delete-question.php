<?php
session_start();

// Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

// Include the database connection
include '../../config/DbConnection.php';

// Get the question ID from the POST request
$questionId = $_POST['id'];

// Delete the question from the database
$query = "DELETE FROM Question WHERE ID = ?";
$stmt = mysqli_prepare($con, $query);
mysqli_stmt_bind_param($stmt, 'i', $questionId);
mysqli_stmt_execute($stmt);

if (mysqli_stmt_affected_rows($stmt)) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to delete question']);
}

mysqli_stmt_close($stmt);
mysqli_close($con);
?>