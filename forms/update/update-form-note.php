<?php
require_once __DIR__ . '/../../config/session.php';

// Check if the admin is logged in
if (!isset($_SESSION['admin_id'])) {
    echo json_encode(["status" => "error", "message" => "Unauthorized access"]);
    exit();
}

// Include the database connection
include '../../config/DbConnection.php';
require_once '../../helpers/csrf.php';

verifyCSRFOrDie();

// Get the form ID and new note from the POST request
$formId = $_POST['id'];
$newNote = $_POST['note'];

// Validate the input
if (empty($formId) || empty($newNote)) {
    echo json_encode(["status" => "error", "message" => "Invalid input"]);
    exit();
}

// Update the form note in the database
$query = "UPDATE Form SET note = ? WHERE ID = ?";
$stmt = $con->prepare($query);
$stmt->bind_param("si", $newNote, $formId);

if ($stmt->execute()) {
    echo json_encode(["status" => "success", "message" => "Note updated successfully"]);
} else {
    echo json_encode(["status" => "error", "message" => "Failed to update note"]);
}

$stmt->close();
$con->close();
?>