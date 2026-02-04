<?php
/**
 * Audit Log Tests
 * Tests audit logging functionality
 */

namespace QualitySystem\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/audit_log.php';

class AuditLogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        resetTestDatabase();
        createTestSession('admin', 1);
    }

    protected function tearDown(): void
    {
        clearTestSession();
        parent::tearDown();
    }

    public function testBasicAuditLogging()
    {
        $result = logAudit('test_action', 'TestEntity', 123, null, ['test' => 'data']);
        
        $this->assertTrue($result);
    }

    public function testFormAuditLogging()
    {
        $result = logFormAudit('create_form', 1, null, ['title' => 'Test Form']);
        
        $this->assertTrue($result);
    }

    public function testSectionAuditLogging()
    {
        $result = logSectionAudit('update_section', 5, ['title' => 'Old'], ['title' => 'New']);
        
        $this->assertTrue($result);
    }

    public function testQuestionAuditLogging()
    {
        $result = logQuestionAudit('delete_question', 10);
        
        $this->assertTrue($result);
    }

    public function testAuthAuditLogging()
    {
        $result = logAuthAudit('login', 1, 'admin', 'success');
        
        $this->assertTrue($result);
    }

    public function testFailedOperationLogging()
    {
        $result = logAudit('failed_action', 'TestEntity', 1, null, null, 'failed', 'Test error message');
        
        $this->assertTrue($result);
    }

    public function testGetClientIP()
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        
        $ip = getClientIP();
        
        $this->assertEquals('192.168.1.1', $ip);
    }

    public function testGetClientIPWithProxy()
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.1, 192.168.1.1';
        $_SERVER['REMOTE_ADDR'] = '192.168.1.1';
        
        $ip = getClientIP();
        
        $this->assertEquals('203.0.113.1', $ip);
    }

    public function testJSONEncodingOfComplexValues()
    {
        $complexData = [
            'nested' => ['array' => 'value'],
            'number' => 123,
            'boolean' => true
        ];
        
        $result = logAudit('test', 'Test', 1, null, $complexData);
        
        $this->assertTrue($result);
    }
}
?>
