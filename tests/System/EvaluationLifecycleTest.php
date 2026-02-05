<?php
namespace QualitySystem\Tests\System;

use PHPUnit\Framework\TestCase;

class EvaluationLifecycleTest extends TestCase {
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

    /**
     * Test the entire lifecycle of Defining a Model and Recording Data
     * This covers the "Data Science Process" (Model) and "Recording System"
     */
    public function testEvaluationLifecycle() {
        // 1. Define the Model (Form Types & Access Rules)
        // ---------------------------------------------------
        $formTypeSlug = 'research-audit-' . uniqid();
        $this->con->query("INSERT INTO FormTypes (Slug, Name) VALUES ('$formTypeSlug', 'Research Audit')");
        $formTypeId = $this->con->insert_id;

        $evaluatorTypeSlug = 'auditor-' . uniqid();
        $this->con->query("INSERT INTO EvaluatorTypes (Slug, Name) VALUES ('$evaluatorTypeSlug', 'Internal Auditor')");
        $evaluatorTypeId = $this->con->insert_id;

        // Create the Form Configuration
        $this->con->query("INSERT INTO Form (FormTypeID, EvaluatorTypeID, FormTarget) VALUES ($formTypeId, $evaluatorTypeId, 'Confidential Audit')");
        $formId = $this->con->insert_id;

        // Define Access Fields (The "Verification" requirements)
        // We require 'student_id' (mapped to IDStudent internally or via metadata)
        $stmt = $this->con->prepare("INSERT INTO FormAccessFields (FormID, Label, FieldType, IsRequired) VALUES (?, 'Auditor ID', 'text', 1)");
        $stmt->bind_param("i", $formId);
        $stmt->execute();


        // 2. Simulate Recording Data (filling the form)
        // ---------------------------------------------------
        // The core "Measurement" of the system.
        // We submit a response with structured Metadata.
        
        $metadata = json_encode([
            'IDStudent' => 'auditor_001',   // Required for 011 Index
            'student_id' => 'auditor_001',  // Required for 013 Stored Column
            'IDCourse' => 'lab_safety_101', // Required for 011 Index
            'course_id' => 'lab_safety_101',// Required for 013 Stored Column
            'audit_score' => 98.5
        ]);

        $questionId = 101; 
        $semester = 'Spring2026';
        
        $stmt = $this->con->prepare("
            INSERT INTO EvaluationResponses 
            (FormType, FormTarget, Semester, AnsweredAt, QuestionID, Metadata) 
            VALUES (?, 'Lab A', ?, NOW(), ?, ?)
        ");
        $stmt->bind_param("ssis", $formTypeSlug, $semester, $questionId, $metadata);
        $result = $stmt->execute();

        $this->assertTrue($result, "Failed to record evaluation response");
        $responseId = $stmt->insert_id;
        $this->assertGreaterThan(0, $responseId);


        // 3. Verify Data Science / Model Integration
        // ---------------------------------------------------
        // Check if the Database Model correctly extracted the JSON keys into columns (013 & 011 logic)
        
        $query = "SELECT IDStudent, student_id, IDCourse, course_id FROM EvaluationResponses WHERE ID = $responseId";
        $res = $this->con->query($query);
        $row = $res->fetch_assoc();

        // 011 Virtual Column Logic (IDStudent key)
        $this->assertEquals('auditor_001', $row['IDStudent'], "Virtual Column 'IDStudent' failed to extract from Metadata");
        
        // 013 Stored Column Logic (student_id key)
        $this->assertEquals('auditor_001', $row['student_id'], "Stored Column 'student_id' failed to extract from Metadata");
        
        // This proves the "Recording System in the Model" works: JSON -> Structured Data


        // 4. Verify Uniqueness Constraints (Data Integrity)
        // ---------------------------------------------------
        // Attempt duplicate submission (Same Student, Same Course, Same Question, Same Semester)
        
        try {
            $stmt->execute(); // Re-run the same insert
            $this->fail("Model failed to prevent duplicate recording (Data Integrity Breach)");
        } catch (\mysqli_sql_exception $e) {
            $this->assertStringContainsString("Duplicate", $e->getMessage());
        }

        // 5. Verify Scope/Context Difference
        // ---------------------------------------------------
        // Changing the Semester should allow recording (different context)
        $newSemester = 'Fall2026';
        $stmt->bind_param("ssis", $formTypeSlug, $newSemester, $questionId, $metadata);
        $result = $stmt->execute();
        $this->assertTrue($result, "Should allow same data in different semester");
    }
}
