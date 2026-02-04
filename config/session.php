<?php
require_once __DIR__ . '/constants.php';
/**
 * Secure Session Configuration
 * 
 * This file configures PHP sessions with security best practices:
 * - HttpOnly cookies (prevents XSS access to session cookies)
 * - Secure cookies (HTTPS only - disable for local development)
 * - SameSite=Strict (prevents CSRF via cookie)
 * - Session timeout (30 minutes of inactivity)
 * - Session regeneration on privilege changes
 */

// Prevent multiple session starts
if (session_status() === PHP_SESSION_NONE) {
    
    // Configure session cookie parameters
    session_set_cookie_params([
        'lifetime' => 0,              // Session cookie (expires when browser closes)
        'path' => '/',
        'domain' => '',               // Current domain
        'secure' => false,            // Set to true in production with HTTPS
        'httponly' => true,           // Prevents JavaScript access to session cookie
        'samesite' => 'Strict'        // Strict CSRF protection
    ]);
    
    // Additional security settings
    ini_set('session.use_strict_mode', '1');        // Reject uninitialized session IDs
    ini_set('session.use_only_cookies', '1');       // Don't accept session IDs via URL
    ini_set('session.cookie_httponly', '1');        // Redundant but explicit
    ini_set('session.use_trans_sid', '0');          // Don't pass session ID in URLs
    ini_set('session.cookie_samesite', 'Strict');   // CSRF protection
    
    // Start the session
    session_start();
    
    // Session timeout: 30 minutes of inactivity
    $timeout_duration = SESSION_LIFETIME; // 30 minutes in seconds
    
    if (isset($_SESSION['LAST_ACTIVITY'])) {
        $elapsed_time = time() - $_SESSION['LAST_ACTIVITY'];
        
        if ($elapsed_time > $timeout_duration) {
            // Session expired
            session_unset();
            session_destroy();
            session_start(); // Start a fresh session
            
            // Redirect to login if this is an authenticated page
            if (isset($_SESSION['admin_id'])) {
                header('Location: /login.php?timeout=1');
                exit();
            }
        }
    }
    
    // Update last activity timestamp
    $_SESSION['LAST_ACTIVITY'] = time();
    
    // Session regeneration on first access (prevents session fixation)
    if (!isset($_SESSION['CREATED'])) {
        session_regenerate_id(true);
        $_SESSION['CREATED'] = time();
    }
    
    // Regenerate session ID every 30 minutes (additional security)
    if (isset($_SESSION['CREATED']) && (time() - $_SESSION['CREATED'] > SESSION_LIFETIME)) {
        session_regenerate_id(true);
        $_SESSION['CREATED'] = time();
    }
}

/**
 * Regenerate session ID on privilege escalation
 * Call this function after login or permission changes
 */
function regenerateSessionOnPrivilegeChange() {
    session_regenerate_id(true);
    $_SESSION['CREATED'] = time();
    $_SESSION['LAST_ACTIVITY'] = time();
}

/**
 * Destroy session completely (for logout)
 */
function destroySession() {
    $_SESSION = [];
    
    // Delete the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    session_destroy();
}
?>
