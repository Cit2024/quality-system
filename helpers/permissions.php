<?php
/**
 * Permission Validation Helper
 * 
 * This helper ensures that admin permissions are always up-to-date by
 * re-fetching them from the database on each request. This prevents
 * scenarios where an admin's permissions are revoked but they can still
 * perform actions until their session expires.
 */

/**
 * Refresh admin permissions from the database
 * Should be called on every authenticated request
 * 
 * @param mysqli $con Database connection
 * @return bool True if permissions were refreshed successfully, false if admin not found or deleted
 */
function refreshAdminPermissions($con) {
    // Only refresh for admin users (not teachers)
    if (!isset($_SESSION['admin_id']) || !isset($_SESSION['user_type']) || $_SESSION['user_type'] !== 'admin') {
        return true; // Not an admin, nothing to refresh
    }
    
    $adminId = (int)$_SESSION['admin_id'];
    
    // Fetch current permissions from database
    $stmt = $con->prepare("SELECT isCanCreate, isCanDelete, isCanUpdate, isCanRead, isCanGetAnalysis, is_deleted 
                           FROM Admin 
                           WHERE ID = ?");
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Admin account no longer exists
        $stmt->close();
        return false;
    }
    
    $admin = $result->fetch_assoc();
    $stmt->close();
    
    // Check if admin has been deleted
    if ($admin['is_deleted']) {
        return false;
    }
    
    // Update session with fresh permissions
    $_SESSION['permissions'] = [
        'isCanCreate' => (bool)$admin['isCanCreate'],
        'isCanDelete' => (bool)$admin['isCanDelete'],
        'isCanUpdate' => (bool)$admin['isCanUpdate'],
        'isCanRead' => (bool)$admin['isCanRead'],
        'isCanGetAnalysis' => (bool)$admin['isCanGetAnalysis']
    ];
    
    return true;
}

/**
 * Verify admin has a specific permission
 * Automatically refreshes permissions before checking
 * 
 * @param mysqli $con Database connection
 * @param string $permission Permission key (e.g., 'isCanCreate')
 * @return bool True if admin has the permission, false otherwise
 */
function hasPermission($con, $permission) {
    // Refresh permissions first
    if (!refreshAdminPermissions($con)) {
        return false; // Admin deleted or not found
    }
    
    // Check permission
    return isset($_SESSION['permissions'][$permission]) && $_SESSION['permissions'][$permission];
}

/**
 * Require a specific permission or redirect to dashboard
 * Use this at the top of permission-gated pages
 * 
 * @param mysqli $con Database connection
 * @param string $permission Permission key (e.g., 'isCanCreate')
 * @param string $redirectUrl Optional redirect URL (default: dashboard.php)
 */
function requirePermission($con, $permission, $redirectUrl = '../dashboard.php') {
    if (!hasPermission($con, $permission)) {
        header("Location: $redirectUrl");
        exit();
    }
}

/**
 * Verify admin is still valid and not deleted
 * Use this on all authenticated admin pages
 * 
 * @param mysqli $con Database connection
 * @return bool True if admin is valid, false if deleted/not found
 */
function verifyAdminStatus($con) {
    return refreshAdminPermissions($con);
}
?>
