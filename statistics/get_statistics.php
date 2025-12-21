<?php
// statistics/get_statistics.php
require_once '../config/dbConnectionCit.php';
require_once '../config/DbConnection.php';
require_once '../helpers/database.php';
// require_once '../forms/form_constants.php'; // Removed
require_once '../helpers/FormTypes.php';

header('Content-Type: application/json');

class StatisticsHandler {
    private $con;
    private $conn_cit;
    private $formTarget;
    private $formType;
    private $filter;
    private $teacherId; // Add teacher ID property

    public function __construct($con, $conn_cit) {
        $this->con = $con;
        $this->conn_cit = $conn_cit;
    }

    public function handleRequest($formTarget, $formType, $filter) {
        try {
            $this->validateInput($formTarget, $formType);
            $this->formTarget = $formTarget;
            $this->formType = $formType;
            $this->filter = $filter;

            $config = $this->getFormTypeConfig();
            $processedData = $this->processEvaluations($config);
            $response = $this->buildResponse($processedData);

            echo json_encode($response);
        } catch (Exception $e) {
            $this->handleError($e);
        }
    }

    private function validateInput($formTarget, $formType) {
        $formTargets = FormTypes::getFormTargets($this->con);
        $formTypes = FormTypes::getFormTypes($this->con);

        if (!isset($formTargets[$formTarget])) {
            throw new InvalidArgumentException('Invalid form target');
        }
        if (!isset($formTypes[$formType])) {
            throw new InvalidArgumentException('Invalid form type');
        }
    }

    private function getFormTypeConfig() {
        $configs = [
            'course_evaluation' => [
                'metadata' => ['course_id', 'teacher_id'],
                'group_fields' => ['Semester', 'course_id'],
                'template' => 'course'
            ],
            'teacher_evaluation' => [
                'metadata' => ['course_id', 'teacher_id'],
                'group_fields' => ['Semester', 'course_id', 'teacher_id'],
                'template' => 'teacher'
            ],
            'program_evaluation' => [
                'metadata' => [],
                'group_fields' => $this->formTarget === 'alumni' ? ['time_period'] : ['Semester'],
                'template' => 'program'
            ],
            'facility_evaluation' => [
                'metadata' => ['facility_id'],
                'group_fields' => ['Semester', 'facility_id'],
                'template' => 'facility'
            ]
        ];

        // Return specific config if exists, otherwise return generic config
        if (isset($configs[$this->formType])) {
            return $configs[$this->formType];
        }

        // Generic fallback for dynamic types
        return [
            'metadata' => [], 
            'group_fields' => ['Semester'],
            'template' => 'generic'
        ];
    }

    private function processEvaluations($config) {
        $evaluationData = $this->fetchEvaluationData($config);
        return $this->processRecords($evaluationData, $config);
    }

    private function fetchEvaluationData($config) {
        $selectFields = array_map(function($field) {
            return "JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.{$field}')) AS {$field}";
        }, $config['metadata']);
        
        // Add time period calculation for alumni programs
        if ($this->formTarget === 'alumni' && $this->formType === 'program_evaluation') {
            $selectFields[] = $this->getTimePeriodExpression();
        }
        
        // Build SELECT clause safely
        $selectClause = empty($selectFields) ? '' : ', ' . implode(', ', $selectFields);

        $query = sprintf(
            "SELECT Semester, AnsweredAt%s 
             FROM EvaluationResponses 
             WHERE FormTarget = ? 
               AND FormType = ? 
               %s",
            $selectClause,
            $this->getTimeCondition()
        );
        
        $queryParams = $this->getQueryParams();
        $bindTypes = $this->getBindTypes();

        
        // Verify parameter count matches placeholders
        $placeholderCount = substr_count($query, '?');
        if (count($queryParams) !== $placeholderCount) {
            throw new RuntimeException("Parameter count mismatch: {$placeholderCount} placeholders vs ".count($queryParams)." parameters");
        }
    
        return fetchData(
            $this->con,
            $query,
            $queryParams,
            $bindTypes
        );
    }
    
