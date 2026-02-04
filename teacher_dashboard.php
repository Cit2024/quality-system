<?php
// teacher_dashdoard.php
require_once __DIR__ . '/config/session.php';
require_once __DIR__ . '/config/constants.php';

// Include database connections
require_once 'config/dbConnectionCit.php';
require_once 'config/DbConnection.php';

$currentPage = 'dashboard';

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header("Location: login.php");
    exit();
}

$teacher_id = $_SESSION['teacher_id'];
$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';


// Get current semester information
$semesterQuery = "SELECT ZamanNo, ZamanName FROM zaman ORDER BY ZamanNo DESC LIMIT 1";
$semesterResult = mysqli_query($conn_cit, $semesterQuery);
$semester = mysqli_fetch_assoc($semesterResult);

// Get total students
$totalStudentsQuery = "
    SELECT COUNT(DISTINCT t.KidNo) AS total_students
    FROM tanzil t
    JOIN coursesgroups c ON t.ZamanNo = c.ZamanNo AND t.MadaNo = c.MadaNo
    WHERE c.TNo = ? AND t.ZamanNo = ?";
$totalStudentsData = fetchData($conn_cit, $totalStudentsQuery, [$teacher_id, $semester['ZamanNo']], 'ii');
$total_students = $totalStudentsData[0]['total_students'] ?? 0;

// FIXED: Get evaluated courses count
$teacherCoursesQuery = "
    SELECT DISTINCT c.MadaNo 
    FROM coursesgroups c 
    WHERE c.TNo = ? AND c.ZamanNo = ?";
$teacherCourses = fetchData($conn_cit, $teacherCoursesQuery, [$teacher_id, $semester['ZamanNo']], 'ii');

$coursesEvaluated = 0;
if (!empty($teacherCourses)) {
    $courseIds = array_column($teacherCourses, 'MadaNo');
    $placeholders = implode(',', array_fill(0, count($courseIds), '?'));
    
    $evalQuery = "
        SELECT COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.course_id'))) as count
        FROM EvaluationResponses 
        WHERE FormType = 'course_evaluation'
          AND Semester = ?
          AND JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.course_id')) IN ($placeholders)";
    
    $params = array_merge([$semester['ZamanNo']], $courseIds);
    $types = 's' . str_repeat('s', count($courseIds));
    
    $coursesEvaluatedData = fetchData($con, $evalQuery, $params, $types);
    $coursesEvaluated = $coursesEvaluatedData[0]['count'] ?? 0;
}

$studentsEvaluated = fetchData(
    $con,
    "SELECT COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.student_id'))) as count
     FROM EvaluationResponses 
     WHERE JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.teacher_id')) = ?
       AND FormType IN ('course_evaluation', 'teacher_evaluation')
       AND Semester = ?",
    [$teacher_id, $semester['ZamanNo']],
    'si'
)[0]['count'] ?? 0;

// Calculate participation ratio
$participation_ratio = $total_students > 0 ? round(($studentsEvaluated / $total_students) * 100, PERCENTAGE_PRECISION) : 0;

// Get average rating
$avgRatingData = fetchData(
    $con,
    "SELECT AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(AnswerValue, '$.value')) AS DECIMAL(10," . RATING_PRECISION . "))) as avg_rating
     FROM EvaluationResponses 
     WHERE JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.teacher_id')) = ?
       AND FormType = 'teacher_evaluation'
       AND Semester = ?",
    [$teacher_id, $semester['ZamanNo']],
    'si'
);
$average_rating = $avgRatingData[0]['avg_rating'] ? round($avgRatingData[0]['avg_rating'], 2) : 0;

// Get historical data
$historicalData = fetchData(
    $con,
    "SELECT 
        Semester,
        COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.student_id'))) as student_count,
        AVG(CAST(JSON_UNQUOTE(JSON_EXTRACT(AnswerValue, '$.value')) AS DECIMAL(10,2))) as avg_rating
     FROM EvaluationResponses 
     WHERE JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.teacher_id')) = ?
     GROUP BY Semester
     ORDER BY Semester DESC
     LIMIT 6",
    [$teacher_id],
    's'
);

