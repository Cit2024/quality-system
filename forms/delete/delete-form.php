<?php
require_once __DIR__ . '/../../config/session.php';

// Include the database connection
include '../../config/DbConnection.php';
require_once '../../helpers/csrf.php';
require_once '../../helpers/permissions.php';

// Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit();
}

// Verify admin status and refresh permissions
if (!verifyAdminStatus($con)) {
    echo json_encode(["status" => "error", "message" => "Admin account no longer valid"]);
    exit();
}

// Require delete permission
if (!hasPermission($con, 'isCanDelete')) {
    echo json_encode(["status" => "error", "message" => "Insufficient permissions"]);
    exit();
}

// Include the database connection
include '../../config/DbConnection.php';
require_once '../../helpers/csrf.php';

verifyCSRFOrDie();

// Get the form ID from the POST request and sanitize it
$formId = intval($_POST['id']);

// Use prepared statements to prevent SQL injection
try {
    // Start transaction
    mysqli_begin_transaction($con);
    
    // Delete related questions first (if they exist)
    $deleteQuestionsQuery = "DELETE FROM Question WHERE IDSection IN (SELECT ID FROM Section WHERE IDForm = ?)";
    $stmt = mysqli_prepare($con, $deleteQuestionsQuery);
    mysqli_stmt_bind_param($stmt, "i", $formId);
    mysqli_stmt_execute($stmt);
    
    // Delete related sections
    $deleteSectionsQuery = "DELETE FROM Section WHERE IDForm = ?";
    $stmt = mysqli_prepare($con, $deleteSectionsQuery);
    mysqli_stmt_bind_param($stmt, "i", $formId);
    mysqli_stmt_execute($stmt);
    
    // Now delete the form
    $deleteFormQuery = "DELETE FROM Form WHERE ID = ?";
    $stmt = mysqli_prepare($con, $deleteFormQuery);
    mysqli_stmt_bind_param($stmt, "i", $formId);
    mysqli_stmt_execute($stmt);
    
    // Commit transaction
    mysqli_commit($con);
    
    // Return a success response
    echo json_encode(["status" => "success", "message" => "Form deleted successfully"]);
    
} catch (mysqli_sql_exception $e) {
    // Rollback transaction on error
    mysqli_rollback($con);
    
    // Return an error response
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    
} finally {
    // Close the database connection
    if (isset($stmt)) {
        mysqli_stmt_close($stmt);
    }
    mysqli_close($con);
}
?>