    private function getTimePeriodExpression() {
        $intervalMap = [
            'last_month'    => "DATE_FORMAT(AnsweredAt, '%%Y-%%m')",
            'last_3_months' => "CONCAT(YEAR(AnsweredAt), '-Q', QUARTER(AnsweredAt))",
            'last_6_months' => "CONCAT(YEAR(AnsweredAt), '-H', CEIL(MONTH(AnsweredAt)/6))",
            'last_year'     => "DATE_FORMAT(AnsweredAt, '%%Y')",
            'all'           => "'all-time'"
        ];
        
        return sprintf(
            "COALESCE(%s, 'unknown-period') AS time_period",
            $intervalMap[$this->filter] ?? $intervalMap['last_month']
        );
    }
    
    private function getQueryParams() {
        $params = [$this->formTarget, $this->formType];
        
        // Add filter parameter only when needed
        if ($this->shouldAddFilterParam()) {
            $params[] = $this->getFilterParamValue();
        }
        
        return $params;
    }
    
    private function shouldAddFilterParam() {
        if ($this->filter === 'all') return false;
        
        return !($this->formTarget === 'alumni' && 
               $this->formType === 'program_evaluation');
    }
    
    private function getFilterParamValue() {
        if ($this->formTarget === 'alumni' && 
           $this->formType === 'program_evaluation') {
            return $this->getCurrentPeriodValue();
        }
        return $this->filter;
    }
    
    private function getBindTypes() {
        $types = 'ss';
        
        if ($this->shouldAddFilterParam()) {
        $types .= 's';
    }
    
        return $types;
    }
    
    private function getCurrentPeriodValue() {
        $now = new DateTime();
        switch ($this->filter) {
            case 'last_month':
                $now->modify('first day of last month');
                return $now->format('Y-m');
            case 'last_3_months':
                return $now->format('Y') . '-Q' . ceil($now->format('n')/3);
            case 'last_6_months':
                return $now->format('Y') . '-H' . ceil($now->format('n')/6);
            case 'last_year':
                return $now->format('Y');
            default:
                return '';
        }
    }
    
    private function getTimeCondition() {
        $conditions = [];
        
        if ($this->formTarget === 'alumni' && $this->formType === 'program_evaluation') {
            $filterMap = [
                'last_month'    => "AnsweredAt BETWEEN DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m-01') AND DATE_FORMAT(NOW(), '%Y-%m-01')",
                'last_3_months' => "AnsweredAt >= DATE_SUB(NOW(), INTERVAL 3 MONTH)",
                'last_6_months' => "AnsweredAt >= DATE_SUB(NOW(), INTERVAL 6 MONTH)",
                'last_year'     => "AnsweredAt >= DATE_SUB(NOW(), INTERVAL 1 YEAR)",
                'all'          => "1=1"
            ];
            
            if (isset($filterMap[$this->filter])) {
                $conditions[] = $filterMap[$this->filter];
            }
        } else {
            if ($this->filter !== 'all') {
                $conditions[] = "Semester = ?";
            }
        }
        
        return $conditions ? ' AND ' . implode(' AND ', $conditions) : '';
    }

    private function processRecords($evaluationData, $config) {
        $processed = [];
        $uniqueKeys = [];

        foreach ($evaluationData as $record) {
            $key = $this->generateUniqueKey($record, $config);
            
            if (!isset($uniqueKeys[$key])) {
                $processedItem = $this->processSingleRecord($record, $config);
                if ($processedItem) {
                    $processed[] = $processedItem;
                    $uniqueKeys[$key] = true;
                }
            }
        }

        return $processed;
    }

    private function generateUniqueKey($record, $config) {
        return implode('-', array_map(function($field) use ($record) {
            return $record[$field] ?? 'null';
        }, $config['group_fields']));
    }

