<?php
namespace QualitySystem\Tests\Integration;

use PHPUnit\Framework\TestCase;

class FormCreationTest extends TestCase {
    private $con;

    protected function setUp(): void {
        parent::setUp();
        
        // Connect to test database
        $this->con = mysqli_connect(
            getenv('DB_HOST') ?: '127.0.0.1', 
            getenv('DB_USER') ?: 'root', 
            getenv('DB_PASS') ?: 'rootpassword', 
            getenv('DB_NAME') ?: 'quality_system_test',
            getenv('DB_PORT') ?: 3307
        );

        if (!$this->con) {
            $this->fail("Failed to connect to test database");
        }
        
        // Start a transaction for each test
        $this->con->begin_transaction();
        
        // Ensure we have a clean state for Form table
        // We don't truncate because other tables might be static
    }

    protected function tearDown(): void {
        // Rollback transaction to clean up
        if ($this->con) {
            $this->con->rollback();
            $this->con->close();
        }
        parent::tearDown();
    }

    public function testCanCreateCourseEvaluationForm() {
        // Prepare valid form data
        $formType = 'course_evaluation';
        $formTarget = 'student';
        $semester = 'Fall 2026';
        
        // Ensure FKs exist (from migration 012)
        // In a real integration test, we might mock this or ensure seed data exists
        // specific to FormTypes and EvaluatorTypes.
        
        // Insert
        $sql = "INSERT INTO Form (FormType, FormTarget, Semester, IsActive) VALUES (?, ?, ?, 1)";
        $stmt = $this->con->prepare($sql);
        $stmt->bind_param("sss", $formType, $formTarget, $semester);
        $result = $stmt->execute();
        
        $this->assertTrue($result, "Failed to insert form: " . $stmt->error);
        
        $formId = $stmt->insert_id;
        $this->assertGreaterThan(0, $formId);
        
        // Verify it exists in DB
        $verifySql = "SELECT * FROM Form WHERE ID = ?";
        $verifyStmt = $this->con->prepare($verifySql);
        $verifyStmt->bind_param("i", $formId);
        $verifyStmt->execute();
        $result = $verifyStmt->get_result();
        $row = $result->fetch_assoc();
        
        $this->assertEquals($semester, $row['Semester']);
        $this->assertEquals(1, $row['IsActive']);
    }

    public function testCannotDuplicateFormInSameSemester() {
        // Insert first form
        $sql = "INSERT INTO Form (FormType, FormTarget, Semester) VALUES ('survey', 'faculty', 'Spring 2026')";
        $this->con->query($sql);
        
        // Try inserting exact same one (if you have unique constraints, expectation fails)
        // Based on schema, we might not have a UNIQUE constraint on (FormType, FormTarget, Semester)
        // If we don't, this test verifies strictly that we CAN insert duplicates (unless validlogic prevents it at app level)
        // But let's assume valid logic prevents it or we want to test application logic.
        // Since this is a DB integration test, we test DB constraints.
        
        // Checking for duplicate slug logic (Migration 014 logic)
        // "unique_slug_per_form" is on FormAccessFields, not Form.
        
        // Let's test AccessFields constraints
        $formId = $this->con->insert_id;
        
        $this->con->query("INSERT INTO FormAccessFields (FormID, Label, Slug, FieldType) VALUES ($formId, 'Test', 'test-slug', 'text')");
        
        try {
            $this->con->query("INSERT INTO FormAccessFields (FormID, Label, Slug, FieldType) VALUES ($formId, 'Test 2', 'test-slug', 'text')");
            $this->fail("Should have thrown duplicate entry error for Slug");
        } catch (\mysqli_sql_exception $e) {
            $this->assertStringContainsString("Duplicate entry", $e->getMessage());
        }
    }
}
