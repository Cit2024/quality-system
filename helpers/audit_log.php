<?php
/**
 * Audit Logging Helper
 * Tracks all critical operations for compliance and security monitoring
 */

require_once __DIR__ . '/../config/DbConnection.php';
require_once __DIR__ . '/../config/session.php';

/**
 * Log an audit entry
 * 
 * @param string $action Action performed (e.g., 'create_form', 'delete_question')
 * @param string $entityType Type of entity (e.g., 'Form', 'Question', 'Section')
 * @param int|null $entityID ID of the affected entity
 * @param mixed $oldValue Previous value (will be JSON encoded if array/object)
 * @param mixed $newValue New value (will be JSON encoded if array/object)
 * @param string $status Status: 'success', 'failed', or 'partial'
 * @param string|null $errorMessage Error details if status is 'failed'
 * @return bool Success status
 */
function logAudit($action, $entityType, $entityID = null, $oldValue = null, $newValue = null, $status = 'success', $errorMessage = null) {
    global $con;
    
    try {
        // Get current user info
        $userID = $_SESSION['user_id'] ?? null;
        $userType = $_SESSION['user_type'] ?? 'system';
        
        // Get IP address
        $ipAddress = getClientIP();
        
        // Get user agent
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        if ($userAgent && strlen($userAgent) > 255) {
            $userAgent = substr($userAgent, 0, 255);
        }
        
        // Get session ID
        $sessionID = session_id() ?: null;
        
        // Encode complex values as JSON
        $oldValueStr = is_array($oldValue) || is_object($oldValue) ? json_encode($oldValue, JSON_UNESCAPED_UNICODE) : $oldValue;
        $newValueStr = is_array($newValue) || is_object($newValue) ? json_encode($newValue, JSON_UNESCAPED_UNICODE) : $newValue;
        
        // Prepare statement
        $stmt = $con->prepare("
            INSERT INTO AuditLog (
                UserID, UserType, Action, EntityType, EntityID,
                OldValue, NewValue, IPAddress, UserAgent, SessionID,
                Status, ErrorMessage
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        if (!$stmt) {
            error_log("Audit log prepare failed: " . $con->error);
            return false;
        }
        
        $stmt->bind_param(
            "isssississss",
            $userID,
            $userType,
            $action,
            $entityType,
            $entityID,
            $oldValueStr,
            $newValueStr,
            $ipAddress,
            $userAgent,
            $sessionID,
            $status,
            $errorMessage
        );
        
        $result = $stmt->execute();
        
        if (!$result) {
            error_log("Audit log insert failed: " . $stmt->error);
        }
        
        $stmt->close();
        return $result;
        
    } catch (Exception $e) {
        error_log("Audit logging exception: " . $e->getMessage());
        return false;
    }
}

/**
 * Log form-related operations
 */
function logFormAudit($action, $formID, $oldData = null, $newData = null, $status = 'success', $error = null) {
    return logAudit($action, 'Form', $formID, $oldData, $newData, $status, $error);
}

/**
 * Log section-related operations
 */
function logSectionAudit($action, $sectionID, $oldData = null, $newData = null, $status = 'success', $error = null) {
    return logAudit($action, 'Section', $sectionID, $oldData, $newData, $status, $error);
}

/**
 * Log question-related operations
 */
function logQuestionAudit($action, $questionID, $oldData = null, $newData = null, $status = 'success', $error = null) {
    return logAudit($action, 'Question', $questionID, $oldData, $newData, $status, $error);
}

/**
 * Log type management operations
 */
function logTypeAudit($action, $typeID, $category, $oldData = null, $newData = null, $status = 'success', $error = null) {
    $entityType = $category === 'type' ? 'FormType' : 'EvaluatorType';
    return logAudit($action, $entityType, $typeID, $oldData, $newData, $status, $error);
}

/**
 * Log access settings operations
 */
function logAccessAudit($action, $formID, $oldData = null, $newData = null, $status = 'success', $error = null) {
    return logAudit($action, 'FormAccess', $formID, $oldData, $newData, $status, $error);
}

/**
 * Log response submission
 */
function logResponseAudit($action, $responseID, $metadata = null, $status = 'success', $error = null) {
    return logAudit($action, 'Response', $responseID, null, $metadata, $status, $error);
}

/**
 * Log authentication events
 */
function logAuthAudit($action, $userID = null, $userType = null, $status = 'success', $error = null) {
    $oldUserType = $_SESSION['user_type'] ?? 'system';
    $_SESSION['user_type'] = $userType ?? $oldUserType;
    
    $result = logAudit($action, 'Authentication', $userID, null, ['user_type' => $userType], $status, $error);
    
    $_SESSION['user_type'] = $oldUserType;
    return $result;
}

/**
 * Log permission changes
 */
function logPermissionAudit($action, $adminID, $oldPermissions, $newPermissions, $status = 'success', $error = null) {
    return logAudit($action, 'Permission', $adminID, $oldPermissions, $newPermissions, $status, $error);
}

/**
 * Get client IP address (handles proxies)
 */
function getClientIP() {
    $ipKeys = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];
    
    foreach ($ipKeys as $key) {
        if (isset($_SERVER[$key])) {
            $ips = explode(',', $_SERVER[$key]);
            $ip = trim($ips[0]);
            
            // Validate IP
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return $ip;
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? null;
}

/**
 * Get recent audit entries for a specific entity
 * 
 * @param string $entityType Type of entity
 * @param int $entityID ID of entity
 * @param int $limit Number of entries to return
 * @return array Audit entries
 */
function getAuditHistory($entityType, $entityID, $limit = 50) {
    global $con;
    
    $stmt = $con->prepare("
        SELECT 
            ID, UserID, UserType, Action, OldValue, NewValue,
            IPAddress, Status, ErrorMessage, CreatedAt
        FROM AuditLog
        WHERE EntityType = ? AND EntityID = ?
        ORDER BY CreatedAt DESC
        LIMIT ?
    ");
    
    $stmt->bind_param("sii", $entityType, $entityID, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $entries = [];
    while ($row = $result->fetch_assoc()) {
        $entries[] = $row;
    }
    
    $stmt->close();
    return $entries;
}

/**
 * Get audit statistics
 * 
 * @param int $days Number of days to analyze
 * @return array Statistics
 */
function getAuditStatistics($days = 30) {
    global $con;
    
    $stmt = $con->prepare("
        SELECT 
            Action,
            EntityType,
            Status,
            COUNT(*) as count
        FROM AuditLog
        WHERE CreatedAt >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY Action, EntityType, Status
        ORDER BY count DESC
    ");
    
    $stmt->bind_param("i", $days);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $stats = [];
    while ($row = $result->fetch_assoc()) {
        $stats[] = $row;
    }
    
    $stmt->close();
    return $stats;
}
?>