    private function processSingleRecord($record, $config) {
        $baseData = [
            'semester_no' => $record['Semester'] ?? null,
            'answered_at' => $record['AnsweredAt'] ?? null
        ];
        
        if ($this->formTarget !== 'alumni') {
        $baseData['semester_name'] = $record['Semester'] 
            ? $this->getSemesterName($record['Semester']) 
            : $this->getTimeLabel();
    }

        switch ($this->formType) {
            case 'course_evaluation':
                return $this->processCourseRelatedRecord($record, $baseData);
            case 'teacher_evaluation':
                return $this->processTeacherRelatedRecord($record, $baseData);
            case 'program_evaluation':
                return $this->processProgramRecord($record);
            case 'facility_evaluation':
                return $this->processFacilityRecord($record, $baseData);
            default:
                // Use generic processor for unknown types
                return $this->processGenericRecord($record, $baseData);
        }
    }

    // Add generic record processor
    private function processGenericRecord($record, $baseData) {
        return array_merge($baseData, [
            'type' => $this->formType,
            'evaluations' => $this->getGenericEvaluationCount($record)
        ]);
    }

    private function getGenericEvaluationCount($record) {
        return fetchData(
            $this->con,
            "SELECT COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.student_id'))) AS cnt 
             FROM EvaluationResponses 
             WHERE FormTarget = ?
               AND FormType = ?
               AND Semester = ?",
            [$this->formTarget, $this->formType, $record['Semester']],
            'ssi'
        )[0]['cnt'] ?? 0;
    }
    
    private function processFacilityRecord($record, $baseData) {
        return array_merge($baseData, [
            'type' => 'facility_evaluation',
            'evaluations' => $this->getFacilityEvaluationCount($record)
        ]);
    }
    
    private function getFacilityEvaluationCount($record) {
        return fetchData(
            $this->con,
            "SELECT COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.student_id'))) AS cnt 
             FROM EvaluationResponses 
             WHERE FormTarget = 'student'
               AND FormType = 'facility_evaluation'
               AND Semester = ?",
            [$record['Semester']],
            'is'
        )[0]['cnt'] ?? 0;
    }

    private function processCourseRelatedRecord($record, $baseData) {
        $courseCode = $record['course_id'];
        $teacherId = $record['teacher_id'] ?? null;
    
        $courseInfo = $this->getCourseInfo($record['Semester'], $courseCode);
        $teacherInfo = $this->getTeacherInfo($record['Semester'], $courseCode);
    
        if (!$courseInfo || !$teacherInfo) return null;
        
        $teacherInfo['photo'] = './assets/icons/circle-user-round.svg';
    
        return array_merge($baseData, [
            'type' => $this->formType,
            'course_code' => $courseCode,
            'course_name' => $courseInfo['MadaName'],
            'teacher_id' => $teacherInfo['id'],
            'teacher_name' => $teacherInfo['name'],
            'teacher_photo' => $teacherInfo['photo'],
            'evaluations' => $this->getEvaluationCount($record),
            'total_students' => $this->getTotalStudents($record['Semester'], $courseCode)
        ]);
    }
    
    private function processTeacherRelatedRecord($record, $baseData) {
        $courseCode = $record['course_id'];
        $teacherId = $record['teacher_id'] ?? null;
        
        $courseInfo = $this->getCourseInfo($record['Semester'], $courseCode);
        $teacherInfo = $this->getTeacherInfo($record['Semester'], $courseCode);
        
        if (!$teacherInfo) return null;
        
        $teacherInfo['photo'] = './assets/icons/circle-user-round.svg';
        
        return array_merge($baseData, [
            'type' => $this->formType,
            'course_code' => $courseCode,
            'course_name' => $courseInfo['MadaName'],
            'teacher_id' => $teacherInfo['id'],
            'teacher_name' => $teacherInfo['name'],
            'teacher_photo' => $teacherInfo['photo'],
            'evaluations' => $this->getEvaluationCount($record),
            'total_students' => $this->getTotalStudents($record['Semester'], $courseCode)
        ]);
    }

    private function getSemesterName($semesterNo) {
        $result = fetchData(
            $this->conn_cit,
            "SELECT ZamanName FROM zaman WHERE ZamanNo = ?",
            [$semesterNo],
            'i'
        );
        return $result[0]['ZamanName'] ?? 'غير معروف';
    }

