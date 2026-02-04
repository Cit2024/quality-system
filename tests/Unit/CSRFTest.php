<?php
/**
 * CSRF Token Tests
 * Tests CSRF token generation and validation
 */

namespace QualitySystem\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/csrf.php';

class CSRFTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        clearTestSession();
        createTestSession();
    }

    protected function tearDown(): void
    {
        clearTestSession();
        parent::tearDown();
    }

    public function testTokenGeneration()
    {
        $token = generateCSRFToken();
        
        $this->assertNotEmpty($token);
        $this->assertEquals(CSRF_TOKEN_LENGTH * 2, strlen($token)); // hex encoding doubles length
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $token);
    }

    public function testTokenStoredInSession()
    {
        $token = generateCSRFToken();
        
        $this->assertArrayHasKey('csrf_token', $_SESSION);
        $this->assertEquals($token, $_SESSION['csrf_token']);
    }

    public function testValidTokenValidation()
    {
        $token = generateCSRFToken();
        
        $this->assertTrue(validateCSRFToken($token));
    }

    public function testInvalidTokenValidation()
    {
        generateCSRFToken();
        
        $this->assertFalse(validateCSRFToken('invalid_token'));
    }

    public function testEmptyTokenValidation()
    {
        generateCSRFToken();
        
        $this->assertFalse(validateCSRFToken(''));
        $this->assertFalse(validateCSRFToken(null));
    }

    public function testTokenRegenerationChangesValue()
    {
        $token1 = generateCSRFToken();
        $token2 = generateCSRFToken();
        
        $this->assertNotEquals($token1, $token2);
    }

    public function testOldTokenInvalidAfterRegeneration()
    {
        $oldToken = generateCSRFToken();
        $newToken = generateCSRFToken();
        
        $this->assertFalse(validateCSRFToken($oldToken));
        $this->assertTrue(validateCSRFToken($newToken));
    }

    public function testTokenValidationWithoutSession()
    {
        clearTestSession();
        
        $this->assertFalse(validateCSRFToken('any_token'));
    }
}
?>
