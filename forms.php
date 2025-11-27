<?php
// pages/forms.php
session_start();
$currentPage = 'forms'; // Set the current page for active link highlighting

// Get semester data for header
include './components/header.php';

require_once 'config/dbConnectionCit.php';
require_once 'config/DbConnection.php';

// Get fetchData function
require_once 'helpers/database.php';

// Get trimText, formatBilingualText function
require_once 'helpers/units.php';

require_once 'forms/form_constants.php';

// Fetch all forms
$forms = fetchData($con, "SELECT * FROM Form");
if ($forms === false) {
    die("Error loading forms. Please try again later.");
}
$forms = $forms ?: []; // Ensure it's always an array

// Fetch statistics
$stats = fetchData($con, "SELECT COUNT(ID) AS total_forms FROM Form")[0] ?? ['total_forms' => 0];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>النماذج</title>
    <link rel="icon" href="./assets/icons/college.png">
    <link rel="stylesheet" href="styles/forms.css">
    <link rel="stylesheet" href="styles/global.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
    <!-- =========================== main ======================== -->
    <div class="main">
        <div class="top-bar">
            <div class="toggle-button">
                <i class="fa-solid fa-bars"></i>
            </div>
            <div class="semester-info">
                <span><?php echo htmlspecialchars($semester['ZamanName'] ?? 'ربيع 2024 - 2025'); ?></span>
            </div>
            <div class="user-profile">
                <img src="./assets/icons/circle-user-round.svg" alt="user">
            </div>
        </div>
        <div class="forms-page-container">
            <h1 class="welcome-message"><?php echo htmlspecialchars($_SESSION['username']); ?>, مرحبا</h1>
            <div class="forms-content">
                <!-- Statistics -->
                <div class="forms-statistics">
                    <h2>الإحصائيات</h2>
                    <p>إجمالي النماذج: <?php echo htmlspecialchars($stats['total_forms']); ?></p>
                </div>

                <!-- Create Form Button -->
                <?php if ($_SESSION['permissions']['isCanCreate'] ?? false): ?>
                    <div class="add-form-button-container">
                        <div class="add-form-button" onclick="window.location.href = './forms/create-form.php';">
                            <p class="add-form-button-text">إضافة النموذج</p>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- List of Forms -->
                <?php if (count($forms) > 0): ?>
                    <div class="forms-grid">
                        <?php foreach ($forms as $form): ?>
                            <div class="form-card">
                                <div onclick="window.location.href = './forms/edit-form.php?id=<?php echo htmlspecialchars($form['ID']); ?>';">
                                    <div class="form-details">
                                        <!-- Form Status Badge -->
                                        <div class="form-status-badge">
                                            <?php if ($form['FormStatus'] === 'published'): ?>
                                                <img src="./assets/icons/badge-check.svg" alt="Published" />
                                                <span>منشور</span>
                                            <?php else: ?>
                                                <img src="./assets/icons/badge-alert.svg" alt="Draft" />
                                                <span>مسودة</span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="evaluate-evaluated-container">
                                            <div class="evaluate">
                                                <img src="<?= FORM_TARGETS[$form['FormTarget']]['icon'] ?? './assets/icons/user-check.svg' ?>" alt="Evaluator" />
                                                <?php
                                                echo match($form['FormTarget'] ?? '') {
                                                    'student' => FORM_TARGETS['student']['name'],
                                                    'teacher' => FORM_TARGETS['teacher']['name'],
                                                    'admin' => FORM_TARGETS['admin']['name'],
                                                    'alumni' => FORM_TARGETS['alumni']['name'],
                                                    'employer' => FORM_TARGETS['employer']['name'],
                                                    default => 'غير معرف'
                                                };
                                                ?>
                                            </div>
                                            <div class="evaluated">
                                                <img src="<?= FORM_TYPES[$form['FormType']]['icon'] ?? './assets/icons/clipboard-list.svg' ?>" alt="Evaluated" />
                                                <?php
                                                echo match($form['FormType'] ?? '') {
                                                    'course_evaluation' => FORM_TYPES['course_evaluation']['name'],
                                                    'teacher_evaluation' => FORM_TYPES['teacher_evaluation']['name'],
                                                    'program_evaluation' => FORM_TYPES['program_evaluation']['name'],
                                                    'facility_evaluation' => FORM_TYPES['facility_evaluation']['name'],
                                                    'leaders_evaluation' => FORM_TYPES['leaders_evaluation']['name'],
                                                    default => 'غير معرف'
                                                };
                                                ?>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Form Title and ID -->
                                    <h2 class="form-title">
                                        <span class="title">
                                            <?php echo trimText($form['Title'] ?? '', 25); ?>
                                        </span>
                                        <span class="form-id">#<?php echo htmlspecialchars($form['ID']); ?></span>
                                    </h2>

                                    <!-- Form Description -->
                                    <p><?php echo trimText($form['Description'] ?? '', 35); ?></p>
                                </div>
                                
                                <!-- Evaluation Link Section -->
                                <?php if ($form['FormStatus'] === 'published'): ?>
                                <div class="evaluation-link-section">
                                    <div class="link-label">
                                        <i class="fa-solid fa-link"></i>
                                        <span>رابط التقييم:</span>
                                    </div>
                                    <div class="link-container">
                                        <?php 
                                        $baseUrl = "https://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']);
                                        $evalLink = $baseUrl . "/evaluation-form.php?evaluation=" . urlencode($form['FormType']) . 
                                                   "&Evaluator=" . urlencode($form['FormTarget']);
                                        ?>
                                        <input type="text" class="evaluation-link" value="<?php echo htmlspecialchars($evalLink); ?>" readonly>
                                        <button class="copy-link-btn" data-link="<?php echo htmlspecialchars($evalLink); ?>" title="نسخ الرابط">
                                            <i class="fa-solid fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <!-- Delete Button (if authorized) -->
                                <div class="form-actions">
                                    <button class="download-form-button form-action-button" 
                                        data-action="download" 
                                        data-form-id="<?php echo htmlspecialchars($form['ID']); ?>" 
                                        data-form-title="<?php echo htmlspecialchars($form['Title'], ENT_QUOTES, 'UTF-8'); ?>">
                                    <img src="./assets/icons/file-down.svg" alt="download" />
                                    تنزيل النموذج
                                </button>
                                    <?php if ($_SESSION['permissions']['isCanDelete'] ?? false): ?>
                                        <button class="delete-form-button form-action-button" data-action="delete" data-form-id="<?php echo htmlspecialchars($form['ID']); ?>">
                                            <img src="./assets/icons/trash.svg" alt="Delete" style="color:white;" />
                                            حذف
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="no-forms">
                        <img src="./assets/icons/no-data.svg" alt="no forms" />
                        <p>لا توجد نماذج حاليا.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php include "components/footer.php"; ?>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/printThis/1.15.0/printThis.min.js"></script>
    <script src="./scripts/forms.js"></script>
</body>
</html>