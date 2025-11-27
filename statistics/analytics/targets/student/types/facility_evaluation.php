<?php
require_once __DIR__ . '/../../../shared/auth.php';
require_once __DIR__ . '/../../../shared/database.php';
require_once __DIR__ . '/../../../shared/data_fetcher.php';


// 1. Input Validation & Sanitization
$semester = htmlspecialchars($_GET['semester'] ?? '', ENT_QUOTES, 'UTF-8');

// 2. Fetch Facility Evaluation Data
// ------------------------------------------------------------
// 2.1 Get semester name
$semesterInfo = safeFetch(
    getCITConnection(),
    "SELECT ZamanName FROM zaman WHERE ZamanNo = ?",
    [$semester],
    'i'
)[0] ?? ['ZamanName' => 'غير معروف'];

// 2.2 Get total enrolled students in the semester
$totalStudents = safeFetch(
    getCITConnection(),
    "SELECT COUNT(DISTINCT KidNo) AS total_students
     FROM tanzil 
     WHERE ZamanNo = ?",
    [$semester],
    'i'
)[0]['total_students'] ?? 0;

// 2.3 Get students who completed facility evaluations
$evaluatedStudents = safeFetch(
    getQualityConnection(),
    "SELECT DISTINCT 
        JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.student_id')) AS IDStudent
     FROM EvaluationResponses 
     WHERE FormType = 'facility_evaluation'
       AND FormTarget = 'student'
       AND Semester = ?",
    [$semester],
    'i'
);

// 3. Process Department Statistics
$departmentStats = [];
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

// 4. Calculate Participation Rate
$participationRate = $totalStudents > 0 
    ? round(($totalEvaluations / $totalStudents) * 100, 1)
    : 0;

// 5. Fetch Facility Evaluation Questions and Answers
$groupedQuestions = getGroupedQuestions(
    getQualityConnection(),
    'facility_evaluation',
    'student',
    ["er.Semester = ?"],
    [$semester],
    'i'
) ?? [];

// 6. Process Evaluation Questions
foreach ($groupedQuestions as $type => &$questions) {
    if ($type === 'evaluation') {
        foreach ($questions as &$question) {
            $rawDistribution = array_count_values(
                array_map(fn($a) => (int)round($a['value']), $question['Answers'] ?? [])
            );
            
            $question['distribution'] = array_replace(
                [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
                $rawDistribution
            );
        }
    }
}
unset($questions, $question);

// 7. Fetch Historical Data for Facility Evaluations
$historicalData = safeFetch(
    getQualityConnection(),
    "SELECT 
        Semester AS semester,
        COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(er.Metadata, '$.student_id'))) AS participants
     FROM EvaluationResponses AS er
     WHERE er.FormTarget = 'student'
       AND er.FormType = 'facility_evaluation'
     GROUP BY Semester
     ORDER BY Semester DESC",
    [],
    ''
) ?? []; 

// 8. Process Historical Data
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

// 9. Prepare View Data
$viewData = [
    'evaluator' => 'الطالب',
    'evaluation_type' => 'facility',
    'program' => 'تقييم المرافق',
    'semester' => [
        'number' => $semester,
        'name' => $semesterInfo['ZamanName']
    ],
    'questions' => $groupedQuestions,
    'stats' => [
        'total_students' => $totalStudents,
        'participants' => $totalEvaluations,
        'participation_rate' => $participationRate,
        'department_stats' => $departmentStats,
        'subscription' => [
            'current_month' => 0,
            'last_3_months' => 0,
            'last_6_months' => 0,
            'last_year' => 0
        ],
        'semester_subscriptions' => [] // Will be added below
    ],
    'history' => [
        'labels' => $semesterNames,
        'averages' => $participationRates
    ],
    'history_in_year' => []
];

// 10. Add Semester Subscriptions to Stats
$viewData['stats']['semester_subscriptions'] = array_map(function($data) {
    return [
        'number' => $data['semester'],
        'name' => $data['ZamanName'] ?? 'غير معروف',
        'total_students' => $data['total'] ?? 0,
        'participants' => $data['participants'] ?? 0,
        'participation_rate' => $data['rate'] ?? 0
    ];
}, $historicalData);

// Include view template
require_once __DIR__ . '/../../../targets/views/program_evaluation.php';