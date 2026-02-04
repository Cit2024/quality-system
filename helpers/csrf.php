<?php
// helpers/csrf.php

if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/session.php';
}

/**
 * Generate a CSRF token and store it in the session.
 * If a token already exists, return it (to allow multiple tabs/forms).
 * 
 * @return string The CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate a CSRF token.
 * 
 * @param string $token The token to validate
 * @return bool True if valid, false otherwise
 */
function validateCSRFToken($token) {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Verify CSRF token and die if invalid.
 * Helper for quick protection in POST handlers.
 */
function verifyCSRFOrDie() {
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCSRFToken($token)) {
        throw new ValidationException('Invalid CSRF token. Please refresh the page and try again.');
    }
}
?>
