<?php
session_start();

// Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

// Include the database connection
include '../../config/DbConnection.php';

// Get the section ID from the POST request
$sectionId = $_POST['sectionId'];

// Insert the default question into the database
$query = "INSERT INTO Question (IDSection, TitleQuestion, TypeQuestion) VALUES (?, 'سؤال إفتراضي', 'multiple_choice')";
$stmt = mysqli_prepare($con, $query);
mysqli_stmt_bind_param($stmt, 'i', $sectionId);
mysqli_stmt_execute($stmt);

if (mysqli_stmt_affected_rows($stmt) > 0) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to add default question']);
}

mysqli_stmt_close($stmt);
mysqli_close($con);
?>