<?php
/**
 * Error Handler Tests
 * Tests exception handling and error responses
 */

namespace QualitySystem\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/error_handler.php';
require_once __DIR__ . '/../../helpers/exceptions.php';

class ErrorHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Suppress actual output during tests
        ob_start();
    }

    protected function tearDown(): void
    {
        ob_end_clean();
        parent::tearDown();
    }

    public function testHandleValidationException()
    {
        $exception = new \ValidationException("Test validation error");
        
        $this->assertEquals(400, $exception->getHttpCode());
        $this->assertEquals("Test validation error", $exception->getMessage());
    }

    public function testHandleAuthException()
    {
        $exception = new \AuthException("Authentication failed");
        
        $this->assertEquals(401, $exception->getHttpCode());
    }

    public function testHandlePermissionException()
    {
        $exception = new \PermissionException("Access denied");
        
        $this->assertEquals(403, $exception->getHttpCode());
    }

    public function testHandleNotFoundException()
    {
        $exception = new \NotFoundException("Resource not found");
        
        $this->assertEquals(404, $exception->getHttpCode());
    }

    public function testHandleDatabaseException()
    {
        $exception = new \DatabaseException("Database error");
        
        $this->assertEquals(500, $exception->getHttpCode());
    }

    public function testHandleDuplicateException()
    {
        $exception = new \DuplicateException("Duplicate entry");
        
        $this->assertEquals(409, $exception->getHttpCode());
    }

    public function testExceptionInheritance()
    {
        $exception = new \ValidationException("Test");
        
        $this->assertInstanceOf(\AppException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testIsAjaxRequest()
    {
        // Test without header
        $this->assertFalse(isAjaxRequest());
        
        // Test with header
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'XMLHttpRequest';
        $this->assertTrue(isAjaxRequest());
        
        // Test case insensitivity
        $_SERVER['HTTP_X_REQUESTED_WITH'] = 'xmlhttprequest';
        $this->assertTrue(isAjaxRequest());
        
        // Cleanup
        unset($_SERVER['HTTP_X_REQUESTED_WITH']);
    }

    public function testWithErrorHandlingSuccess()
    {
        $result = withErrorHandling(function() {
            return "success";
        });
        
        $this->assertEquals("success", $result);
    }

    public function testExceptionMessageNotEmpty()
    {
        $exception = new \ValidationException("Test message");
        
        $this->assertNotEmpty($exception->getMessage());
        $this->assertIsString($exception->getMessage());
    }

    public function testAllExceptionTypes()
    {
        $exceptions = [
            new \ValidationException("test"),
            new \AuthException("test"),
            new \PermissionException("test"),
            new \NotFoundException("test"),
            new \DuplicateException("test"),
            new \DatabaseException("test"),
        ];
        
        foreach ($exceptions as $exception) {
            $this->assertInstanceOf(\AppException::class, $exception);
            $this->assertIsInt($exception->getHttpCode());
            $this->assertGreaterThanOrEqual(400, $exception->getHttpCode());
            $this->assertLessThanOrEqual(500, $exception->getHttpCode());
        }
    }
}
?>
