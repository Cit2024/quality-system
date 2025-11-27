<?php
require_once __DIR__ . '/../../../shared/auth.php';
require_once __DIR__ . '/../../../shared/database.php';
require_once __DIR__ . '/../../../shared/data_fetcher.php';


// 1. Input Validation & Sanitization
// ------------------------------------------------------------
$rawCourseId = trim($_GET['courseId'] ?? '');
$courseCode = htmlspecialchars(urldecode($rawCourseId), ENT_QUOTES, 'UTF-8');
$semester = htmlspecialchars($_GET['semester'] ?? '', ENT_QUOTES, 'UTF-8');

// 2. Fetch Core Course Data
// ------------------------------------------------------------
$courseData = safeFetch(
    getCITConnection(),
    "SELECT m.MadaName, m.MadaNo FROM mawad m WHERE m.MadaNo = ?",
    [$courseCode],  
    's' 
)[0] ?? null;

// 2.1 Get total enrolled students
$TotalStudents = safeFetch(
    getCITConnection(),
    "SELECT COUNT(DISTINCT t.KidNo) AS total_students
     FROM tanzil t 
     WHERE t.MadaNo = ? AND t.ZamanNo = ?",
    [$courseCode, $semester],
    'si'
)[0]['total_students'] ?? 0;

// 3. Fetch Available Semesters
// ------------------------------------------------------------
$availableSemesters = safeFetch(
    getQualityConnection(),
    "SELECT 
        er.Semester AS value,
        z.ZamanName AS label 
     FROM EvaluationResponses er
     JOIN citcoder_Citgate.zaman z ON er.Semester = z.ZamanNo
     WHERE er.FormType = 'course_evaluation'
       AND er.FormTarget = 'student'
       AND JSON_EXTRACT(er.Metadata, '$.course_id') = ?
     GROUP BY er.Semester
     ORDER BY er.Semester DESC",
    [$courseCode],
    's'
) ?? [];

