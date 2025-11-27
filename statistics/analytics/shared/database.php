<?php
// statistics/analytics/shared/database.php
// Database connections and common queries
require_once __DIR__.'/../../../config/dbConnectionCit.php';
require_once __DIR__.'/../../../config/DbConnection.php';

function getCITConnection() {
    global $conn_cit;
    return $conn_cit;
}

function getQualityConnection() {
    global $con;
    return $con;
}

function safeFetch($conn, $query, $params = [], $types = '') {
    $stmt = $conn->prepare($query);
    
    if (!empty($params)) {
        // Auto-detect types if not provided
        if (empty($types)) {
            $types = str_repeat('s', count($params));
        }
        
        if (strlen($types) !== count($params)) {
            throw new InvalidArgumentException(
                "Type/parameter mismatch. Received {$types} for " . count($params) . " parameters"
            );
        }
        
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    
    return $data;
}