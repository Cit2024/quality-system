<?php
// update_permission.php
include '../../config/DbConnection.php';

$adminId = $_GET['id'];
$permission = $_GET['permission'];
$value = $_GET['value'];

// Update the permission in the database
$query = "UPDATE Admin SET $permission = $value WHERE ID = $adminId";
$result = mysqli_query($con, $query);

if ($result) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false]);
}
?>