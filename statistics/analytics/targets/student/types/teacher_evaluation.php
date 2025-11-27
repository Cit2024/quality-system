<?php
// statistics/analytics/targets/student/types/teacher_evaluation.php

require_once __DIR__ . '/../../../shared/auth.php';
require_once __DIR__ . '/../../../shared/database.php';
require_once __DIR__ . '/../../../shared/data_fetcher.php';

// 1. Input Validation & Sanitization
// ------------------------------------------------------------
$teacherId = (int)($_GET['teacher_id'] ?? 0);
$semester = htmlspecialchars($_GET['semester'] ?? '', ENT_QUOTES, 'UTF-8');
$courseCode = htmlspecialchars($_GET['course_code'] ?? '', ENT_QUOTES, 'UTF-8');

// 2. Fetch Core Teacher Data
// ------------------------------------------------------------
$teacherData = safeFetch(
    getCITConnection(),
    "SELECT id, name FROM regteacher WHERE id = ?",
    [$teacherId],
    'i'
)[0] ?? null;

if (empty($teacherData)) {
    header("HTTP/1.1 404 Not Found");
    die("Teacher not found");
}

// Add default photo path
$teacherData['photo'] = '../assets/icons/circle-user-round.svg';


// 3. Fetch Available Semesters
// ------------------------------------------------------------
$availableSemesters = safeFetch(
    getQualityConnection(),
    "SELECT 
        er.Semester AS value,
        z.ZamanName AS label 
     FROM EvaluationResponses er
     JOIN citcoder_Citgate.zaman z ON er.Semester = z.ZamanNo
     WHERE FormType = 'teacher_evaluation'
     AND FormTarget = 'student'
       AND JSON_EXTRACT(Metadata, '$.teacher_id') = ?
       AND JSON_EXTRACT(Metadata, '$.course_id') = ?
     GROUP BY er.Semester
     ORDER BY er.Semester DESC",
    [$teacherId, $courseCode],
    'is'
) ?? [];


// 4. Fetch Evaluation Responses
// ------------------------------------------------------------
$groupedQuestions = getGroupedQuestions(
    getQualityConnection(),
    'teacher_evaluation',
    'student',
    [
        "er.Semester = ?",
        "JSON_EXTRACT(er.Metadata, '$.teacher_id') = ?",
        "JSON_EXTRACT(er.Metadata, '$.course_id') = ?"
    ],
    [$semester, $teacherId, $courseCode],
    'sis'
);