$semesterNames = [];
$studentCounts = [];
$avgRatings = [];

// Fix historical data processing
foreach ($historicalData as $row) {
    $semNameData = fetchData($conn_cit, "SELECT ZamanName FROM zaman WHERE ZamanNo = ?", [$row['Semester']], 'i');
    $semesterNames[] = $semNameData[0]['ZamanName'] ?? 'Unknown';
    $studentCounts[] = $row['student_count'];
    $avgRatings[] = round($row['avg_rating'], 2);  // Fixed parentheses issue
}

// Statistics cards
$statisticsCards = [
    [
        "name" => "المقررات المُقيّمة",
        "statistics" => $coursesEvaluated,
        "icon" => "fa-solid fa-book-bookmark",
        "link" => "teacher_statistics.php?type=course_evaluation"
    ],
    [
        "name" => "نسبة مشاركة الطلاب",
        "statistics" => $participation_ratio . "%",
        "icon" => "fa-solid fa-graduation-cap",
        "link" => null
    ],
    [
        "name" => "الطلاب المُقيّمون",
        "statistics" => $studentsEvaluated,
        "icon" => "fa-solid fa-users",
        "link" => null
    ],
    [
        "name" => "متوسط التقييم",
        "statistics" => $average_rating . "/5",
        "icon" => "fa-solid fa-star",
        "link" => "teacher_statistics.php?type=teacher_evaluation"
    ]
];

// Include header
include './components/header.php';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم المدرس</title>
</head>

<body>
    <div class="main">
        <div class="top-bar">
            <div class="toggle-button">
                <i class="fa-solid fa-bars"></i>
            </div>
            <div class="semester-info">
                <span><?php echo htmlspecialchars($semester['ZamanName']); ?></span>
            </div>
            <div class="user-info">
                <div class="user-profile">
                    <i class="fa-solid fa-circle-user" aria-label="user"></i>
                </div>
                <span><?php echo htmlspecialchars($teacher_name); ?></span>
            </div>
        </div>

        <div class="statistics-cards">
            <?php foreach ($statisticsCards as $card): ?>
                <div class="card" <?= $card['link'] ? 'onclick="location.href=\'' . $card['link'] . '\'"' : '' ?>>
                    <div class="card-content">
                        <div class="numbers"><?= $card['statistics'] ?></div>
                        <div class="card-name"><?= $card['name'] ?></div>
                    </div>
                    <div class="icon-box">
                        <i class="<?= $card['icon'] ?>"></i>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="charts-section">
            <div class="chart">
                <div class="chart-header">
                    <div class="chart-title">مشاركة الطلاب</div>
                </div>
                <canvas id="participation-chart"></canvas>
            </div>

            <div class="chart">
                <div class="chart-header">
                    <div class="chart-title">التقييمات التاريخية</div>
                    <div class="chart-selection auto-r">
                        <select id="chart-type">
                            <option value="line">خطي</option>
                            <option value="bar">شريطي</option>
                        </select>
                        <select id="data-type">
                            <option value="students">عدد الطلاب</option>
                            <option value="ratings">متوسط التقييم</option>
                        </select>
                    </div>
                </div>
                <canvas id="history-chart"></canvas>
            </div>
        </div>
    </div>

    <?php include './components/footer.php'; ?>

    <script src="./scripts/teacher_dashboard.js"></script>
    <script>
        // Pass data to chart initialization
        const participationData = [<?= $studentsEvaluated ?>, <?= $total_students - $studentsEvaluated ?>];
        const semesterNames = <?= json_encode($semesterNames) ?>;
        const studentCounts = <?= json_encode($studentCounts) ?>;
        const avgRatings = <?= json_encode($avgRatings) ?>;

        initializeCharts(participationData, semesterNames, studentCounts, avgRatings);
    </script>

</body>

</html>