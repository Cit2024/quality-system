<?php
namespace QualitySystem\Tests\Integration;

use PHPUnit\Framework\TestCase;

class AccessControlTest extends TestCase {
    private $con;

    protected function setUp(): void {
        parent::setUp();
        
        $this->con = mysqli_connect(
            getenv('DB_HOST') ?: '127.0.0.1', 
            getenv('DB_USER') ?: 'root', 
            getenv('DB_PASS') ?: 'rootpassword', 
            getenv('DB_NAME') ?: 'quality_system_test',
            getenv('DB_PORT') ?: 3307
        );
        $this->con->begin_transaction();
    }

    protected function tearDown(): void {
        if ($this->con) {
            $this->con->rollback();
            $this->con->close();
        }
        parent::tearDown();
    }

    public function testCanCreateAdminUser() {
        $username = 'testadmin_' . uniqid();
        $password = 'secret123';
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        
        // Prepare insert (assuming Access migration added permission columns)
        // Migration 002 adds IsCanCreate, IsCanDelete etc.
        $stmt = $this->con->prepare("
            INSERT INTO Admin (username, password, IsCanCreate, IsCanDelete) 
            VALUES (?, ?, 1, 0)
        ");
        $stmt->bind_param("ss", $username, $hashed);
        $result = $stmt->execute();
        
        $this->assertTrue($result, "Failed to create admin");
        $id = $stmt->insert_id;
        
        // Retrieve and verify
        $stmt = $this->con->prepare("SELECT * FROM Admin WHERE ID = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        $admin = $res->fetch_assoc();
        
        $this->assertEquals($username, $admin['username']);
        $this->assertTrue(password_verify($password, $admin['password']));
        $this->assertEquals(1, $admin['isCanCreate']);
        $this->assertEquals(0, $admin['isCanDelete']);
    }

    public function testDuplicateUsernameFails() {
        $username = 'dup_user';
        $this->con->query("INSERT INTO Admin (username, password) VALUES ('$username', 'pass')");
        
        try {
            $this->con->query("INSERT INTO Admin (username, password) VALUES ('$username', 'pass2')");
            $this->fail("Should fail on duplicate username");
        } catch (\mysqli_sql_exception $e) {
            $this->assertStringContainsString("Duplicate", $e->getMessage());
        }
    }
}
