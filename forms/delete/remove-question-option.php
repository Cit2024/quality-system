<?php
session_start();
include '../../config/DbConnection.php';

// Use POST data instead of JSON input
$questionId = $_POST['questionId'];
$optionToRemove = $_POST['option'];

// Validate input
if (empty($questionId) || empty($optionToRemove)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
    exit();
}

// Get existing options
$stmt = $con->prepare("SELECT Choices FROM Question WHERE ID = ?");
$stmt->bind_param("i", $questionId);
$stmt->execute();
$result = $stmt->get_result()->fetch_assoc();

// Handle NULL database values
$currentChoices = $result['Choices'] ?? '[]'; // Fallback to empty array
$options = json_decode($currentChoices, true) ?: [];

// Remove option
$filtered = array_filter($options, fn($opt) => $opt !== $optionToRemove);

// Update database
$updateStmt = $con->prepare("UPDATE Question SET Choices = ? WHERE ID = ?");
$jsonOptions = json_encode(array_values($filtered));
$updateStmt->bind_param("si", $jsonOptions, $questionId);

if ($updateStmt->execute()) {
    echo json_encode(['status' => 'success']);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $con->error]);
}