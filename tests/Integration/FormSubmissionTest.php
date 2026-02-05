<?php
namespace QualitySystem\Tests\Integration;

use PHPUnit\Framework\TestCase;

class FormSubmissionTest extends TestCase {
    private $con;
    private $formId;

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

        // create a parent form for submissions
        $this->con->query("INSERT INTO Form (FormType, FormTarget, Semester) VALUES ('survey', 'student', 'Test 2026')");
        $this->formId = $this->con->insert_id;
    }

    protected function tearDown(): void {
        if ($this->con) {
            $this->con->rollback();
            $this->con->close();
        }
        parent::tearDown();
    }

    public function testCanSubmitEvaluation() {
        // Create response
        $stmt = $this->con->prepare("INSERT INTO EvaluationResponses (FormType, FormTarget, Semester, AnsweredAt) VALUES (?, ?, ?, NOW())");
        $type = 'survey';
        $target = 'student';
        $sem = 'Test 2026';
        $stmt->bind_param("sss", $type, $target, $sem);
        $result = $stmt->execute();
        
        $this->assertTrue($result, "Failed to submit evaluation");
        $responseId = $stmt->insert_id;
        $this->assertGreaterThan(0, $responseId);
    }
}
