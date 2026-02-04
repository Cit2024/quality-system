<?php
require_once __DIR__ . '/../../config/session.php';

if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

include '../../config/DbConnection.php';
require_once '../../helpers/csrf.php';

verifyCSRFOrDie();

include '../../helpers/FormTypes.php';

//  Data verification
if (!isset($_POST['id'], $_POST['type'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit();
}

$questionId = $_POST['id'];
$newType = $_POST['type'];

if(!array_key_exists($newType, FormTypes::TYPE_QUESTION)) {
    echo json_encode(['status' => 'error', 'message' => 'نوع السؤال غير صالح']);
    exit();
}
// 1. Update Type Question
$query = "UPDATE Question SET TypeQuestion = ?  WHERE ID = ?";
$stmt = mysqli_prepare($con, $query);

mysqli_stmt_bind_param($stmt, "si", $newType, $questionId);
mysqli_stmt_execute($stmt);

// Check the number of affected rows
if (mysqli_stmt_affected_rows($stmt) > 0) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Update failed']);
}
    

mysqli_stmt_close($stmt);
mysqli_close($con);
?>