// 4. Fetch Evaluation Responses
// ------------------------------------------------------------
// 4. Fetch Evaluation Responses
// ------------------------------------------------------------
$groupedQuestions = getGroupedQuestions(
    getQualityConnection(),
    'teacher_evaluation',
    'student',
    [
        "er.Semester = ?",
        "JSON_EXTRACT(er.Metadata, '$.course_id') = ?"
    ],
    [$semester, $courseCode],
    'is'
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

// 5. Calculate Participation Statistics
// ------------------------------------------------------------
$enrolledStudents = safeFetch(
    getCITConnection(),
    "SELECT DISTINCT KidNo FROM tanzil WHERE ZamanNo = ? AND MadaNo = ?",
    [$semester, $courseCode],
    'is'
);

$evaluatedStudents = safeFetch(
    getQualityConnection(),
    "SELECT DISTINCT JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.student_id')) AS IDStudent
     FROM EvaluationResponses 
     WHERE JSON_EXTRACT(Metadata, '$.course_id') = ?
     AND FormType = 'course_evaluation'
     AND FormTarget = 'student'
     AND Semester = ?",
    [$courseCode, $semester],
    'si'
);

$departmentStats = [];
$totalStudents = count($enrolledStudents);
$totalEvaluations = 0;

foreach ($evaluatedStudents as $student) {
    $kidNo = $student['IDStudent'];
    $studentInfo = safeFetch(
        getCITConnection(),
        "SELECT KesmNo FROM sprofiles WHERE KidNo = ?",
        [$kidNo],
        'i'
    )[0] ?? null;

    if ($studentInfo) {
        $kesmNo = $studentInfo['KesmNo'];
        $department = safeFetch(
            getCITConnection(),
            "SELECT dname FROM divitions WHERE KesmNo = ?",
            [$kesmNo],
            'i'
        )[0]['dname'] ?? 'غير معروف';

        $departmentStats[$department] = ($departmentStats[$department] ?? 0) + 1;
        $totalEvaluations++;
    }
}

$nonParticipants = $totalStudents - $totalEvaluations;

// 5.5 Collect participant IDs
$participantIds = [];
$groupNumbers = [];

foreach ($groupedQuestions as $questions) {
    foreach ($questions as $question) {
        foreach ($question['Answers'] as $answer) {
            if (isset($answer['metadata']['student_id'])) {
                $participantIds[] = $answer['metadata']['student_id'];
            }
            if (isset($answer['metadata']['group_id'])) {
                $groupNumbers[] = $answer['metadata']['group_id'];
            }
        }
    }
}

$participantIds = array_unique($participantIds);
$groupNumbers = array_unique($groupNumbers);

// 6. Fetch Teacher Information
// ------------------------------------------------------------
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

$teachers = array_unique($teachers, SORT_REGULAR);

// 7. Fetch Historical Data
// ------------------------------------------------------------
$historicalData = safeFetch(
    getQualityConnection(),
    "SELECT 
        Semester AS semester,
        AVG(JSON_EXTRACT(er.AnswerValue, '$.value')) AS average_rating
     FROM EvaluationResponses AS er
     WHERE er.FormTarget = 'student'
       AND JSON_UNQUOTE(JSON_EXTRACT(er.Metadata, '$.course_id')) = ?
       AND JSON_EXTRACT(er.AnswerValue, '$.type') = 'evaluation'
     GROUP BY Semester
     ORDER BY Semester DESC",
    [$courseCode],
    's'
);

// 8. Process Historical Data
// ------------------------------------------------------------
$semesterNames = [];
$averageRatings = [];
foreach ($historicalData as $data) {
    $semesterInfo = safeFetch(
        getCITConnection(),
        "SELECT ZamanName FROM zaman WHERE ZamanNo = ?",
        [$data['semester']]
    )[0] ?? ['ZamanName' => 'غير معروف'];
    
    $semesterNames[] = $semesterInfo['ZamanName'];
    $averageRatings[] = round($data['average_rating'], 1);
}

// 9. Fetch all semesters data
// ------------------------------------------------------------
$allSemestersData = [];
$allSemesters = safeFetch(
    getQualityConnection(),
    "SELECT DISTINCT Semester 
     FROM EvaluationResponses 
     WHERE FormType = 'course_evaluation'
       AND FormTarget = 'student'
       AND JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.course_id')) = ?",
    [$courseCode],
    's'
);

foreach ($allSemesters as $sem) {
    $semesterNo = $sem['Semester'];
    
    $semesterName = safeFetch(
        getCITConnection(),
        "SELECT ZamanName FROM zaman WHERE ZamanNo = ?",
        [$semesterNo],
        'i'
    )[0]['ZamanName'] ?? 'غير معروف';
    
    $participantsCount = safeFetch(
        getQualityConnection(),
        "SELECT COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.student_id'))) AS count
         FROM EvaluationResponses
         WHERE FormType = 'course_evaluation'
           AND FormTarget = 'student'
           AND Semester = ?
           AND JSON_EXTRACT(Metadata, '$.course_id') = ?",
        [$semesterNo, $courseCode],
        'is'
    )[0]['count'] ?? 0;
    
    $totalStudents = safeFetch(
        getCITConnection(),
        "SELECT COUNT(DISTINCT KidNo) AS count
         FROM tanzil 
         WHERE MadaNo = ? AND ZamanNo = ?",
        [$courseCode, $semesterNo],
        'si'
    )[0]['count'] ?? 0;

    $semesterQuestions = getGroupedQuestions(
        getQualityConnection(),
        'course_evaluation',
        'student',
        [
            "er.Semester = ?",
            "JSON_UNQUOTE(JSON_EXTRACT(er.Metadata, '$.course_id')) = ?"
        ],
        [$semesterNo, $courseCode],
        'is'
    );

    // Clean metadata for essay questions
    foreach ($semesterQuestions as $type => &$questions) {
        if ($type === 'essay') {
            foreach ($questions as &$question) {
                foreach ($question['Answers'] as &$answer) {
                    if (isset($answer['metadata']) && is_array($answer['metadata'])) {
                        $answer['metadata'] = array_filter(
                            $answer['metadata'], 
                            function($item) { return $item !== null; }
                        );
                    }
                }
            }
        }
    }

    // Process evaluation distributions
    if (isset($semesterQuestions['evaluation'])) {
        foreach ($semesterQuestions['evaluation'] as &$question) {
            $rawDistribution = array_count_values(
                array_map(fn($a) => (int)round($a['value']), $question['Answers'])
            );
            $question['distribution'] = array_replace(
                [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
                $rawDistribution
            );
        }
    }

    $allSemestersData[] = [
        'semester_number' => $semesterNo,
        'semester_name' => $semesterName,
        'participants' => $participantsCount,
        'total_students' => $totalStudents,
        'questions' => $semesterQuestions
    ];
}

// 10. Prepare View Data
// ------------------------------------------------------------
$viewData = [
    'course' => array_merge($courseData, ['teachers' => $teachers]),
    'questions' => $groupedQuestions,
    'stats' => [
        'participants' => count($participantIds),
        'total' => $TotalStudents ?? 0,
        'participation_rate' => $TotalStudents > 0 
            ? round((count($participantIds) / $TotalStudents) * 100, 1)
            : 0,
        'total_evaluations' => $totalEvaluations,
        'department_stats' => $departmentStats,
        'non_participants' => $nonParticipants
    ],
    'semester' => $semester,
    'history' => [
        'labels' => $semesterNames,
        'averages' => $averageRatings
    ],
    'availableSemesters' => $availableSemesters,
    'all_semesters' => $allSemestersData
];

// Include view template
require_once __DIR__ . '/../../../targets/views/course_evaluation.php';