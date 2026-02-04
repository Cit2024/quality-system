<?php
require_once __DIR__ . '/../../config/session.php';

// Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit();
}

// Include the database connection
include '../../config/DbConnection.php';
require_once '../../helpers/csrf.php';

verifyCSRFOrDie();

// Get the form ID from the POST request
$formId = $_POST['formId'];

// Insert a new section into the database
$query = "INSERT INTO Section (IDForm, title) VALUES (?, 'قسم جديد')";
$stmt = mysqli_prepare($con, $query);
mysqli_stmt_bind_param($stmt, 'i', $formId);
mysqli_stmt_execute($stmt);

if (mysqli_stmt_affected_rows($stmt)) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to add new section']);
}

mysqli_stmt_close($stmt);
mysqli_close($con);
?>