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
        $pageTitle = "خطأ - $httpCode";
        
        // Determine appropriate icon based on error code
        $iconClass = 'fa-triangle-exclamation'; // default
        if ($httpCode === 404) {
            $iconClass = 'fa-magnifying-glass-chart';
        } elseif ($httpCode === 403) {
            $iconClass = 'fa-user-lock';
        }

        echo "<!DOCTYPE html>
<html lang='ar' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>$pageTitle</title>
    <link rel='icon' href='./assets/icons/college.png'>
    <!-- Use project standard styles -->
    <link rel='stylesheet' href='./styles/evaluation-form.css'>
    <link rel='stylesheet' href='./components/ComponentsStyles.css'>
    <link rel='stylesheet' href='https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'>
    <style>
        body { 
            background-color: #f4f4f4; 
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .error-page-container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        .error-content {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            padding: 50px 30px;
            text-align: center;
            max-width: 600px;
            width: 100%;
            border-top: 5px solid #ff6303;
        }
        .error-code {
            font-family: 'DINBold';
            font-size: 80px;
            line-height: 1;
            color: #ff6303;
            margin-bottom: 20px;
            opacity: 0.9;
        }
        .error-icon {
            font-size: 60px;
            color: #ff6303;
            margin-bottom: 20px;
        }
        .error-message {
            font-family: 'DINBold';
            font-size: 24px;
            color: #333;
            margin-bottom: 15px;
        }
        .error-description {
            font-family: 'DINRegular';
            font-size: 16px;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background-color: #333; 
            color: white;
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-family: 'DINRegular';
            transition: all 0.3s ease;
        }
        .back-btn:hover {
            background-color: #ff6303;
            transform: translateY(-2px);
        }
        .debug-box {
            margin-top: 30px;
            background: #f8f9fa;
            border: 1px solid #eee;
            border-radius: 6px;
            padding: 15px;
            text-align: right;
            direction: ltr;
            font-family: monospace;
            font-size: 13px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <header>
        <div class='logo-contnet'>
            <img src='./assets/icons/Industrial-Technology-College-Logo-Arabic-For-the-big-screen.svg' alt='Industrial Technology College Logo' />
        </div>
        <div class='header-title'>
            <p>وزارة الصناعة و المعادن</p>
            <p>كلية التنقية الصناعية - مصراتة</p>
        </div>
        <div></div>
    </header>

    <div class='separator'></div>

    <div class='error-page-container'>
        <div class='error-content'>
            <div class='error-icon'>
                <i class='fa-solid $iconClass'></i>
            </div>
            <div class='error-message'>" . htmlspecialchars($message) . "</div>
            <div class='error-description'>
                نعتذر، حدث خطأ غير متوقع أثناء معالجة طلبك.<br>
                يرجى المحاولة مرة أخرى أو التواصل مع الدعم الفني.
            </div>
            
            <a href='javascript:history.back()' class='back-btn'>
                <i class='fa-solid fa-arrow-right'></i>
                العودة للصفحة السابقة
            </a>

            " . ($isDevelopment ? "
            <div class='debug-box'>
                <strong>Error:</strong> {$e->getMessage()}<br>
                <strong>File:</strong> {$e->getFile()}:{$e->getLine()}<br>
                <div style='margin-top:10px; border-top:1px dashed #ccc; padding-top:10px;'>
                    <strong>Stack Trace:</strong><br>
                    <pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>
                </div>
            </div>" : "") . "
        </div>
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
