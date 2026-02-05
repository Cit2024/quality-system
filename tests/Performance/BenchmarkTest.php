<?php
namespace QualitySystem\Tests\Performance;

use PHPUnit\Framework\TestCase;

class BenchmarkTest extends TestCase {
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
    }

    protected function tearDown(): void {
        if ($this->con) {
            $this->con->close();
        }
        parent::tearDown();
    }

    public function testReportingPerformance() {
        // Query 1: Aggregation by Semester (Simulates Dashboard)
        // Uses idx_responses_form_type_target (FormType, FormTarget, Semester)
        // Note: We group by Semester, so index on Semester (or covering index) helps.
        // Index `idx_responses_form_type_target` starts with FormType, so it helps if we filter by FormType.
        
        $startTime = microtime(true);
        $query = "SELECT Semester, COUNT(*) as Total, AVG(JSON_EXTRACT(Metadata, '$.score')) as AvgScore 
                  FROM EvaluationResponses 
                  GROUP BY Semester";
        $this->con->query($query);
        $duration = microtime(true) - $startTime;
        
        echo "\n   -> Dashboard Aggregation: " . number_format($duration, 4) . "s";
        $this->assertLessThan(1.0, $duration, "Dashboard query too slow (>1s)");

        // Query 2: Filtered by FormType (Specific Report)
        // Should use idx_responses_form_type_target
        $startTime = microtime(true);
        $query = "SELECT FormTarget, COUNT(*) as Total 
                  FROM EvaluationResponses 
                  WHERE FormType = 'Course-Eval' 
                  GROUP BY FormTarget";
        $this->con->query($query);
        $duration = microtime(true) - $startTime;
        
        echo "\n   -> Course Evaluation Report: " . number_format($duration, 4) . "s";
        // This should be very fast with index
        $this->assertLessThan(0.5, $duration, "Course report too slow (>0.5s)");

        // Query 3: Time-based Range (Recent Evaluations)
        // Uses idx_responses_date (AnsweredAt)
        $startTime = microtime(true);
        $query = "SELECT COUNT(*) FROM EvaluationResponses WHERE AnsweredAt > DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $this->con->query($query);
        $duration = microtime(true) - $startTime;
        
        echo "\n   -> Recent Activity Query: " . number_format($duration, 4) . "s";
        $this->assertLessThan(0.2, $duration, "Date range query too slow (>0.2s)");
    }
}
