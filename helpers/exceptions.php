<?php
/**
 * Custom Exception Classes
 * Provides specific exception types for better error handling and debugging
 */

/**
 * Base exception for all application-specific exceptions
 */
class AppException extends Exception {
    protected $httpCode = 500;
    
    public function getHttpCode() {
        return $this->httpCode;
    }
}

/**
 * Database-related exceptions
 */
class DatabaseException extends AppException {
    protected $httpCode = 500;
}

/**
 * Validation exceptions (user input errors)
 */
class ValidationException extends AppException {
    protected $httpCode = 400;
}

/**
 * Authentication/Authorization exceptions
 */
class AuthException extends AppException {
    protected $httpCode = 401;
}

/**
 * Permission denied exceptions
 */
class PermissionException extends AppException {
    protected $httpCode = 403;
}

/**
 * Resource not found exceptions
 */
class NotFoundException extends AppException {
    protected $httpCode = 404;
}

/**
 * Duplicate resource exceptions
 */
class DuplicateException extends AppException {
    protected $httpCode = 409;
}
?>
