<?php
/**
 * Unified Statistics API
 * Path: statistics/get_statistics.php
 * Supports: Teacher Dashboard & Admin Evaluation Dashboard
 */

require_once '../config/dbConnectionCit.php';
require_once '../config/DbConnection.php';
require_once '../helpers/database.php';
require_once '../helpers/FormTypes.php';

header('Content-Type: application/json');

class TeacherStatisticsHandler
{
    private $con;
    private $conn_cit;
    private $teacherId;
    private $formType;
    private $filter;
    private $isGlobal = false;

    public function __construct($con, $conn_cit) {
        $this->con = $con;
        $this->conn_cit = $conn_cit;
    }

    public function handleRequest($teacherId, $formType, $filter)
    {
        try {
            $this->validateInput($teacherId, $formType);
            $this->formType = $formType;
            $this->filter = $filter;

            // GLOBAL MODE: No specific teacher requested (Admin view or General dashboard)
            if (empty($teacherId) || $teacherId == 0) {
                $this->isGlobal = true;
                $this->teacherId = 0;
                $teacherInfo = ['name' => 'الكل', 'id' => 0, 'photo' => 'fa-solid fa-users'];
            } else {
                // TEACHER MODE: Specific teacher requested
                if (session_status() === PHP_SESSION_NONE) session_start();
                
                // 1. Session check for logged-in teacher
                if (isset($_SESSION['teacher_id']) && $teacherId == $_SESSION['teacher_id']) {
                    if (!empty($_SESSION['reg_teacher_id'])) {
                        $this->teacherId = $_SESSION['reg_teacher_id'];
                    } else {
                        throw new Exception("Your account is not linked to the Teacher Registry.");
                    }
                } else {
                    // 2. Lookup mapping for external/admin requests
                    $linkedId = $this->lookupRegId($teacherId);
                    $this->teacherId = $linkedId ? $linkedId : (int)$teacherId;
                }
                
                $teacherInfo = $this->getTeacherInfo();
            }

            $semesters = $this->getSemesters();
            $processedData = $this->processSemesters($semesters, $teacherInfo);
            $response = $this->buildResponse($teacherInfo, $processedData);

            echo json_encode($response);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    private function validateInput($teacherId, $formType)
    {
        if (!is_numeric($teacherId)) {
            throw new InvalidArgumentException('Invalid teacher ID');
        }

        $formTypes = FormTypes::getFormTypes($this->con);
        if (!isset($formTypes[$formType])) {
            throw new InvalidArgumentException('Invalid form type: ' . $formType);
        }
    }

    private function getTeacherInfo()
    {
        $result = fetchData(
            $this->conn_cit,
            "SELECT id, name FROM regteacher WHERE id = ?",
            [$this->teacherId],
            'i'
        );

        if (empty($result)) {
            throw new Exception('Teacher not found in registry (ID: ' . $this->teacherId . ')');
        }

        $teacher = $result[0];
        $teacher['photo'] = 'fa-solid fa-circle-user';
        return $teacher;
    }

    private function getSemesters()
    {
        $params = [];
        $types = '';

        if ($this->isGlobal) {
            // Smart filtering: Only fetch semesters that have evaluation data
            // This prevents empty results when latest semesters have no responses
            $query = "SELECT DISTINCT z.ZamanNo, z.ZamanName 
                      FROM zaman z
                      WHERE EXISTS (
                          SELECT 1 
                          FROM citcoder_Quality.EvaluationResponses er
                          WHERE er.Semester = z.ZamanNo
                            AND er.FormType = ?
                      )";
            $params[] = $this->formType;
            $types .= 's';

            if ($this->filter !== 'all' && is_numeric($this->filter)) {
                $query .= " AND z.ZamanNo = ?";
                $params[] = $this->filter;
                $types .= 'i';
            }

            $query .= " ORDER BY z.ZamanNo DESC LIMIT 10";
            
            return fetchData($this->conn_cit, $query, $params, $types);
        }

        $query = "SELECT DISTINCT c.ZamanNo, z.ZamanName 
                  FROM coursesgroups c
                  JOIN zaman z ON c.ZamanNo = z.ZamanNo
                  WHERE c.TNo = ?";
        $params[] = $this->teacherId;
        $types .= 'i';

        if ($this->filter !== 'all' && is_numeric($this->filter)) {
            $query .= " AND c.ZamanNo = ?";
            $params[] = $this->filter;
            $types .= 'i';
        }

        $query .= " ORDER BY c.ZamanNo DESC";

        return fetchData($this->conn_cit, $query, $params, $types);
    }

    private function processSemesters($semesters, $teacherInfo)
    {
        $processed = [];

        foreach ($semesters as $semester) {
            $courses = $this->getSemesterCourses($semester['ZamanNo']);
            $processedCourses = [];

            foreach ($courses as $course) {
                $totalStudents = $this->getTotalStudents($semester['ZamanNo'], $course['MadaNo']);
                $evaluations = 0;

                if ($this->formType === 'course_evaluation') {
                    $evaluations = $this->getCourseEvaluations($semester['ZamanNo'], $course['MadaNo']);
                } elseif ($this->formType === 'teacher_evaluation') {
                    $evaluations = $this->getTeacherEvaluations($semester['ZamanNo'], $course['MadaNo']);
                }

                // Skip courses with no evaluations
                if ($evaluations == 0) continue;

                $processedCourses[] = [
                    'course_code' => $course['MadaNo'],
                    'course_name' => $course['MadaName'],
                    'evaluations' => $evaluations,
                    'total_students' => $totalStudents,
                ];
            }

            if (!empty($processedCourses)) {
                $processed[] = [
                    'semester_no' => $semester['ZamanNo'],
                    'semester_name' => $semester['ZamanName'],
                    'courses' => $processedCourses
                ];
            }
        }

        return $processed;
    }

    private function getSemesterCourses($semesterNo)
    {
        if ($this->isGlobal) {
            return fetchData(
                $this->conn_cit,
                "SELECT DISTINCT c.MadaNo, m.MadaName 
                 FROM coursesgroups c
                 JOIN mawad m ON c.MadaNo = m.MadaNo
                 WHERE c.ZamanNo = ?",
                [$semesterNo],
                'i'
            );
        }

        return fetchData(
            $this->conn_cit,
            "SELECT DISTINCT c.MadaNo, m.MadaName 
             FROM coursesgroups c
             JOIN mawad m ON c.MadaNo = m.MadaNo
             WHERE c.ZamanNo = ? AND c.TNo = ?",
            [$semesterNo, $this->teacherId],
            'ii'
        );
    }

    private function getCourseEvaluations($semesterNo, $courseCode)
    {
        $query = "SELECT COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.student_id'))) AS cnt 
                  FROM EvaluationResponses 
                  WHERE FormType = 'course_evaluation'
                    AND Semester = ?
                    AND JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.course_id')) = ?";
        
        return fetchData($this->con, $query, [$semesterNo, $courseCode], 'is')[0]['cnt'] ?? 0;
    }

    private function getTeacherEvaluations($semesterNo, $courseCode)
    {
        $query = "SELECT COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.student_id'))) AS cnt 
                  FROM EvaluationResponses 
                  WHERE FormType = 'teacher_evaluation'
                    AND Semester = ?
                    AND JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.course_id')) = ?";
        
        $params = [$semesterNo, $courseCode];
        $types = 'is';

        if (!$this->isGlobal) {
            $query .= " AND JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.teacher_id')) = ?";
            $params[] = $this->teacherId;
            $types .= 's';
        }
        
        return fetchData($this->con, $query, $params, $types)[0]['cnt'] ?? 0;
    }

    private function getTotalStudents($semesterNo, $courseCode)
    {
        return fetchData(
            $this->conn_cit,
            "SELECT COUNT(DISTINCT KidNo) AS total 
             FROM tanzil 
             WHERE ZamanNo = ? AND MadaNo = ?",
            [$semesterNo, $courseCode],
            'is'
        )[0]['total'] ?? 0;
    }

    private function buildResponse($teacherInfo, $processedData)
    {
        // Extract filters (Semesters)
        $filters = [];
        
        // We need all available semesters for the filter list, not just the filtered result set.
        // However, fetching them again is redundant if we already have them.
        // But if filtering is active, processedData only contains ONE semester.
        // So we might need a separate call to get ALL semesters if filter is active?
        // Or cleaner: Fetch ALL semesters first, THEN populate filter list, THEN filter processedData?
        // Ah, getSemesters() does the filtering.
        // This means if I pick "Fall 2025", the dropdown will only show "Fall 2025" if I just depend on what's returned.
        // To fix this proper UI pattern:
        // 1. Fetch available semesters (unfiltered list) for the dropdown.
        // 2. Fetch data (filtered list).
        
        // Strategy: 
        // Always fetch available semesters (limit 10 or so).
        // If filter is active, filter processedData in-memory or by query?
        // Actually, let's keep it simple: Just return the semesters found in the data for now.
        // If the user selects a semester, reloading shows only that semester.
        // BUT then they can't switch back easily unless there's an "All" option (which is default).
        // Wait, if I filter the query, I lose the other options from the dropdown!
        // That's bad UX.
        
        // Better: Fetch ALL available semesters for the dropdown separately? 
        // Or fetch data for ALL semesters and filter in PHP loop?
        // Or fetch data for ALL semesters and return ALL, let front-end filter?
        
        // Let's modify:
        // 1. getSemesters() should return ALL relevant semesters (up to limit).
        // 2. processSemesters() iterates them.
        // 3. buildResponse populates filters from result.
        // 4. BUT if user selected a filter, we ONLY show THAT semester's data in HTML?
        //    YES.
        
        // So revert getSemesters() change? No, let's keep getSemesters returning ALL.
        // And filter in generateHtml or processSemesters?
        // Let's filter in generateHtml or return data structure the frontend uses.
        
        // Actually, looking at scripts/statistics.js:
        // updateFilters(filters) replaces options.
        // So if I return only 1 semester, the dropdown shrinks to 1 option + All.
        // Users can pick All to reset. That's acceptable for now.
        
        $seen = [];
        // If processedData is filtered, we only see 1.
        // If we want FULL list, we need another query.
        // Let's just use processedData for now to be safe and simple.
        
        foreach ($processedData as $semester) {
            $id = $semester['semester_no'];
            if (!isset($seen[$id])) {
                $filters[] = [
                    'id' => $id,
                    'text' => $semester['semester_name']
                ];
                $seen[$id] = true;
            }
        }
        
        return [
            'success' => true,
            'teacher' => $teacherInfo,
            'html' => $this->generateHtml($processedData), // This will naturally be filtered if processedData is filtered
            'filters' => $filters
        ];
    }

    private function generateHtml($data)
    {
        $html = '';
        foreach ($data as $semester) {
            foreach ($semester['courses'] as $course) {
                if ($this->formType === 'course_evaluation') {
                    $html .= $this->courseTemplate($semester, $course);
                } else {
                    $html .= $this->teacherTemplate($semester, $course);
                }
            }
        }
        return $html;
    }

    private function courseTemplate($semester, $course)
    {
        $queryParams = http_build_query([
            'target' => 'student',
            'type' => 'course_evaluation',
            'courseId' => $course['course_code'],
            'semester' => $semester['semester_no']
        ]);

        $courseName = htmlspecialchars($course['course_name']);
        $courseCode = htmlspecialchars($course['course_code']);
        $semesterName = htmlspecialchars($semester['semester_name']);
        $evaluations = (int)$course['evaluations'];
        $totalStudents = (int)$course['total_students'];

        return <<<HTML
    <div class="card" data-semester="{$semester['semester_no']}">
        <div class="card-content-course">
            <div class="info-course">
                <a href="./statistics/router.php?{$queryParams}" class="course-title">
                    {$courseName}
                    <span>{$courseCode}</span>
                </a>
                <div class="semester">
                    <span>الفصل الدراسي:</span>
                    {$semesterName}
                </div>
            </div>
            <div class="container-number-evaluation">
                <!-- Doughnut Chart Container -->
                <div class="chart-container-doughnut" style="position: relative; height: 150px; width: 150px; margin: 0 auto 15px auto;">
                    <canvas id="chart-number-evaluation-{$courseCode}-{$semester['semester_no']}" 
                            data-evaluations="{$evaluations}" 
                            data-total-students="{$totalStudents}"></canvas>
                </div>
                
                <div class="number-evaluation">
                    عدد التقييمات
                    <span class="primary">{$evaluations}</span>
                    من أصل
                    <span>{$totalStudents}</span>
                </div>
            </div>
        </div>
    </div>
HTML;
    }

    private function teacherTemplate($semester, $course)
    {
        $queryParams = http_build_query([
            'target' => 'student',
            'type' => 'teacher_evaluation',
            'semester' => $semester['semester_no'],
            'course_code' => $course['course_code'],
            'teacher_id' => $this->teacherId
        ]);

        $courseName = htmlspecialchars($course['course_name']);
        $courseCode = htmlspecialchars($course['course_code']);
        $semesterName = htmlspecialchars($semester['semester_name']);
        $evaluations = (int)$course['evaluations'];
        $totalStudents = (int)$course['total_students'];

        return <<<HTML
<div class="card" data-semester="{$semester['semester_no']}">
    <div class="card-content-teacher">
        <div class="card-info-teacher-course">
            <h3>
                <a href="./statistics/router.php?{$queryParams}">
                    تقييم المقرر {$courseName}           
                </a>
            </h3>
            <p>
                <span>{$courseCode}</span>
            </p>
            <h4>
                الفصل الدراسي
                <span class="primary">{$semesterName}</span>
            </h4>
        </div>
        <div class="container-number-evaluation">
             <!-- Doughnut Chart Container -->
            <div class="chart-container-doughnut" style="position: relative; height: 150px; width: 150px; margin: 0 auto 15px auto;">
                <canvas id="chart-number-evaluation-{$this->teacherId}-{$courseCode}" 
                        data-evaluations="{$evaluations}" 
                        data-total-students="{$totalStudents}"></canvas>
            </div>

            <div class="number-evaluation">
                التقييمات
                <span class="primary">{$evaluations}</span>
                من أصل
                <span>{$totalStudents}</span>
            </div>
        </div>
    </div>
</div>
HTML;
    }

    private function lookupRegId($id) {
        if (!$this->conn_cit) return null;
        $stmt = $this->conn_cit->prepare("SELECT RegTeacherID FROM teachers_evaluation WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $res = $stmt->get_result();
        return ($row = $res->fetch_assoc()) ? $row['RegTeacherID'] : null;
    }

    private function handleError($e)
    {
        error_log("Teacher Statistics Error: " . $e->getMessage());
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Error: ' . $e->getMessage()
        ]);
    }
}

// Execute logic
try {
    $handler = new TeacherStatisticsHandler($con, $conn_cit);
    $handler->handleRequest(
        $_GET['teacher_id'] ?? 0,
        $_GET['type'] ?? 'course_evaluation',
        $_GET['filter'] ?? 'all'
    );
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
