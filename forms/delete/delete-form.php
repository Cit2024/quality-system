<?php
session_start();

// Check if the admin is logged in and has permission to delete forms
if (!isset($_SESSION['admin_id']) || !$_SESSION['permissions']['isCanDelete']) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit();
}

// Include the database connection
include '../../config/DbConnection.php';

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