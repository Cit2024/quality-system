<?php
// pages/statistics.php
session_start();
$currentPage = 'statistics';

include 'components/header.php';
include 'forms/form_constants.php';

// Include database connections
require_once 'config/dbConnectionCit.php';
require_once 'config/DbConnection.php';
include 'helpers/database.php';

$validTabs = array_keys(FORM_TARGETS);
$tab = isset($_GET['tab']) && in_array($_GET['tab'], $validTabs) ? $_GET['tab'] : 'student';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإحصائيات</title>
    <link rel="stylesheet" href="styles/global.css">
    <link rel="stylesheet" href="styles/statistics.css">
</head>

<body>
    <!-- =========================== Main Content ======================== -->
    <div class="main">
        <!-- ==================== Top Bar ==================== -->
        <div class="top-bar">
            <div class="toggle-button">
                <i class="fa-solid fa-bars"></i>
            </div>
            <div class="semester-info">
                <span><?= htmlspecialchars($semester['ZamanName']) ?></span>
            </div>
            <div class="user-profile">
                <img src="./assets/icons/circle-user-round.svg" alt="صورة المستخدم">
            </div>
        </div>

        <!-- ==================== Statistics Container ==================== -->
        <?php if ($_SESSION['permissions']['isCanGetAnalysis']): ?>
            <div class="container-statistics">
                <!-- Main Tabs -->
                <div class="tabs">
                    <?php foreach(FORM_TARGETS as $target => $targetData): ?>
                        <button class="tab-button <?= $tab === $target ? 'active' : '' ?>" 
                                data-target="<?= $target ?>">
                            <img src="<?= $targetData['icon'] ?>" class="tab-icon">
                            <?= $targetData['name'] ?>
                        </button>
                    <?php endforeach; ?>
                </div>
                
                
                <!-- Search and Filter -->
                <div class="search-container">
                    <input type="text" class="search-input" placeholder="بحث...">
                        <select class="filter-select">
                            <option value="all">الكل</option>
                            <!-- Dynamically populated -->
                        </select>
                </div>

                <!-- Tab Contents -->
                <?php foreach(FORM_TARGETS as $target => $targetData): ?>
                    <div class="tab-content <?= $tab === $target ? 'active' : '' ?>" id="<?= $target ?>-tab">
                        <!-- Sub Tabs -->
                        <div class="sub-tabs-wrapper"> <!-- Add this wrapper -->
                            <div class="sub-tabs">
                                <?php foreach(FORM_TYPES as $type => $typeData): 
                                    if(in_array($target, $typeData['allowed_targets'])): ?>
                                        <button class="sub-tab-button" data-type="<?= $type ?>">
                                            <img src="<?= $typeData['icon'] ?>" class="sub-tab-icon">
                                            <?= $typeData['name'] ?>
                                        </button>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>
                        </div>


                        <!-- Cards Container -->
                        <div class="cards-container">
                            <!-- AJAX Content Loaded Here -->
                        </div>

                        <!-- No Results Message -->
                        <div class="no-results-message" style="display: none;">
                            <img src="./assets/icons/no-data.svg" alt="No results" width="50">
                            <p>لا توجد عناصر مطابقة لـ "<span class="search-term"></span>"</p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-permission">
                <p>لا تمتلك الصلاحية لعرض هذه الصفحة</p>
                <a href="./login.php">تسجيل كمشرف مصلح له</a>
            </div>
        <?php endif; ?>
        
        </div>
        <?php include './components/footer.php'; ?>
    </div>

    <!-- ==================== JavaScript ==================== -->
    <script>
        window.FORM_TYPES = <?= json_encode(FORM_TYPES) ?>;
        window.FORM_TARGETS = <?= json_encode(FORM_TARGETS) ?>;
    </script>
    <script src="./scripts/lib/utils.js"></script> <!-- Add this line -->
    <script src="./scripts/statistics.js"></script>
</body>

</html>