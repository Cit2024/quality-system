<?php
// members/delete/delete_admin.php
include '../../config/DbConnection.php';

// Set the Content-Type header to JSON
header('Content-Type: application/json');

// Clear any previous output
ob_clean();

if (isset($_GET['id'])) {
    $adminId = $_GET['id'];

    // Mark the admin as deleted
    $query = "UPDATE Admin SET is_deleted = 1 WHERE ID = ?";
    $stmt = mysqli_prepare($con, $query);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $adminId);

        if (mysqli_stmt_execute($stmt)) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => mysqli_error($con)]);
        }

        mysqli_stmt_close($stmt);
    } else {
        echo json_encode(['success' => false, 'error' => mysqli_error($con)]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Admin ID not provided']);
}
?>