    private function getCourseInfo($semesterNo, $courseCode) {
        return fetchData(
            $this->conn_cit,
            "SELECT m.MadaName 
             FROM mawad m
             WHERE m.MadaNo = ?",
            [$courseCode],
            's'
        )[0] ?? null;
    }
    
    private function processProgramRecord($record) {
        if ($this->formTarget === 'alumni') {
            return [
                'type' => 'program_evaluation',
                'time_period' => $this->formatTimePeriod($record['time_period']),
                'evaluations' => $this->getProgramEvaluationCount($record),
                'start_date' => $this->getPeriodStartDate($record['time_period'])
            ];
        } else {
            return [
                'type' => 'program_evaluation',
                'semester_no' => $record['Semester'],
                'semester_name' => $this->getSemesterName($record['Semester']),
                'evaluations' => $this->getProgramEvaluationCountForStudent($record)
            ];
        }
    }
    
    private function getProgramEvaluationCountForStudent($record) {
        return fetchData(
            $this->con,
            "SELECT COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.student_id'))) AS cnt 
             FROM EvaluationResponses 
             WHERE FormTarget = 'student'
               AND FormType = 'program_evaluation'
               AND Semester = ?",
            [$record['Semester']],
            'i'
        )[0]['cnt'] ?? 0;
    }
    
    private function getTimePeriodCondition() {
        if ($this->filter === 'all') return '1=1';
        
        $intervalMap = [
            'last_month'    => "DATE_FORMAT(AnsweredAt, '%%Y-%%m') = ?",
            'last_3_months' => "CONCAT(YEAR(AnsweredAt), '-Q', QUARTER(AnsweredAt)) = ?",
            'last_6_months' => "CONCAT(YEAR(AnsweredAt), '-H', CEIL(MONTH(AnsweredAt)/6)) = ?",
            'last_year'     => "YEAR(AnsweredAt) = ?"
        ];
        
        return $intervalMap[$this->filter] ?? $intervalMap['last_month'];
    }
    
    private function formatTimePeriod($period) {
        // Handle YYYY-MM format
        if (preg_match('/^(\d{4})-(\d{2})$/', $period, $matches)) {
            return sprintf('%s %s', 
                $this->getArabicMonthName($matches[2]), 
                $matches[1]
            );
        }
        
        // Handle quarterly format (YYYY-Q1)
        if (preg_match('/^(\d{4})-Q(\d)$/', $period, $matches)) {
            return "الربع {$matches[2]} {$matches[1]}";
        }
        
        // Handle half-year format (YYYY-H1)
        if (preg_match('/^(\d{4})-H(\d)$/', $period, $matches)) {
            return "النصف {$matches[2]} {$matches[1]}";
        }
        
        // Handle year format
        if (preg_match('/^(\d{4})$/', $period)) {
            return "سنة {$matches[1]}";
        }
        
        return $period;
    }

    private function getArabicMonthName($monthNumber) {
        $months = [
            '01' => 'يناير', '02' => 'فبراير', '03' => 'مارس',
            '04' => 'أبريل', '05' => 'مايو', '06' => 'يونيو',
            '07' => 'يوليو', '08' => 'أغسطس', '09' => 'سبتمبر',
            '10' => 'أكتوبر', '11' => 'نوفمبر', '12' => 'ديسمبر'
        ];
        return $months[$monthNumber] ?? 'غير معروف';
    }
    
    private function getPeriodStartDate($period) {
        // Handle YYYY-MM
        if (preg_match('/^(\d{4})-(\d{2})$/', $period, $matches)) {
            return "{$matches[1]}-{$matches[2]}-01";
        }
        
        // Handle quarterly
        if (preg_match('/^(\d{4})-Q(\d)$/', $period, $matches)) {
            $quarterStartMonth = ($matches[2] - 1) * 3 + 1;
            return sprintf("%d-%02d-01", $matches[1], $quarterStartMonth);
        }
        
        // Handle half-year
        if (preg_match('/^(\d{4})-H(\d)$/', $period, $matches)) {
            $halfStartMonth = ($matches[2] - 1) * 6 + 1;
            return sprintf("%d-%02d-01", $matches[1], $halfStartMonth);
        }
        
        // Handle year
        if (preg_match('/^(\d{4})$/', $period, $matches)) {
            return "{$matches[1]}-01-01";
        }
        
        return date('Y-m-01');
    }

