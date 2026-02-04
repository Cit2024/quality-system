<?php
// teacher_statistics.php
require_once __DIR__ . '/config/session.php';
$currentPage = 'statistics';

// Check if teacher is logged in
if (!isset($_SESSION['teacher_id'])) {
    header("Location: login.php");
    exit();
}

include 'components/header.php';


// Include database connections
require_once 'config/dbConnectionCit.php';
require_once 'config/DbConnection.php';
include 'helpers/database.php';
require_once 'helpers/FormTypes.php';

$formTypes = FormTypes::getFormTypes($con);
$formTargets = FormTypes::getFormTargets($con);


$teacher_id = $_SESSION['teacher_id'];
$teacher_name = $_SESSION['teacher_name'] ?? 'Teacher';

// Create filtered form types array
$FILTERED_FORM_TYPES = [
    'course_evaluation' => $formTypes['course_evaluation'],
    'teacher_evaluation' => $formTypes['teacher_evaluation']
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة تحكم المدرس</title>
    <link rel="stylesheet" href="styles/global.css">
    <link rel="stylesheet" href="styles/statistics.css">
</head>

<body>
    <div class="main">
        <div class="top-bar">
            <div class="toggle-button">
                <i class="fa-solid fa-bars"></i>
            </div>
            <div class="semester-info">
                <span><?= htmlspecialchars($semester['ZamanName'] ?? 'الفصل الحالي') ?></span>
            </div>
            <div class="user-info">
                <div class="user-profile">
                    <i class="fa-solid fa-circle-user" aria-label="user"></i>
                </div>
                <span><?php echo htmlspecialchars($teacher_name); ?></span>
            </div>
        </div>

        <!-- ==================== Statistics Container ==================== -->
        <div class="container-statistics">
            <!-- Search and Filter -->
            <div class="search-container">
                <input type="text" class="search-input" placeholder="بحث...">
                <select class="filter-select">
                    <option value="all">الكل</option>
                    <!-- Dynamically populated -->
                </select>
            </div>

            <!-- Tab Content for Teacher -->
            <div class="tab-content active" id="teacher-tab">
                <!-- Sub Tabs -->
                <div class="sub-tabs-wrapper">
                    <div class="sub-tabs">
                        <?php foreach ($FILTERED_FORM_TYPES as $type => $typeData): ?>
                            <button class="sub-tab-button" data-type="<?= $type ?>">
                                <i class="<?= $typeData['icon'] ?> sub-tab-icon"></i>
                                <?= $typeData['name'] ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Cards Container -->
                <div class="cards-container">
                    <!-- AJAX Content Loaded Here -->
                </div>

                <!-- No Results Message -->
                <div class="no-results-message" style="display: none;">
                    <i class="fa-solid fa-inbox" aria-label="No results" style="font-size: 50px; color: #ccc;"></i>
                    <p>لا توجد عناصر مطابقة لـ "<span class="search-term"></span>"</p>
                </div>
            </div>
        </div>
    </div>
    <?php include './components/footer.php'; ?>
    </div>

    <!-- ==================== JavaScript ==================== -->
    <script>
        window.FORM_TYPES = <?= json_encode($formTypes) ?>;
        window.FORM_TARGETS = <?= json_encode($formTargets) ?>;
        window.TEACHER_ID = <?= json_encode($teacher_id) ?>;
    </script>
    <script src="./scripts/lib/utils.js"></script>
    <script src="./scripts/teacher_statistics.js"></script>
</body>

</html>