<?php
// statistics/get_teacher_statistics.php
require_once '../config/dbConnectionCit.php';
require_once '../config/DbConnection.php';
require_once '../helpers/database.php';
// require_once '../forms/form_constants.php'; // Removed
require_once '../helpers/FormTypes.php';

header('Content-Type: application/json');

class TeacherStatisticsHandler
{
    private $con;
    private $conn_cit;
    private $teacherId;
    private $formType;
    private $filter;

    public function __construct($con, $conn_cit)
    {
        $this->con = $con;
        $this->conn_cit = $conn_cit;
    }

    public function handleRequest($teacherId, $formType, $filter)
    {
        try {
            $this->validateInput($teacherId, $formType);
            $this->teacherId = $teacherId;
            $this->formType = $formType;
            $this->filter = $filter;

            $teacherInfo = $this->getTeacherInfo();
            $semesters = $this->getTeacherSemesters();
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
            throw new InvalidArgumentException('Invalid form type');
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
            throw new Exception('Teacher not found');
        }

        $teacher = $result[0];
        $teacher['photo'] = $this->getTeacherPhoto();

        // Remove the form type configuration logic
        return $teacher;
    }

    private function getTeacherPhoto()
    {
        // Placeholder for actual photo retrieval logic
        return 'fa-solid fa-circle-user';
    }

    private function shouldAddFilterParam()
    {
        if ($this->filter === 'all') return false;
    }

    private function getFilterParamValue()
    {
        return $this->filter;
    }

    private function getTeacherSemesters()
    {
        $query = "SELECT DISTINCT c.ZamanNo, z.ZamanName 
                  FROM coursesgroups c
                  JOIN zaman z ON c.ZamanNo = z.ZamanNo
                  WHERE c.TNo = ?
                  ORDER BY c.ZamanNo DESC";

        return fetchData(
            $this->conn_cit,
            $query,
            [$this->teacherId],
            'i'
        );
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

                // Get only the requested evaluation type
                if ($this->formType === 'course_evaluation') {
                    $evaluations = $this->getCourseEvaluations($semester['ZamanNo'], $course['MadaNo']);
                } elseif ($this->formType === 'teacher_evaluation') {
                    $evaluations = $this->getTeacherEvaluations($semester['ZamanNo'], $course['MadaNo']);
                }

                // Skip courses with no evaluations
                if ($evaluations == 0) {
                    continue;
                }

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

    private function getTeacherEvaluations($semesterNo, $courseCode)
    {
        return fetchData(
            $this->con,
            "SELECT COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.student_id'))) AS cnt 
             FROM EvaluationResponses 
             WHERE FormTarget = 'student'
               AND FormType = 'teacher_evaluation'
               AND Semester = ?
               AND JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.course_id')) = ?
               AND JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.teacher_id')) = ?",
            [$semesterNo, $courseCode, $this->teacherId],
            'iss'
        )[0]['cnt'] ?? 0;
    }

    private function getSemesterCourses($semesterNo)
    {
        return fetchData(
            $this->conn_cit,
            "SELECT c.MadaNo, m.MadaName 
             FROM coursesgroups c
             JOIN mawad m ON c.MadaNo = m.MadaNo
             WHERE c.ZamanNo = ? AND c.TNo = ?",
            [$semesterNo, $this->teacherId],
            'ii'
        );
    }

    private function getCourseEvaluations($semesterNo, $courseCode)
    {
        return fetchData(
            $this->con,
            "SELECT COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.student_id'))) AS cnt 
             FROM EvaluationResponses 
             WHERE FormTarget = 'student'
               AND FormType = 'course_evaluation'
               AND Semester = ?
               AND JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.course_id')) = ?",
            [$semesterNo, $courseCode],
            'is'
        )[0]['cnt'] ?? 0;
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
        return [
            'success' => true,
            'html' => $this->generateHtml($processedData),
            'filters' => $this->getSemesterFilters()
        ];
    }

    private function generateHtml($data)
    {
        $html = '';
        foreach ($data as $semester) {
            foreach ($semester['courses'] as $course) {
                if ($this->formType === 'course_evaluation') {
                    $html .= $this->courseTemplate($semester, $course);
                } elseif ($this->formType === 'teacher_evaluation') {
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
        $evaluations = htmlspecialchars($course['evaluations']);
        $totalStudents = htmlspecialchars($course['total_students']);

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
                <canvas id="chart-course-{$courseCode}-{$semester['semester_no']}" 
                        data-evaluations="{$evaluations}" 
                        data-total-students="{$totalStudents}"></canvas>
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
        $evaluations = htmlspecialchars($course['evaluations']);
        $totalStudents = htmlspecialchars($course['total_students']);

        return <<<HTML
<div class="card" data-semester="{$semester['semester_no']}">
    <div class="card-content-teacher">
        <div class="card-info-teacher-course">
            <h3>
                <a href="./statistics/router.php?{$queryParams}">
                    تقييمي في المقرر {$courseName}           
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
            <canvas id="chart-teacher-{$this->teacherId}-{$courseCode}" 
                    data-evaluations="{$evaluations}" 
                    data-total-students="{$totalStudents}"></canvas>
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

    private function getSemesterFilters()
    {
        // Not needed for teacher view, but keeping structure
        return [];
    }

    private function handleError($e)
    {
        error_log("Teacher Statistics Error: " . $e->getMessage());
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'حدث خطأ أثناء معالجة طلبك'
        ]);
    }
}

// Initialize and execute handler
try {
    $handler = new TeacherStatisticsHandler($con, $conn_cit);
    $handler->handleRequest(
        $_GET['teacher_id'] ?? 0,
        $_GET['type'] ?? 'course_evaluation',
        $_GET['filter'] ?? 'all'
    );
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'خطأ في الخادم: ' . $e->getMessage()
    ]);
}
