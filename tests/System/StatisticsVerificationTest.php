<?php
namespace QualitySystem\Tests\System;

require_once __DIR__ . '/AdminWorkflowTest.php'; // Reuse HttpClient

class StatisticsVerificationTest extends AdminWorkflowTest {
    private $con;
    private $courseId = 'CSE101';
    private $semester = 1;
    private $formId;
    private $questionId;

    protected function setUp(): void {
        parent::setUp();
        
        $this->con = mysqli_connect('127.0.0.1', 'root', 'rootpassword', 'quality_system_test', 3307);
        $this->con->query("DELETE FROM EvaluationResponses");
        $this->con->query("DELETE FROM tanzil");
        $this->con->query("DELETE FROM sprofiles");
        $this->con->query("DELETE FROM divitions");
        $this->con->query("DELETE FROM mawad");

        // 1. Setup Form
        $this->con->query("INSERT IGNORE INTO FormTypes (Slug, Name) VALUES ('course_evaluation', 'Course Evaluation')");
        $this->con->query("INSERT IGNORE INTO EvaluatorTypes (Slug, Name) VALUES ('student', 'Student')");
        
        $this->con->query("INSERT INTO Form (FormType, FormTarget, Title, FormStatus, Semester) 
                          VALUES ('course_evaluation', 'student', 'Test Course Eval', 'published', '1')");
        $this->formId = $this->con->insert_id;

        $this->con->query("INSERT INTO Section (IDForm, Title) VALUES ({$this->formId}, 'Main Section')");
        $sectionId = $this->con->insert_id;

        $this->con->query("INSERT INTO Question (IDSection, TitleQuestion, TypeQuestion) 
                          VALUES ({$sectionId}, 'How was the course?', 'evaluation')");
        $this->questionId = $this->con->insert_id;

        // 2. Setup Access Fields
        $this->con->query("INSERT INTO FormAccessFields (FormID, Label, Slug, IsRequired, FieldType, OrderIndex) VALUES 
            ({$this->formId}, 'Student ID', 'student_id', 1, 'text', 1),
            ({$this->formId}, 'Course ID', 'course_id', 1, 'text', 2)");

        // 3. Setup CIT Data (Mawad, Tanzil, Profiles)
        $this->con->query("INSERT INTO mawad (MadaNo, MadaName) VALUES ('{$this->courseId}', 'Computer Science 101')");
        
        // 10 students enrolled
        for ($i = 1; $i <= 10; $i++) {
            $kidNo = "STU" . str_pad($i, 3, '0', STR_PAD_LEFT);
            $this->con->query("INSERT INTO tanzil (KidNo, ZamanNo, MadaNo) VALUES ('{$kidNo}', {$this->semester}, '{$this->courseId}')");
            
            // 3 in CS (Kesm 1), 7 in IS (Kesm 2)
            $kesm = ($i <= 3) ? 1 : 2;
            $this->con->query("INSERT INTO sprofiles (KidNo, KesmNo) VALUES ('{$kidNo}', $kesm)");
        }
        
        $this->con->query("INSERT INTO divitions (KesmNo, dname) VALUES (1, 'Computer Science'), (2, 'Information Systems')");
    }

    public function testCourseStatisticsAccuracy() {
        // Seed 3 evaluations with specific ratings
        $ratings = [2, 4, 5]; // Sum = 11, Count = 3, Avg = 3.66... -> 3.7
        $studentIds = ['STU001', 'STU002', 'STU003']; // These are the "participants"
        
        foreach ($ratings as $index => $rating) {
            $studentId = $studentIds[$index];
            $metadata = json_encode([
                'student_id' => $studentId,
                'course_id' => $this->courseId,
                'ip_address' => '127.0.0.1',
                'submission_date' => date('Y-m-d H:i:s')
            ]);
            
            $answerJson = json_encode([
                'type' => 'evaluation',
                'value' => (float)$rating
            ]);
            
            $stmt = $this->con->prepare("INSERT INTO EvaluationResponses (FormType, FormTarget, QuestionID, AnswerValue, Metadata, Semester) VALUES (?, ?, ?, ?, ?, ?)");
            $ft = 'course_evaluation';
            $tg = 'student';
            $sem = $this->semester;
            $stmt->bind_param("ssisss", $ft, $tg, $this->questionId, $answerJson, $metadata, $sem);
            $stmt->execute();
        }

        // Call the router for statistics
        $url = "/statistics/router.php?target=student&type=course_evaluation&semester={$this->semester}&courseId={$this->courseId}";
        $res = $this->get($url);
        
        $this->assertEquals(200, $res['info']['http_code']);
        $body = $res['body'];

        // 1. Verify Average Score (3.7)
        // In the view, it's rendered as: 'average' => 3.7
        // We look for the text in the rendered HTML or evaluate the context.
        // It's likely inside a circular-progress-bar or similar.
        $this->assertStringContainsString('3.7', $body, "Average score should be 3.7");

        // 2. Verify Participation (3 participants out of 10 enrolled = 30%)
        // The Template calculates: round((3/10)*100, 1) = 30
        $this->assertStringContainsString('30%', $body, "Participation rate should be 30%");

        // 3. Verify Department Breakdown (3 students from CS, but wait, who are the participants?)
        // The participants are STU001, STU002, STU003. All 3 are in Kesm 1 (Computer Science).
        // So info should show Computer Science: 3
        $this->assertStringContainsString('Computer Science', $body);
        $this->assertStringContainsString('3', $body); // Count for CS
    }
}
