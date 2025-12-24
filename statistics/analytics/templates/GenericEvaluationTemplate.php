<?php
/**
 * Generic Evaluation Template
 * Dynamically renders statistics for any form type/evaluator combination
 * based on configuration
 */

require_once __DIR__ . '/../shared/auth.php';
require_once __DIR__ . '/../shared/database.php';
require_once __DIR__ . '/../shared/data_fetcher.php';
require_once __DIR__ . '/../config/ConfigurationLoader.php';

class GenericEvaluationTemplate {
    private $formType;
    private $evaluatorType;
    private $config;
    private $filters = [];
    private $params = [];
    private $bindTypes = '';

    public function __construct($formType, $evaluatorType) {
        $this->formType = $formType;
        $this->evaluatorType = $evaluatorType;
        
        // Load configuration
        $this->config = ConfigurationLoader::getConfig($formType, $evaluatorType);
        
        if (!$this->config) {
            throw new RuntimeException("No configuration found for $formType + $evaluatorType");
        }
    }

    /**
     * Process request and render statistics
     */
    public function render($requestParams) {
        // 1. Extract and validate filters from request
        $this->extractFilters($requestParams);
        
        // 2. Fetch entity lookup data if configured
        $entityData = $this->fetchEntityData();
        
        // 3. Fetch evaluation questions
        $groupedQuestions = $this->fetchQuestions();
        
        // 4. Process questions (normalize distributions, etc.)
        $this->processQuestions($groupedQuestions);
        
        // 5. Calculate statistics
        $stats = $this->calculateStatistics($groupedQuestions);
        
        // 6. Fetch historical data
        $history = $this->fetchHistoricalData();
        
        // 7. Prepare view data
        $viewData = $this->prepareViewData($entityData, $groupedQuestions, $stats, $history);
        
        // 8. Include view template
        $this->includeView($viewData);
    }

    /**
     * Extract filters from request based on configuration
     */
    private function extractFilters($requestParams) {
        $metadataFilters = $this->config['metadata_filters'] ?? [];
        
        foreach ($metadataFilters as $filterName => $filterConfig) {
            $paramName = $filterConfig['param'];
            $value = $requestParams[$paramName] ?? null;
            
            if ($value === null) {
                throw new InvalidArgumentException("Missing required parameter: $paramName");
            }
            
            // Sanitize based on type
            if ($filterConfig['type'] === 'int') {
                $value = (int)$value;
            } else {
                $value = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
            }
            
            $this->filters[$filterName] = $value;
            $this->params[] = $value;
            $this->bindTypes .= $filterConfig['bind_type'];
        }
    }

    /**
     * Fetch entity data (e.g., course info, teacher info)
     */
    private function fetchEntityData() {
        $entityLookup = $this->config['entity_lookup'] ?? null;
        
        if (!$entityLookup) {
            return null;
        }
        
        // Build params for entity lookup
        $lookupParams = [];
        foreach ($entityLookup['params'] as $paramKey) {
            $lookupParams[] = $this->filters[$paramKey];
        }
        
        $result = safeFetch(
            getCITConnection(),
            $entityLookup['query'],
            $lookupParams,
            $entityLookup['bind_types']
        );
        
        return $result[0] ?? null;
    }

    /**
     * Fetch evaluation questions
     */
    private function fetchQuestions() {
        $whereConditions = $this->config['where_conditions'] ?? [];
        
        return getGroupedQuestions(
            getQualityConnection(),
            $this->formType,
            $this->evaluatorType,
            $whereConditions,
            $this->params,
            $this->bindTypes
        ) ?? [];
    }

