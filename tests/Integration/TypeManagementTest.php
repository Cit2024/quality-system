<?php
namespace QualitySystem\Tests\Integration;

use PHPUnit\Framework\TestCase;

class TypeManagementTest extends TestCase {
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

    public function testCanCreateFormType() {
        $slug = 'test_type_' . uniqid();
        $name = 'Test Type';
        
        $stmt = $this->con->prepare("INSERT INTO FormTypes (Slug, Name, Icon) VALUES (?, ?, 'star')");
        $stmt->bind_param("ss", $slug, $name);
        $result = $stmt->execute();
        
        $this->assertTrue($result);
        $id = $stmt->insert_id;
        $this->assertGreaterThan(0, $id);
        
        // Verify uniqueness
        try {
            $stmt->execute();
            $this->fail("Should fail on duplicate slug");
        } catch (\mysqli_sql_exception $e) {
            $this->assertStringContainsString("Duplicate", $e->getMessage());
        }
    }

    public function testCanLinkTypeToEvaluator() {
        // 1. Create FormType
        $this->con->query("INSERT INTO FormTypes (Slug, Name) VALUES ('ft_test', 'Form Test')");
        $ftId = $this->con->insert_id;

        // 2. Create EvaluatorType
        $this->con->query("INSERT INTO EvaluatorTypes (Slug, Name) VALUES ('et_test', 'Eval Test')");
        $etId = $this->con->insert_id;

        // 3. Link them
        $stmt = $this->con->prepare("INSERT INTO FormType_EvaluatorType (FormTypeID, EvaluatorTypeID) VALUES (?, ?)");
        $stmt->bind_param("ii", $ftId, $etId);
        $result = $stmt->execute();
        
        $this->assertTrue($result, "Failed to link types");

        // 4. Verify link
        $query = "SELECT * FROM FormType_EvaluatorType WHERE FormTypeID = $ftId AND EvaluatorTypeID = $etId";
        $res = $this->con->query($query);
        $this->assertEquals(1, $res->num_rows);
    }
}
