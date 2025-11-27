<?php
/**
 * Database Helper Functions
 */

function fetchData($conn, $query, $params = []) {
    // Check if connection is valid
    if (!$conn || !$conn instanceof mysqli) {
        error_log("Invalid database connection");
        return false;
    }

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        error_log("Prepare failed: " . $conn->error);
        return false;
    }
    
    if (!empty($params)) {
        $types = str_repeat('s', count($params));
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        error_log("Execute failed: " . $stmt->error);
        return false;
    }
    
    $meta = $stmt->result_metadata();
    if (!$meta) return true; // For non-SELECT queries
    
    $fields = $meta->fetch_fields();
    $results = [];
    
    // Dynamically create variables for binding
    $bindVars = [];
    foreach ($fields as $field) {
        $bindVars[$field->name] = null;
    }
    
    // Create references for bind_result
    $bindParams = [];
    foreach ($bindVars as &$var) {
        $bindParams[] = &$var;
    }
    
    // Bind results
    call_user_func_array([$stmt, 'bind_result'], $bindParams);
    
    // Fetch rows
    while ($stmt->fetch()) {
        $row = [];
        foreach ($bindVars as $key => $value) {
            $row[$key] = $value;
        }
        $results[] = $row;
    }
    
    $stmt->close();
    return $results;
}