    /**
     * Process questions (normalize rating distributions)
     */
    private function processQuestions(&$groupedQuestions) {
        if (!isset($groupedQuestions['evaluation'])) {
            return;
        }
        
        foreach ($groupedQuestions['evaluation'] as &$question) {
            $rawDistribution = array_count_values(
                array_map(fn($a) => (int)round($a['value']), $question['Answers'] ?? [])
            );
            
            $question['distribution'] = array_replace(
                [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
                $rawDistribution
            );
            
            ksort($question['distribution']);
        }
        unset($question);
    }

    /**
     * Calculate statistics
     */
    private function calculateStatistics($groupedQuestions) {
        $stats = [];
        $modules = $this->config['statistics_modules'] ?? [];
        $customModules = $this->config['custom_modules'] ?? [];
        
        // Always calculate participation if module is enabled
        if (in_array('participation', $modules)) {
            $stats = array_merge($stats, $this->calculateParticipation());
        }
        
        // Department breakdown
        if (in_array('department_breakdown', $modules)) {
            $stats['department_stats'] = $this->calculateDepartmentBreakdown();
        }
        
        // Custom modules for course-specific data
        if (in_array('teacher_lookup', $customModules)) {
            $stats['teachers'] = $this->fetchTeachers($groupedQuestions);
        }
        
        if (in_array('enrolled_students_count', $customModules)) {
            $stats['total_students'] = $this->getEnrolledStudentsCount();
        }
        
        return $stats;
    }

    /**
     * Calculate participation statistics
     */
    private function calculateParticipation() {
        $semester = $this->filters['semester'] ?? null;
        
        if (!$semester) {
            return [];
        }
        
        // Get total students
        $totalStudents = safeFetch(
            getCITConnection(),
            "SELECT COUNT(DISTINCT KidNo) AS total_students
             FROM tanzil 
             WHERE ZamanNo = ?",
            [$semester],
            'i'
        )[0]['total_students'] ?? 0;
        
        // Get evaluated students
        $evaluatedStudents = safeFetch(
            getQualityConnection(),
            "SELECT DISTINCT 
                JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.student_id')) AS IDStudent
             FROM EvaluationResponses 
             WHERE FormType = ?
               AND FormTarget = ?
               AND Semester = ?",
            [$this->formType, $this->evaluatorType, $semester],
            'ssi'
        );
        
        $totalEvaluations = count($evaluatedStudents);
        $participationRate = $totalStudents > 0 
            ? round(($totalEvaluations / $totalStudents) * 100, 1)
            : 0;
        
        return [
            'total_students' => $totalStudents,
            'participants' => $totalEvaluations,
            'participation_rate' => $participationRate,
            'non_participants' => max($totalStudents - $totalEvaluations, 0)
        ];
    }

    /**
     * Calculate department breakdown
     */
    private function calculateDepartmentBreakdown() {
        $semester = $this->filters['semester'] ?? null;
        
        if (!$semester) {
            return [];
        }
        
        $evaluatedStudents = safeFetch(
            getQualityConnection(),
            "SELECT DISTINCT 
                JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.student_id')) AS IDStudent
             FROM EvaluationResponses 
             WHERE FormType = ?
               AND FormTarget = ?
               AND Semester = ?",
            [$this->formType, $this->evaluatorType, $semester],
            'ssi'
        );
        
        $departmentStats = [];
        
        foreach ($evaluatedStudents as $student) {
            $studentInfo = safeFetch(
                getCITConnection(),
                "SELECT d.dname 
                 FROM sprofiles s
                 JOIN divitions d ON s.KesmNo = d.KesmNo
                 WHERE s.KidNo = ?",
                [$student['IDStudent']],
                'i'
            )[0] ?? ['dname' => 'غير معروف'];
            
            $department = $studentInfo['dname'];
            $departmentStats[$department] = ($departmentStats[$department] ?? 0) + 1;
        }
        
        return $departmentStats;
    }

    /**
     * Fetch teacher information for course evaluations
     */
    private function fetchTeachers($groupedQuestions) {
        if (!isset($this->filters['course_id'], $this->filters['semester'])) {
            return [];
        }
        
        $courseCode = $this->filters['course_id'];
        $semester = $this->filters['semester'];
        
        // Extract group numbers from responses
        $groupNumbers = [];
        foreach ($groupedQuestions as $questions) {
            foreach ($questions as $question) {
                foreach ($question['Answers'] as $answer) {
                    if (isset($answer['metadata']['group_id'])) {
                        $groupNumbers[] = $answer['metadata']['group_id'];
                    }
                }
            }
        }
        
        $groupNumbers = array_unique($groupNumbers);
        $teachers = [];
        
        foreach ($groupNumbers as $gNo) {
            $teacherData = safeFetch(
                getCITConnection(),
                "SELECT rt.name, rt.id 
                 FROM citcoder_Citgate.coursesgroups cg
                 JOIN citcoder_Citgate.regteacher rt ON cg.TNo = rt.id
                 WHERE cg.GNo = ?
                   AND cg.MadaNo = ?
                   AND cg.ZamanNo = ?",
                [$gNo, $courseCode, $semester],
                'isi'
            );
            
            if (!empty($teacherData)) {
                $teachers[] = [
                    'name' => $teacherData[0]['name'],
                    'photo' => '../assets/icons/circle-user-round.svg'
                ];
            }
        }
        
        return array_unique($teachers, SORT_REGULAR);
    }
    
    /**
     * Get enrolled students count for course
     */
    private function getEnrolledStudentsCount() {
        if (!isset($this->filters['course_id'], $this->filters['semester'])) {
            return 0;
        }
        
        $courseCode = $this->filters['course_id'];
        $semester = $this->filters['semester'];
        
        $result = safeFetch(
            getCITConnection(),
            "SELECT COUNT(DISTINCT t.KidNo) AS total_students
             FROM tanzil t 
             WHERE t.MadaNo = ? AND t.ZamanNo = ?",
            [$courseCode, $semester],
            'si'
        );
        
        return $result[0]['total_students'] ?? 0;
    }
    
    /**
     * Fetch available semesters for filtering
     */
    private function fetchAvailableSemesters() {
        // Only for course evaluations with course_id filter
        if (!isset($this->filters['course_id'])) {
            return [];
        }
        
        $courseCode = $this->filters['course_id'];
        
        return safeFetch(
            getQualityConnection(),
            "SELECT 
                er.Semester AS value,
                z.ZamanName AS label 
             FROM EvaluationResponses er
             JOIN citcoder_Citgate.zaman z ON er.Semester = z.ZamanNo
             WHERE er.FormType = ?
               AND er.FormTarget = ?
               AND JSON_EXTRACT(er.Metadata, '$.course_id') = ?
             GROUP BY er.Semester
             ORDER BY er.Semester DESC",
            [$this->formType, $this->evaluatorType, $courseCode],
            'sss'
        ) ?? [];
    }

    /**
     * Fetch historical data
     */
    private function fetchHistoricalData() {
        if (!in_array('historical_trending', $this->config['statistics_modules'] ?? [])) {
            return ['labels' => [], 'averages' => []];
        }
        
        $historicalData = safeFetch(
            getQualityConnection(),
            "SELECT 
                Semester AS semester,
                COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(er.Metadata, '$.student_id'))) AS participants
             FROM EvaluationResponses AS er
             WHERE er.FormTarget = ?
               AND er.FormType = ?
             GROUP BY Semester
             ORDER BY Semester DESC",
            [$this->evaluatorType, $this->formType],
            'ss'
        ) ?? [];
        