    private function getProgramEvaluationCount($record) {
        $condition = $this->getTimePeriodCondition();
        $params = [];
        $types = '';
    
        if ($this->filter !== 'all') {
            $params[] = $record['time_period'];
            $types = 's';
        }
    
        return fetchData(
            $this->con,
            "SELECT COUNT(DISTINCT CONCAT_WS(
                '|', 
                JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.job')), 
                JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.specialization'))
            )) AS cnt 
             FROM EvaluationResponses 
             WHERE FormTarget = 'alumni'
               AND FormType = 'program_evaluation'
               AND $condition
               AND JSON_EXTRACT(Metadata, '$.job') IS NOT NULL
               AND JSON_EXTRACT(Metadata, '$.specialization') IS NOT NULL
               AND JSON_EXTRACT(Metadata, '$.graduation_year') IS NOT NULL
               ",
            $params,
            $types
        )[0]['cnt'] ?? 0;
    }

    private function getTeacherInfo($semesterNo, $courseCode) {
        // Get basic teacher info without photo
        $teacher = fetchData(
            $this->conn_cit,
            "SELECT r.id, r.name 
             FROM coursesgroups c
             JOIN regteacher r ON c.TNo = r.id
             WHERE c.ZamanNo = ? AND c.MadaNo = ?",
            [$semesterNo, $courseCode],
            'is'
        )[0] ?? null;
    
        if ($teacher) {
            // Add default photo path
            $teacher['photo'] = './assets/icons/circle-user-round.svg';
        }
    
        return $teacher ?? [
            'id' => 0,
            'name' => 'غير معين',
            'photo' => './assets/icons/circle-user-round.svg'
        ];
    }

    private function getEvaluationCount($record) {
        $params = [
            $this->formTarget, 
            $this->formType, 
            $record['Semester'], 
            $record['course_id']
        ];
        $types = 'ssis';

        if ($this->formType === 'teacher_evaluation') {
            $params[] = $record['teacher_id'];
            $types .= 's';
        }

        return fetchData(
            $this->con,
            "SELECT COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.student_id'))) AS cnt 
             FROM EvaluationResponses 
             WHERE FormTarget = ? 
               AND FormType = ? 
               AND Semester = ? 
               AND JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.course_id')) = ?"
               . ($this->formType === 'teacher_evaluation' ? 
                  " AND JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.teacher_id')) = ?" : ''),
            $params,
            $types
        )[0]['cnt'] ?? 0;
    }

    private function getTotalStudents($semesterNo, $courseCode) {
        return fetchData(
            $this->conn_cit,
            "SELECT COUNT(DISTINCT KidNo) AS total 
             FROM tanzil 
             WHERE ZamanNo = ? AND MadaNo = ?",
            [$semesterNo, $courseCode],
            'is'
        )[0]['total'] ?? 0;
    }

    private function buildResponse($processedData) {
        return [
            'success' => true,
            'html' => $this->generateHtml($processedData),
            'filters' => $this->getSemesterFilters()
        ];
    }

    private function generateHtml($data) {
        $html = '';
        foreach ($data as $item) {
            $html .= match($this->formType) {
                'course_evaluation' => $this->courseTemplate($item),
                'teacher_evaluation' => $this->teacherTemplate($item),
                'program_evaluation' => $this->programTemplate($item),
                'facility_evaluation' => $this->facilityTemplate($item),
                default => $this->genericTemplate($item)
            };
        }
        return $html;
    }

    private function genericTemplate($item) {
        $queryParams = http_build_query([
            'target' => $this->formTarget,
            'type' => $this->formType,
            'semester' => $item['semester_no']
        ]);
        
        $semesterName = htmlspecialchars($item['semester_name'] ?? 'فترة غير محددة');
        $evaluations = htmlspecialchars($item['evaluations']);

        return <<<HTML
        <div class="card" data-semester="{$item['semester_no']}">
            <div class="card-content-course">
                <div class="info-course">
                    <a href="./statistics/router.php?{$queryParams}" class="facility-title">
                        تقرير {$semesterName}
                    </a>
                    <div class="facility-meta">
                        <div class="semester">
                            <img src="./assets/icons/calendar.svg" alt="الفترة">
                            {$semesterName}
                        </div>
                    </div>
                </div>
                <div class="container-number-evaluation">
                    <div class="number-evaluation">
                        <span class="primary">{$evaluations}</span>
                        تقييمات
                    </div>
                </div>
            </div>
        </div>
HTML;
    }

    private function courseTemplate($item) {
        $queryParams = http_build_query([
            'target' => 'student',
            'type' => 'course_evaluation',
            'courseId' => $item['course_code'],
            'semester' => $item['semester_no']
        ]);
    
        $courseName = htmlspecialchars($item['course_name']);
        $courseCode = htmlspecialchars($item['course_code']);
        $teacherName = htmlspecialchars($item['teacher_name']);
        $semesterName = htmlspecialchars($item['semester_name']);
        $evaluations = htmlspecialchars($item['evaluations']);
        $totalStudents = htmlspecialchars($item['total_students']);

        return <<<HTML
        <div class="card" data-semester="{$item['semester_no']}">
            <div class="card-content-course">
                <div class="info-course">
                      <a href="./statistics/router.php?{$queryParams}" class="course-title">
                        {$courseName}
                        <span>{$courseCode}</span>
                    </a>
                    
                    <div class="teacher">
                        <span>مدرس المقرر:</span>
                        <h4>{$teacherName}</h4>
                    </div>
                    
                    <div class="semester">
                        <span>الفصل الدراسي:</span>
                        {$semesterName}
                    </div>
                </div>
    
                <div class="container-number-evaluation">
                    <canvas id="chart-number-evaluation-{$courseCode}" 
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

    private function teacherTemplate($item) {
        
        $queryParams = http_build_query([
            'target' => 'student',
            'type' => 'teacher_evaluation',
            'teacher_id' => $item['teacher_id'],
            'semester' => $item['semester_no'],
            'course_code' => $item['course_code']
        ]);
        
        $teacherName = htmlspecialchars($item['teacher_name']);
        $courseName = htmlspecialchars($item['course_name']);
        $courseCode = htmlspecialchars($item['course_code']);
        $semesterName = htmlspecialchars($item['semester_name']);
        $evaluations = htmlspecialchars($item['evaluations']);
        $totalStudents = htmlspecialchars($item['total_students']);
        $teacherPhoto = htmlspecialchars($item['teacher_photo']);

        return <<<HTML
            <div class="card" data-semester="{$item['semester_no']}">
                <div class="card-content-teacher">
                    <div class="teacher-photo">
                        <img src="{$teacherPhoto}" alt="صورة المدرس">
                    </div>
        
                    <div class="card-info-teacher-course">
                        <h3>
                            <a href="./statistics/router.php?{$queryParams}">
                                {$teacherName}
                            </a>
                        </h3>
                        <p>
                            {$courseName}
                            <span>{$courseCode}</span>
                        </p>
                        <h4>
                            الفصل الدراسي
                            <span class="primary">{$semesterName}</span>
                        </h4>
                    </div>
        
                    <div class="container-number-evaluation">
                        <canvas id="chart-number-evaluation-{$item['teacher_id']}-{$courseCode}" 
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
    
    
    private function programTemplate($item) {
        if ($this->formTarget === 'alumni') {
            $queryParams = http_build_query([
                'target' => 'alumni',
                'type' => 'program_evaluation',
            ]);
            
            $timePeriod = htmlspecialchars($item['time_period']);
            $startDate = htmlspecialchars($item['start_date']);
            $evaluations = htmlspecialchars($item['evaluations']);

            return <<<HTML
            <div class="card">
                <div class="card-content-course">
                    <div class="info-course">
                        <a href="./statistics/router.php?{$queryParams}">
                            <h3 class="program-title">
                                الفترة: {$timePeriod}
                            </h3>
                        </a>
                        <div class="meta-item">
                            <img src="./assets/icons/calendar.svg" alt="التاريخ">
                            بداية: {$startDate}
                        </div>
                    </div>
                    <div class="container-number-evaluation">
                        <div class="number-evaluation">
                            <span class="primary">{$evaluations}</span>
                            تقييمات
                        </div>
                    </div>
                </div>
            </div>
HTML;
        } else if ($this->formTarget === 'student') {
            $queryParams = http_build_query([
                'target' => 'student',
                'type' => 'program_evaluation',
                'semester' => $item['semester_no']
            ]);
            
            $semesterName = htmlspecialchars($item['semester_name']);
            $evaluations = htmlspecialchars($item['evaluations']);

            return <<<HTML
            <div class="card" data-semester="{$item['semester_no']}">
                <div class="card-content-course">
                    <div class="info-course">
                        <a href="./statistics/router.php?{$queryParams}" class="program-title">
                            {$semesterName}
                        </a>
                    </div>
                    <div class="container-number-evaluation">
                        <div class="number-evaluation">
                            <span class="primary">{$evaluations}</span>
                            طالب مشارك
                        </div>
                    </div>
                </div>
            </div>
HTML;
        }
    }

    private function facilityTemplate($item) {
        $queryParams = http_build_query([
            'target' => 'student',
            'type' => 'facility_evaluation',
            'semester' => $item['semester_no']
        ]);
        
        $semesterName = htmlspecialchars($item['semester_name']);
        $evaluations = htmlspecialchars($item['evaluations']);

        return <<<HTML
        <div class="card" data-semester="{$item['semester_no']}">
            <div class="card-content-course">
                <div class="info-course">
                    <a href="./statistics/router.php?{$queryParams}" class="facility-title">
                        المؤسسة كلية التقنية الصناعية
                    </a>
                    <div class="facility-meta">
                        <div class="semester">
                            <img src="./assets/icons/calendar.svg" alt="الفصل">
                            {$semesterName}
                        </div>
                    </div>
                </div>
                <div class="container-number-evaluation">
                    <div class="number-evaluation">
                        <span class="primary">{$evaluations}</span>
                        تقييمات
                    </div>
                </div>
            </div>
        </div>
HTML;
    }
    
    private function getSemesterFilters() {
        if ($this->formTarget === 'alumni' && $this->formType === 'program_evaluation') {
            return [
                'last_month'    => 'آخر شهر',
                'last_3_months' => 'آخر 3 أشهر',
                'last_6_months' => 'آخر 6 أشهر',
                'last_year'     => 'آخر سنة'
            ];
        }
    
        // Get only semesters with actual evaluations
        $semesters = fetchData(
            $this->con,
            "SELECT DISTINCT e.Semester, z.ZamanName 
             FROM EvaluationResponses e
             JOIN citcoder_Citgate.zaman z ON e.Semester = z.ZamanNo
             WHERE e.FormTarget = ? 
               AND e.FormType = ?
             ORDER BY e.Semester DESC",
            [$this->formTarget, $this->formType],
            'ss'
        );
    
        return array_column($semesters ?: [], 'ZamanName', 'Semester');
    }
    
    private function getTimeLabel() {
        $labels = [
            'last_month' => 'آخر شهر',
            'last_3_months' => 'آخر 3 أشهر',
            'last_6_months' => 'آخر 6 أشهر',
            'last_year' => 'آخر سنة'
        ];
        return $labels[$this->filter] ?? 'غير محدد';
    }

    private function handleError($e) {
        error_log("Statistics Error: " . $e->getMessage());
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'An error occurred while processing your request'
        ]);
    }
}

// Initialize and execute handler
try {
    $handler = new StatisticsHandler($con, $conn_cit);
    $handler->handleRequest(
        $_GET['target'] ?? 'student',
        $_GET['type'] ?? 'course_evaluation',
        $_GET['filter'] ?? 'all'
    );
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error']);
}