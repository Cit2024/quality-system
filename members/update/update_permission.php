<?php
// update_permission.php
include '../../config/DbConnection.php';
require_once '../../helpers/csrf.php';

verifyCSRFOrDie();

$adminId = $_POST['id'];
$permission = $_POST['permission'];
$value = $_POST['value'];

// Update the permission in the database
$query = "UPDATE Admin SET $permission = $value WHERE ID = $adminId";
$result = mysqli_query($con, $query);

if ($result) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false]);
}
?>