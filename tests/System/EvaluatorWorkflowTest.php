<?php
namespace QualitySystem\Tests\System;

use PHPUnit\Framework\TestCase;

class EvaluatorWorkflowTest extends TestCase {
    private static $baseUrl = 'http://127.0.0.1:8081';
    private $con;
    private $cookieFile;

    protected function setUp(): void {
        parent::setUp();
        
        $this->con = mysqli_connect('127.0.0.1', 'root', 'rootpassword', 'quality_system_test', 3307);
        $this->con->query("DELETE FROM EvaluationResponses");
        
        // 1. Setup Form Types (using existing but ensuring they are there)
        $this->con->query("INSERT IGNORE INTO FormTypes (Slug, Name) VALUES ('course_evaluation', 'Course Evaluation')");
        $this->con->query("INSERT IGNORE INTO EvaluatorTypes (Slug, Name) VALUES ('student', 'Student')");
        
        // 2. Setup a Form
        $this->con->query("DELETE FROM Form WHERE FormType = 'course_evaluation' AND FormTarget = 'student'");
        $this->con->query("INSERT INTO Form (FormType, FormTarget, Title, Semester, FormStatus, IsActive) 
                           VALUES ('course_evaluation', 'student', 'Test Student Form', '1', 'published', 1)");
        $formId = $this->con->insert_id;
        
        // 3. Setup Access Fields (Required Metadata)
        $this->con->query("DELETE FROM FormAccessFields WHERE FormID = $formId");
        $this->con->query("INSERT INTO FormAccessFields (FormID, Label, Slug, IsRequired, FieldType, OrderIndex) VALUES 
            ($formId, 'Student ID', 'IDStudent', 1, 'text', 1),
            ($formId, 'Course ID', 'IDCourse', 1, 'text', 2),
            ($formId, 'Group ID', 'IDGroup', 1, 'text', 3),
            ($formId, 'Semester', 'Semester', 1, 'text', 4)");

        // 4. Setup Section and Question
        $this->con->query("DELETE FROM Section WHERE IDForm = $formId");
        $this->con->query("INSERT INTO Section (IDForm, Title, OrderIndex) VALUES ($formId, 'General Questions', 1)");
        $sectionId = $this->con->insert_id;
        
        $this->con->query("DELETE FROM Question WHERE IDSection = $sectionId");
        $this->con->query("INSERT INTO Question (IDSection, TitleQuestion, TypeQuestion, OrderIndex) VALUES 
            ($sectionId, 'How was the course?', 'evaluation', 1)");
        $this->questionId = $this->con->insert_id;

        // 5. Setup CIT Lookup Data (for teachers_evaluation/coursesgroups lookup if enriched)
        // Note: ResponseHandler calls enrichStudentMetadata for student/course_evaluation
        $this->con->query("INSERT IGNORE INTO zaman (ZamanNo, ZamanName) VALUES (1, 'Spring 2026')");
        $this->con->query("INSERT IGNORE INTO coursesgroups (ZamanNo, MadaNo, GNo, TNo) VALUES (1, 'CSE101', 1, 99)");

        $this->cookieFile = tempnam(sys_get_temp_dir(), 'ev_cookie_');
    }

    protected function tearDown(): void {
        if ($this->con) {
            $this->con->close();
        }
        if (file_exists($this->cookieFile)) {
            unlink($this->cookieFile);
        }
        parent::tearDown();
    }

    private function get($url) {
        $ch = curl_init(self::$baseUrl . $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        return ['body' => $response, 'info' => $info];
    }

    private function post($url, $data) {
        $ch = curl_init(self::$baseUrl . $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        return ['body' => $response, 'info' => $info];
    }

    public function testEvaluationLinkAccess() {
        // Valid link: evaluation=course_evaluation&Evaluator=student&IDStudent=123&IDCourse=CSE101&IDGroup=1&Semester=1
        $url = "/evaluation-form.php?evaluation=course_evaluation&Evaluator=student&IDStudent=123&IDCourse=CSE101&IDGroup=1&Semester=1";
        $res = $this->get($url);
        
        $this->assertEquals(200, $res['info']['http_code']);
        $this->assertStringContainsString('How was the course?', $res['body']);
        // Check if CSRF token is present
        $this->assertStringContainsString('csrf_token', $res['body']);
    }

    public function testSubmitEvaluation() {
        // 1. Get the form to extract CSRF token (real flow)
        $url = "/evaluation-form.php?evaluation=course_evaluation&Evaluator=student&IDStudent=123&IDCourse=CSE101&IDGroup=1&Semester=1";
        $formPage = $this->get($url);
        
        preg_match('/name="csrf_token" value="([^"]+)"/', $formPage['body'], $matches);
        $csrfToken = $matches[1] ?? '';
        $this->assertNotEmpty($csrfToken, "Could not find CSRF token in form");

        preg_match('/name="form_id" value="([^"]+)"/', $formPage['body'], $matches);
        $formId = $matches[1] ?? '';

        // 2. Submit form
        $res = $this->post('/evaluation/submit.php', [
            'csrf_token' => $csrfToken,
            'form_id' => $formId,
            'evaluation_type' => 'course_evaluation',
            'IDStudent' => '123',
            'IDCourse' => 'CSE101',
            'IDGroup' => '1',
            'Semester' => '1',
            'question' => [
                $this->questionId => [
                    'rating' => 5
                ]
            ]
        ]);

        // Should redirect to thank you page
        $this->assertEquals(302, $res['info']['http_code']);
        $this->assertStringContainsString('evaluation-thankyou.php?success=1', $res['info']['redirect_url']);

        // 3. Verify in DB
        $dbRes = $this->con->query("SELECT * FROM EvaluationResponses WHERE FormType = 'course_evaluation' AND QuestionID = {$this->questionId}");
        $this->assertEquals(1, $dbRes->num_rows);
        $row = $dbRes->fetch_assoc();
        
        // Verify metadata enrichment (teacher_id should be added by handler)
        $metadata = json_decode($row['Metadata'], true);
        $this->assertEquals(99, $metadata['teacher_id'], "Teacher enrichment failed");
    }

    public function testPreventDuplicateSubmission() {
        // 1. First submission
        $this->testSubmitEvaluation();

        // 2. Try again with same credentials
        $url = "/evaluation-form.php?evaluation=course_evaluation&Evaluator=student&IDStudent=123&IDCourse=CSE101&IDGroup=1&Semester=1";
        $formPage = $this->get($url);
        preg_match('/name="csrf_token" value="([^"]+)"/', $formPage['body'], $matches);
        $csrfToken = $matches[1];
        preg_match('/name="form_id" value="([^"]+)"/', $formPage['body'], $matches);
        $formId = $matches[1];

        $res = $this->post('/evaluation/submit.php', [
            'csrf_token' => $csrfToken,
            'form_id' => $formId,
            'evaluation_type' => 'course_evaluation',
            'IDStudent' => '123',
            'IDCourse' => 'CSE101',
            'IDGroup' => '1',
            'Semester' => '1',
            'question' => [
                $this->questionId => [
                    'rating' => 4
                ]
            ]
        ]);

        // Should redirect but with success=0 and error message
        $this->assertEquals(302, $res['info']['http_code']);
        $this->assertStringContainsString('success=0', $res['info']['redirect_url']);
        $this->assertStringContainsString('لقد قمت بإرسال هذا التقييم مسبقاً', urldecode($res['info']['redirect_url']));
    }
}