// Normalize rating distributions
if (isset($groupedQuestions['evaluation'])) {
    foreach ($groupedQuestions['evaluation'] as &$question) {
        $rawDistribution = array_count_values(
            array_map(fn($a) => (int)round($a['value']), $question['Answers'])
        );
        
        $question['distribution'] = array_replace(
            [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            $rawDistribution
        );
        
        ksort($question['distribution']);
    }
    unset($question);
}


// 5. Participation Statistics
// ------------------------------------------------------------
// 5.1 Get courses taught
$coursesTaught = safeFetch(
    getCITConnection(),
    "SELECT DISTINCT c.MadaNo, m.MadaName
     FROM coursesgroups c
     JOIN mawad m ON c.MadaNo = m.MadaNo
     WHERE c.TNo = ? AND c.ZamanNo = ?",
    [$teacherId, $semester],
    'ii'
) ?? [];

// 5.2 Get evaluation counts
$courseEvaluations = [];
foreach ($coursesTaught as $course) {
    $evalCount = safeFetch(
        getQualityConnection(),
        "SELECT COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.student_id'))) AS count
         FROM EvaluationResponses
         WHERE FormType = 'teacher_evaluation'
         AND FormTarget = 'student'
           AND Semester = ?
           AND JSON_EXTRACT(Metadata, '$.teacher_id') = ?
           AND JSON_EXTRACT(Metadata, '$.course_id') = ?",
        [$semester, $teacherId, $course['MadaNo']],
        'sis'
    )[0]['count'] ?? 0;
    
    $courseEvaluations[$course['MadaNo']] = $evalCount;
}

// 5.3 Filter courses to only those with evaluations
$coursesTaught = array_filter($coursesTaught, function($course) use ($courseEvaluations) {
    return ($courseEvaluations[$course['MadaNo']] ?? 0) > 0;
});

// Reset array keys to maintain proper JSON encoding
$coursesTaught = array_values($coursesTaught);

// 6. Historical Data
// ------------------------------------------------------------
$historicalData = safeFetch(
    getQualityConnection(),
    "SELECT Semester, 
            AVG(JSON_EXTRACT(AnswerValue, '$.value')) AS avg_rating,
            COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.student_id'))) AS total_students
     FROM EvaluationResponses
     WHERE FormType = 'teacher_evaluation'
     AND FormTarget = 'student'
       AND JSON_EXTRACT(Metadata, '$.teacher_id') = ?
     GROUP BY Semester
     ORDER BY Semester DESC
     LIMIT 5",
    [$teacherId],
    'i'
) ?? [];


// Add semester names
foreach ($historicalData as &$data) {
    $semesterInfo = safeFetch(
        getCITConnection(),
        "SELECT ZamanName FROM zaman WHERE ZamanNo = ?",
        [$data['Semester']],
        'i'
    )[0] ?? ['ZamanName' => 'غير معروف'];
    
    $data['SemesterName'] = $semesterInfo['ZamanName'];
}

// 7. Evaluation Participants & Stats
// ------------------------------------------------------------
$evaluatedStudents = safeFetch(
    getQualityConnection(),
    "SELECT DISTINCT 
        JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.student_id')) AS student_id
     FROM EvaluationResponses 
     WHERE FormType = 'teacher_evaluation'
     AND FormTarget = 'student'
       AND JSON_EXTRACT(Metadata, '$.teacher_id') = ?
       AND JSON_EXTRACT(Metadata, '$.course_id') = ?
       AND Semester = ?",
    [$teacherId, $courseCode, $semester],
    'isi'
) ?? [];

$totalStudents = safeFetch(
    getCITConnection(),
    "SELECT COUNT(DISTINCT t.KidNo) AS total
     FROM citcoder_Citgate.tanzil t
     JOIN (
         SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(er.Metadata, '$.group_id')) AS group_id
         FROM citcoder_Quality.EvaluationResponses er
         WHERE er.FormType = 'teacher_evaluation'
         AND er.FormTarget = 'student'
           AND er.Semester = ?
           AND JSON_EXTRACT(er.Metadata, '$.teacher_id') = ?
           AND JSON_EXTRACT(er.Metadata, '$.course_id') = ?
     ) groups ON t.Gr1 = groups.group_id
     WHERE t.ZamanNo = ? AND t.MadaNo = ?",
    [$semester, $teacherId, $courseCode, $semester, $courseCode],
    'iisis'
)[0]['total'] ?? 0;

// Process department stats
$departmentStats = [];
foreach ($evaluatedStudents as $student) {
    $studentInfo = safeFetch(
        getCITConnection(),
        "SELECT d.dname 
         FROM sprofiles s
         JOIN divitions d ON s.KesmNo = d.KesmNo
         WHERE s.KidNo = ?",
        [$student['student_id']], // Corrected key
        'i'
    )[0] ?? ['dname' => 'غير معروف'];
    
    $departmentStats[$studentInfo['dname']] = 
        ($departmentStats[$studentInfo['dname']] ?? 0) + 1;
}

// Final calculations
$totalEvaluations = count($evaluatedStudents);
$nonParticipants = max($totalStudents - $totalEvaluations, 0);

// 8. Get all course assessments In the current semester
// ------------------------------------------------------------
$allCourseAssessments = [];