        $semesterNames = [];
        $participationRates = [];
        
        foreach ($historicalData as $data) {
            $semesterInfo = safeFetch(
                getCITConnection(),
                "SELECT ZamanName FROM zaman WHERE ZamanNo = ?",
                [$data['semester']],
                'i'
            )[0] ?? ['ZamanName' => 'غير معروف'];
            
            $semesterStudents = safeFetch(
                getCITConnection(),
                "SELECT COUNT(DISTINCT KidNo) AS total
                 FROM tanzil 
                 WHERE ZamanNo = ?",
                [$data['semester']],
                'i'
            )[0]['total'] ?? 0;
            
            $rate = $semesterStudents > 0 
                ? round(($data['participants'] / $semesterStudents) * 100, 1)
                : 0;
            
            $semesterNames[] = $semesterInfo['ZamanName'];
            $participationRates[] = $rate;
        }
        
        return [
            'labels' => $semesterNames,
            'averages' => $participationRates
        ];
    }

    /**
     * Prepare view data for template
     */
    private function prepareViewData($entityData, $groupedQuestions, $stats, $history) {
        $semester = $this->filters['semester'] ?? null;
        
        // Get semester name
        $semesterInfo = safeFetch(
            getCITConnection(),
            "SELECT ZamanName FROM zaman WHERE ZamanNo = ?",
            [$semester],
            'i'
        )[0] ?? ['ZamanName' => 'غير معروف'];
        
        $viewData = [
            'program' => $this->config['page_title'] ?? '',
            'semester' => [
                'number' => $semester,
                'name' => $semesterInfo['ZamanName']
            ],
            'entity' => $entityData,
            'questions' => $groupedQuestions,
            'stats' => $stats,
            'history' => $history,
            'evaluator' => $this->evaluatorType,
            'evaluation_type' => $this->formType
        ];
        
        // Add course-specific data if needed
        if ($entityData && isset($this->config['custom_modules'])) {
            if (in_array('teacher_lookup', $this->config['custom_modules'])) {
                $viewData['course'] = array_merge(
                    $entityData,
                    ['teachers' => $stats['teachers'] ?? []]
                );
            }
            
            // Add available semesters for course filtering
            $viewData['availableSemesters'] = $this->fetchAvailableSemesters();
        }
        
        return $viewData;
    }

    /**
     * Include view template
     */
    private function includeView($viewData) {
        $viewTemplate = $this->config['view_template'] ?? 'program_evaluation.php';
        $viewPath = __DIR__ . '/../targets/views/' . $viewTemplate;
        
        if (!file_exists($viewPath)) {
            throw new RuntimeException("View template not found: $viewPath");
        }
        
        require_once $viewPath;
    }
}
