<?php
require_once __DIR__ . '/../../config/session.php';

// Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

// Include the database connection
include '../../config/DbConnection.php';
require_once '../../helpers/csrf.php';

verifyCSRFOrDie();


require_once '../../helpers/FormTypes.php';

// Get the form data from the POST request
$formId = $_POST['id'];
$newType = $_POST['type'];

$formTypes = FormTypes::getFormTypes($con);

// Validate the type
if (!array_key_exists($newType, $formTypes)) { 
    echo json_encode(['status' => 'error', 'message' => 'نوع النموذج غير صالح']);
    exit();
}

// Get current FormTarget to validate combination
$checkStmt = mysqli_prepare($con, "SELECT FormTarget FROM Form WHERE ID = ?");
mysqli_stmt_bind_param($checkStmt, 'i', $formId);
mysqli_stmt_execute($checkStmt);
$result = mysqli_stmt_get_result($checkStmt);
$currentForm = mysqli_fetch_assoc($result);
mysqli_stmt_close($checkStmt);

if (!$currentForm) {
    echo json_encode(['status' => 'error', 'message' => 'النموذج غير موجود']);
    exit();
}

// Validate that the new type + current target combination is allowed
$validateStmt = mysqli_prepare($con, "
    SELECT COUNT(*) as count
    FROM FormTypes ft
    JOIN FormType_EvaluatorType fte ON ft.ID = fte.FormTypeID
    JOIN EvaluatorTypes et ON fte.EvaluatorTypeID = et.ID
    WHERE ft.Slug = ? AND et.Slug = ?
");
mysqli_stmt_bind_param($validateStmt, 'ss', $newType, $currentForm['FormTarget']);
mysqli_stmt_execute($validateStmt);
$validateResult = mysqli_stmt_get_result($validateStmt);
$isValid = mysqli_fetch_assoc($validateResult)['count'] > 0;
mysqli_stmt_close($validateStmt);

if (!$isValid) {
    echo json_encode(['status' => 'error', 'message' => 'هذا النوع غير متوافق مع المُقيِّم الحالي']);
    exit();
}

// Update the form type in the database
$query = "UPDATE Form SET FormType = ? WHERE ID = ?";
$stmt = mysqli_prepare($con, $query);
mysqli_stmt_bind_param($stmt, 'si', $newType, $formId);
mysqli_stmt_execute($stmt);

if (mysqli_stmt_affected_rows($stmt) > 0) {
    echo json_encode(['status' => 'success']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Update failed']);
}

mysqli_stmt_close($stmt);
mysqli_close($con);
?>