foreach ($coursesTaught as $course) {
    // Fetch questions for each course
    $courseQuestions = getGroupedQuestions(
        getQualityConnection(),
        'teacher_evaluation',
        'student',
        [
            "er.Semester = ?",
            "JSON_EXTRACT(er.Metadata, '$.teacher_id') = ?", 
            "JSON_EXTRACT(er.Metadata, '$.course_id') = ?"
        ],
        [$semester, $teacherId, $course['MadaNo']],
        'sis'
    );

    // Normalize rating distributions for evaluation questions
    if (isset($courseQuestions['evaluation'])) {
        foreach ($courseQuestions['evaluation'] as &$question) {
            $rawDistribution = array_count_values(
                array_map(fn($a) => (int)round($a['value']), $question['Answers'])
            );
            
            $question['distribution'] = array_replace(
                [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
                $rawDistribution
            );
            
            ksort($question['distribution']);
        }
        unset($question);
    }

    // Decode metadata for essay responses
    if (isset($courseQuestions['essay'])) {
        foreach ($courseQuestions['essay'] as &$essayQuestion) {
            $essayQuestion['responses'] = array_map(function($response) {
                $metadata = $response['metadata'] ?? [];
                if (is_string($metadata)) {
                    $metadata = json_decode($metadata, true) ?: [];
                }
                return [
                    'content' => $response['value'] ?? '',
                    'timestamp' => $response['timestamp'] ?? '',
                    'metadata' => $metadata
                ];
            }, $essayQuestion['Answers']);
        }
        unset($essayQuestion);
    }

    $allCourseAssessments[] = [
        'id' => $course['MadaNo'],
        'name' => $course['MadaName'],
        'questions' => $courseQuestions,
        'evaluation_count' => $courseEvaluations[$course['MadaNo']] ?? 0
    ];
}

// 9. Get all courses across all semesters
// ------------------------------------------------------------
$all_semester = [];

// Get all semesters with evaluations
$semesters = safeFetch(
    getQualityConnection(),
    "SELECT DISTINCT 
        er.Semester AS semester_number,
        z.ZamanName AS semester_name
     FROM EvaluationResponses er
     JOIN citcoder_Citgate.zaman z ON er.Semester = z.ZamanNo
     WHERE er.FormType = 'teacher_evaluation'
     AND er.FormTarget = 'student'
     ORDER BY er.Semester DESC",
    [],
    ''
) ?? [];

foreach ($semesters as $sem) {
    // Get courses with evaluations in this semester
    $courses = safeFetch(
        getQualityConnection(),
        "SELECT DISTINCT
            m.MadaNo AS course_code,
            m.MadaName AS course_name
         FROM EvaluationResponses er
         JOIN citcoder_Citgate.mawad m 
            ON JSON_UNQUOTE(JSON_EXTRACT(er.Metadata, '$.course_id')) = m.MadaNo
         WHERE er.Semester = ?
           AND er.FormType = 'teacher_evaluation'
           AND er.FormTarget = 'student'
           AND JSON_UNQUOTE(JSON_EXTRACT(er.Metadata, '$.teacher_id')) = ?
         GROUP BY m.MadaNo",
        [$sem['semester_number'], $teacherId],
        'ii'
    ) ?? [];

    $processedCourses = [];
    foreach ($courses as $course) {
        // Get questions for this course-semester combination
        $questions = getGroupedQuestions(
            getQualityConnection(),
            'teacher_evaluation',
            'student',
            [
                "er.Semester = ?",
                "JSON_EXTRACT(er.Metadata, '$.course_id') = ?",
                "JSON_EXTRACT(er.Metadata, '$.teacher_id') = ?"
            ],
            [$sem['semester_number'], $course['course_code'], $teacherId],
            'isi'
        );

        // Normalize rating distributions
        if (isset($questions['evaluation'])) {
            foreach ($questions['evaluation'] as &$question) {
                $rawDistribution = array_count_values(
                    array_map(fn($a) => (int)round($a['value']), $question['Answers'])
                );
                
                $question['distribution'] = array_replace(
                    [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
                    $rawDistribution
                );
                
                ksort($question['distribution']);
            }
            unset($question);
        }
        
        // Ensure metadata is properly decoded
        if (isset($courseQuestions['essay'])) {
            foreach ($courseQuestions['essay'] as &$essayQuestion) {
                $essayQuestion['responses'] = array_map(function($response) {
                    $metadata = $response['metadata'] ?? [];
                    if (is_string($metadata)) {
                        $metadata = json_decode($metadata, true) ?: [];
                    }
                    return [
                        'content' => $response['value'] ?? '',
                        'timestamp' => $response['timestamp'] ?? '',
                        'metadata' => $metadata
                    ];
                }, $essayQuestion['Answers']);
            }
            unset($essayQuestion);
        }
        
        // 9.2 Get evaluation participants count for this semester
        $participantsCount = safeFetch(
            getQualityConnection(),
            "SELECT COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.student_id'))) AS count
             FROM EvaluationResponses
             WHERE FormType = 'teacher_evaluation'
               AND FormTarget = 'student'
               AND Semester = ?
               AND JSON_EXTRACT(Metadata, '$.course_id') = ?
               AND JSON_EXTRACT(Metadata, '$.teacher_id') = ?",
            [
                $sem['semester_number'],
                $course['course_code'], 
                $teacherId
            ],
            'isi'
        )[0]['count'] ?? 0;
        
        // 9.3 Get total enrolled students for this semester
        $totalStudents = safeFetch(
            getCITConnection(),
            "SELECT COUNT(DISTINCT KidNo) AS count
             FROM tanzil 
             WHERE MadaNo = ? AND ZamanNo = ?",
            [
                $course['course_code'], 
                $sem['semester_number']
            ],
            'si'
        )[0]['count'] ?? 0;

        $processedCourses[] = [
            'course_name' => $course['course_name'],
            'course_code' => $course['course_code'],
            'participants' => $participantsCount,
            'total_students' => $totalStudents,
            'questions' => $questions
        ];
    }

    $all_semester[] = [
        'semester_name' => $sem['semester_name'],
        'semester_Number' => $sem['semester_number'],
        'number_courses_semester' => count($courses),
        'courses' => $processedCourses
    ];
}


// 10. Prepare View Data
// ------------------------------------------------------------
$viewData = [
    'teacher' => array_merge($teacherData, ['courses' => array_map(fn($c) => [
        'id' => $c['MadaNo'],
        'name' => $c['MadaName'],
        'evaluations' => $courseEvaluations[$c['MadaNo']] ?? 0
    ], $coursesTaught)]),
    
    'questions' => $groupedQuestions,
    
    'stats' => [
        'total_evaluations' => $totalEvaluations,
        'average_rating' => safeFetch(
            getQualityConnection(),
            "SELECT AVG(JSON_EXTRACT(AnswerValue, '$.value')) AS avg
             FROM EvaluationResponses
             WHERE FormType = 'teacher_evaluation'
               AND JSON_EXTRACT(Metadata, '$.teacher_id') = ?",
            [$teacherId],
            'i'
        )[0]['avg'] ?? 0,
        'participants' => $totalEvaluations,
        'total' => $totalStudents,
        'participation_rate' => $totalStudents > 0 
            ? round(($totalEvaluations / $totalStudents) * 100, 1)
            : 0,
        'department_stats' => $departmentStats,
        'non_participants' => $nonParticipants
    ],
    'history' => $historicalData,
    'availableSemesters' => $availableSemesters,
    'selected' => [
        'semester' => $semester,
        'course' => $courseCode
    ],
    'all_courses' => $allCourseAssessments,
    'all_semesters' => $all_semester
];

include __DIR__.'/../../views/teacher_evaluation.php';