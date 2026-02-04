<?php
/**
 * Centralized Error Handler
 * Provides consistent error responses across the application
 */

require_once __DIR__ . '/exceptions.php';

/**
 * Handle exceptions and return appropriate responses
 * 
 * @param Exception $e The exception to handle
 * @param bool $isAjax Whether this is an AJAX request
 * @return void
 */
function handleException($e, $isAjax = false) {
    // Log the error
    error_log(sprintf(
        "[%s] %s in %s:%d\nStack trace:\n%s",
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    ));
    
    // Determine HTTP status code
    $httpCode = 500;
    if ($e instanceof AppException) {
        $httpCode = $e->getHttpCode();
    }
    
    // Set HTTP response code
    http_response_code($httpCode);
    
    // Prepare error message (sanitize for production)
    $message = $e->getMessage();
    $isDevelopment = (getenv('APP_ENV') === 'development');
    
    if ($isAjax) {
        // JSON response for AJAX requests
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => $message,
            'code' => $httpCode,
            'debug' => $isDevelopment ? [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ] : null
        ]);
    } else {
        // HTML response for regular requests
        echo "<!DOCTYPE html>
<html lang='ar' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>خطأ - $httpCode</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }
        .error-container {
            background: white;
            border-radius: 10px;
            padding: 40px;
            max-width: 600px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            text-align: center;
        }
        h1 {
            color: #e74c3c;
            font-size: 72px;
            margin: 0;
        }
        h2 {
            color: #2c3e50;
            margin: 10px 0;
        }
        p {
            color: #7f8c8d;
            line-height: 1.6;
        }
        .back-button {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 30px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .back-button:hover {
            background: #2980b9;
        }
        .debug-info {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
            text-align: left;
            font-family: monospace;
            font-size: 12px;
            color: #495057;
        }
    </style>
</head>
<body>
    <div class='error-container'>
        <h1>$httpCode</h1>
        <h2>" . htmlspecialchars($message) . "</h2>
        <p>عذراً، حدث خطأ أثناء معالجة طلبك.</p>
        <a href='javascript:history.back()' class='back-button'>العودة للخلف</a>
        " . ($isDevelopment ? "
        <div class='debug-info'>
            <strong>File:</strong> {$e->getFile()}<br>
            <strong>Line:</strong> {$e->getLine()}<br>
            <strong>Trace:</strong><br><pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>
        </div>" : "") . "
    </div>
</body>
</html>";
    }
    
    exit();
}

/**
 * Check if the current request is an AJAX request
 * 
 * @return bool
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Wrap code execution with error handling
 * 
 * @param callable $callback The code to execute
 * @return mixed The result of the callback
 */
function withErrorHandling(callable $callback) {
    try {
        return $callback();
    } catch (Exception $e) {
        handleException($e, isAjaxRequest());
    }
}
?>
