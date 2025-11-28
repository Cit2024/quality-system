<?php
// pages/dashboard.php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$currentPage = 'dashboard';

require_once __DIR__ . '/forms/form_constants.php';

// // Get semester data for header
include './components/header.php';

require_once 'config/dbConnectionCit.php';
require_once 'config/DbConnection.php';

require_once 'helpers/database.php';

// Get evaluation counts per form type
$formTypeCounts = fetchData($con, 
    "SELECT FormType, COUNT(ID) AS count 
     FROM EvaluationResponses 
     GROUP BY FormType");

// Create form type statistics cards
$formStatisticsCards = [];
foreach (FORM_TYPES as $typeKey => $formType) {
    $count = 0;
    foreach ($formTypeCounts as $ftc) {
        if ($ftc['FormType'] === $typeKey) {
            $count = $ftc['count'];
            break;
        }
    }
    
    $formStatisticsCards[] = [
        "name" => $formType['name'],
        "statistics" => $count,
        "icon" => str_replace('./assets/icons/', '', $formType['icon']),
        "link" => "./statistics.php?form_type=".$typeKey
    ];
}

// Get enhanced statistics data
$stats = fetchData($con, "SELECT COUNT(ID) AS total_forms FROM Form")[0] ?? ['total_forms' => 0];

// Get published vs draft forms count
$published_forms = fetchData($con, "SELECT COUNT(ID) AS count FROM Form WHERE FormStatus = 'published'")[0]['count'] ?? 0;
$draft_forms = fetchData($con, "SELECT COUNT(ID) AS count FROM Form WHERE FormStatus = 'draft'")[0]['count'] ?? 0;

// Get forms with at least one participation
$forms_with_participation = fetchData($con, "
    SELECT COUNT(DISTINCT CONCAT(f.FormType, '-', f.FormTarget)) AS count
    FROM Form f
    INNER JOIN EvaluationResponses er ON 
        er.FormType = f.FormType AND 
        er.FormTarget = f.FormTarget
    WHERE f.FormStatus = 'published'
")[0]['count'] ?? 0;

// Calculate participation rate for published forms
$participation_rate = $published_forms > 0 ? round(($forms_with_participation / $published_forms) * 100, 2) : 0;

// Get form-specific statistics
$formStatistics = fetchData($con, "
    SELECT 
        f.ID,
        f.Title,
        f.FormStatus,
        f.FormType,
        f.FormTarget,
        COUNT(DISTINCT er.ID) as response_count,
        MAX(er.AnsweredAt) as last_response  
    FROM Form f
    LEFT JOIN EvaluationResponses er ON 
        er.FormType = f.FormType AND 
        er.FormTarget = f.FormTarget AND
        er.Semester = ?
    GROUP BY f.ID, f.Title, f.FormStatus, f.FormType, f.FormTarget
    ORDER BY f.ID DESC
    LIMIT 10
", [$semester['ZamanNo']]);

// Get student participation data
$total_students = fetchData($conn_cit, 
    "SELECT COUNT(DISTINCT KidNo) AS NumberOfStudents 
     FROM tanzil 
     WHERE ZamanNo = ?", [$semester['ZamanNo']])[0]['NumberOfStudents'] ?? 0;

$participated_students = fetchData($con, 
    "SELECT COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.student_id'))) AS NumberOfStudents 
     FROM EvaluationResponses 
     WHERE FormTarget = 'student' 
       AND Semester = ?", [$semester['ZamanNo']])[0]['NumberOfStudents'] ?? 0;
       

$non_participated_students = $total_students - $participated_students;

// Calculate participation ratio
$participation_ratio_percentage = $total_students > 0 ? round(($participated_students / $total_students) * 100, 2) : 0;

// Get teacher and course evaluation counts
$teacher_evaluated = fetchData($con, 
    "SELECT COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.student_id'))) AS NumberOfTeacher 
     FROM EvaluationResponses 
     WHERE FormType = 'teacher_evaluation' 
     AND FormTarget = 'student'
       AND Semester = ?", [$semester['ZamanNo']])[0]['NumberOfTeacher'] ?? 0;
       
// Course evaluations count
$course_evaluated = fetchData($con, 
    "SELECT COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.student_id'))) AS NumberOfCourses 
     FROM EvaluationResponses 
     WHERE FormType = 'course_evaluation' 
     AND FormTarget = 'student'
       AND Semester = ?", [$semester['ZamanNo']])[0]['NumberOfCourses'] ?? 0;
       
// Get semester participation history
$semesterData = fetchData($con, 
    "SELECT 
         er.Semester,
         COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(er.Metadata, '$.student_id'))) AS student_count
     FROM EvaluationResponses er
     WHERE er.FormType IN ('course_evaluation', 'teacher_evaluation')
     AND er.FormTarget = 'student'
     GROUP BY er.Semester 
     ORDER BY er.Semester DESC");

// Get semester names
$semesterNames = [];
$studentCounts = [];
foreach ($semesterData as $Semester) {
    $semesterName = fetchData($conn_cit, "SELECT ZamanName FROM zaman WHERE ZamanNo = ?", [$Semester['Semester']])[0]['ZamanName'] ?? 'Unknown';
    $semesterNames[] = $semesterName;
    $studentCounts[] = $Semester['student_count'];
}

// Get teacher ratings data
$teacherRatingsQuery = "SELECT Semester, AVG(JSON_UNQUOTE(JSON_EXTRACT(AnswerValue, '$.value'))) AS avg_rating 
                       FROM EvaluationResponses 
                       WHERE FormType = 'teacher_evaluation' 
                       GROUP BY Semester 
                       ORDER BY Semester DESC";
                       
$teacherRatingsData = fetchData($con, $teacherRatingsQuery) ?: [];
$teacherRatings = array_column($teacherRatingsData, 'avg_rating');

// Get course ratings data
$courseRatingsQuery = "SELECT Semester, AVG(JSON_UNQUOTE(JSON_EXTRACT(AnswerValue, '$.value'))) AS avg_rating 
                      FROM EvaluationResponses 
                      WHERE FormType = 'course_evaluation' 
                      GROUP BY Semester 
                      ORDER BY Semester DESC";
                      
$courseRatingsData = fetchData($con, $courseRatingsQuery) ?: [];
$courseRatings = array_column($courseRatingsData, 'avg_rating');

$statisticsCards = [
    [
        "name" => "النماذج",
        "statistics" => $stats['total_forms'],
        "icon" => "fa-solid fa-rectangle-list",
        "link" => "./forms.php"
    ],
    [
        "name" => "نسبة الطالب المشاركة في التقييم",
        "statistics" => $participation_ratio_percentage . " %",
        "icon" => "fa-solid fa-graduation-cap",
        "link" => null // No link for this card
    ],
    [
        "name" => "الأساتذة الذين يتم تقييمهم",
        "statistics" => $teacher_evaluated,
        "icon" => "fa-solid fa-person-chalkboard",
        "link" => "./statistics.php?tab=teachers"
    ],
    [
        "name" => "المقررات التي تم تقييمها",
        "statistics" => $course_evaluated,
        "icon" => "fa-solid fa-book-bookmark",
        "link" => "./statistics.php?tab=courses"
    ],
];

// Enhanced statistics cards for detailed view
$enhancedStatisticsCards = [
    [
        "name" => "النماذج المنشورة",
        "statistics" => $published_forms,
        "icon" => "fa-solid fa-check-circle",
        "color" => "#28a745",
        "link" => "./forms.php?filter=published"
    ],
    [
        "name" => "النماذج المسودة",
        "statistics" => $draft_forms,
        "icon" => "fa-solid fa-file-alt",
        "color" => "#ffc107",
        "link" => "./forms.php?filter=draft"
    ],
    [
        "name" => "النماذج مع مشاركة",
        "statistics" => $forms_with_participation,
        "icon" => "fa-solid fa-users",
        "color" => "#17a2b8",
        "link" => null
    ],
    [
        "name" => "معدل المشاركة",
        "statistics" => $participation_rate . " %",
        "icon" => "fa-solid fa-chart-line",
        "color" => "#d97757",
        "link" => null
    ],
];

?>


    <div class="main">
        <div class="top-bar">
            <div class="toggle-button">
                <i class="fa-solid fa-bars"></i>
            </div>
            <div class="semester-info">
                <span><?php echo htmlspecialchars($semester['ZamanName']); ?></span>
            </div>
            <div class="user-profile">
                <img src="./assets/icons/circle-user-round.svg" alt="user">
            </div>
        </div>

        <div class="statistics-cards">
            <?php foreach ($statisticsCards as $card): ?>
                <div class="card" <?php if ($card['link']): ?>onclick="window.location.href='<?php echo htmlspecialchars($card['link']); ?>'"<?php endif; ?>>
                    <div>
                        <div class="numbers"><?php echo htmlspecialchars($card['statistics']); ?></div>
                        <div class="card-name"><?php echo htmlspecialchars($card['name']); ?></div>
                    </div>
                    <div class="icon-box">
                        <?php if (str_contains($card['icon'], 'svg')): ?>
                            <img src="./assets/icons/<?php echo htmlspecialchars($card['icon']); ?>" alt="icon">
                        <?php else: ?>
                            <i class="<?php echo htmlspecialchars($card['icon']); ?>"></i>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Enhanced Statistics Section -->
        <div class="enhanced-statistics-section">
            <div class="section-header">
                <h2>تفاصيل النماذج</h2>
            </div>
            <div class="statistics-cards">
                <?php foreach ($enhancedStatisticsCards as $card): ?>
                    <div class="enhanced-card" 
                         <?php if ($card['link']): ?>onclick="window.location.href='<?php echo htmlspecialchars($card['link']); ?>'" style="cursor: pointer;"<?php endif; ?>>
                        <div class="card-content">
                            <div class="numbers" style="color: <?php echo htmlspecialchars($card['color']); ?>;">
                                <?php echo htmlspecialchars($card['statistics']); ?>
                            </div>
                            <div class="card-name">
                                <?php echo htmlspecialchars($card['name']); ?>
                            </div>
                        </div>
                        <div class="icon-box" style="background: <?php echo htmlspecialchars($card['color']); ?>20;">
                            <i class="<?php echo htmlspecialchars($card['icon']); ?>" style="color: <?php echo htmlspecialchars($card['color']); ?>;"></i>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Forms Statistics Section -->
        <div class="forms-statistics-section">
            <div class="section-header">
                <h2>إحصائيات النماذج</h2>
                <a href="./forms.php" class="view-all-link">عرض الكل <i class="fa-solid fa-arrow-left"></i></a>
            </div>
            <div class="forms-table-container">
                <table class="forms-statistics-table">
                    <thead>
                        <tr>
                            <th>النموذج</th>
                            <th>النوع</th>
                            <th>المُقيِّم</th>
                            <th>الحالة</th>
                            <th>عدد الردود</th>
                            <th>آخر رد</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($formStatistics)): ?>
                            <?php foreach ($formStatistics as $formStat): ?>
                                <tr onclick="window.location.href='./forms/edit-form.php?id=<?php echo $formStat['ID']; ?>'" style="cursor: pointer;">
                                    <td>
                                        <span class="form-title-cell">
                                            #<?php echo $formStat['ID']; ?> - <?php echo htmlspecialchars(mb_substr($formStat['Title'], 0, 30)); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="type-badge">
                                            <?php echo FORM_TYPES[$formStat['FormType']]['name'] ?? 'غير محدد'; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="target-badge">
                                            <?php echo FORM_TARGETS[$formStat['FormTarget']]['name'] ?? 'غير محدد'; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $formStat['FormStatus'] === 'published' ? 'status-published' : 'status-draft'; ?>">
                                            <?php echo $formStat['FormStatus'] === 'published' ? 'منشور' : 'مسودة'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="response-count <?php echo $formStat['response_count'] > 0 ? 'has-responses' : 'no-responses'; ?>">
                                            <?php echo $formStat['response_count']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($formStat['last_response']) {
                                            $date = new DateTime($formStat['last_response']);
                                            echo $date->format('Y-m-d H:i');
                                        } else {
                                            echo '<span class="no-data">لا توجد ردود</span>';
                                        }
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="no-data-cell">لا توجد نماذج</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="charts-section">
            <div 
                class="chart pie-chart" 
                data-participated-students="<?php echo (int)$participated_students; ?>"
                data-non-participated-students="<?php echo (int)$non_participated_students; ?>"
            >
                <canvas id="student-participation"></canvas>
            </div>

            <div class="chart line-chart" 
                data-semester-names='<?php echo json_encode($semesterNames); ?>'
                data-student-counts='<?php echo json_encode($studentCounts); ?>'
                data-teacher-ratings='<?php echo json_encode($teacherRatings); ?>'
                data-course-ratings='<?php echo json_encode($courseRatings); ?>'>
                
                <div class="chart-selection">
                    <select id="chart-type" style="font-family: 'DINRegular', sans-serif;">
                        <option value="line">الرسم البياني الخطي</option>
                        <option value="bar">شريط الرسم البياني</option>
                    </select>
                    
                    <select id="statistics-about" style="font-family: 'DINRegular', sans-serif;">
                        <option value='number-students'>عدد الطلبة المقيمين</option>
                        <option value='average-teacher-ratings'>متوسط تقييمات المدرسين</option>
                        <option value='average-course-ratings'>متوسط تقييمات المقررات</option>
                    </select>
                </div>
                            
                <canvas id="average-quarterly-ratings"></canvas>
            </div>
        </div>
    </div>
    
    <script src="./scripts/dashbord.js"></script>
    <?php include './components/footer.php'; ?>