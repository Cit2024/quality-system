<?php
// analytics/targets/views/teacher_evaluation.php

?>

<script>
    // Add error handling for JSON encoding
    try {
        const jsObject = <?php echo json_encode($viewData['questions'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
        console.log("questions : ", jsObject);
    } catch (error) {
        console.error('Error parsing questions data:', error);
        const jsObject = {};
    }
</script>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <?php include __DIR__ . '/../../shared/head.php'; ?>
    <title>تحليلات المدرس - <?= htmlspecialchars($viewData['teacher']['name']) ?></title>
    <link rel="stylesheet" href="./analytics/assets/css/main.css">
</head>

<body>
    <div class="container">
        <div class="back-button" onclick="window.history.back()">
            <span>رجوع</span>
            <img src="../assets/icons/chevron-right.svg" alt="Back">
        </div>

        <div class="container-analytics">
            <div class="flex-column gap-20">
                <div class="analytics-card">
                    <div class="grid-4to3 items-center ">
                        <div class="flex-column gap-10">
                            <div class="control-group">

                                <button class="download-report primary-button"
                                    data-report-type="teacher"
                                    data-file-name="<?= htmlspecialchars($viewData['teacher']['name']) ?>"
                                    data-basic-information="<?= htmlspecialchars(json_encode($viewData['teacher'])) ?>"
                                    data-questions="<?= htmlspecialchars(json_encode($viewData['questions'])) ?>"
                                    data-stats="<?= htmlspecialchars(json_encode($viewData['stats'])) ?>">
                                    <img src="../assets/icons/file-down.svg" alt="download">
                                    تنزيل التقرير
                                </button>
                                <button class="download-all-courses primary-button"
                                    data-report-type="teacher"
                                    data-file-name="<?= htmlspecialchars($viewData['teacher']['name']) ?>"
                                    data-all-course="<?= htmlspecialchars(json_encode($viewData['all_courses'])) ?>">
                                    <img src="../assets/icons/files.svg" alt="download files">
                                    تنزيل تقارير لكل المقررات
                                </button>

                                <select class="semester-select selection-fields">
                                    <?php if (!empty($viewData['availableSemesters'])): ?>
                                        <?php foreach ($viewData['availableSemesters'] as $sem): ?>
                                            <option value="<?= $sem['value'] ?>"
                                                <?= (string)$sem['value'] === (string)$viewData['selected']['semester'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($sem['label']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <option disabled selected>لا يوجد فصول دراسية متاحة</option>
                                    <?php endif; ?>
                                </select>
                                <select class="course-select selection-fields">
                                    <?php foreach ($viewData['teacher']['courses'] as $course): ?>
                                        <option value="<?= $course['id'] ?>"
                                            <?= (string)$course['id'] === (string)$viewData['selected']['course'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($course['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="flex-row gap-10">
                                <div class="selfie-photo">
                                    <img src="<?= $viewData['teacher']['photo'] ?>"
                                        alt="<?= htmlspecialchars($viewData['teacher']['name']) ?>">
                                </div>
                                <h1 class="primary-title">
                                    <?= htmlspecialchars($viewData['teacher']['name']) ?>
                                </h1>
                            </div>
                        </div>

                        <canvas id="department-breakdown-chart"
                            data-department-stats="<?= htmlspecialchars(json_encode($viewData['stats']['department_stats'])) ?>"
                            data-total-students="<?= $viewData['stats']['total'] ?>"
                            data-non-participants="<?= $viewData['stats']['non_participants'] ?>">
                        </canvas>
                    </div>
                </div>

                <div class="chart-container">
                    <div class="control-group">
                        <select class="chart-type-selector selection-fields">
                            <option value="line">رسم بياني خطي</option>
                            <option value="bar">رسم بياني شرطي</option>
                        </select>
                        <button class="download-history primary-button"
                            data-report-type="teacher"
                            data-file-name="<?= htmlspecialchars($viewData['teacher']['name']) ?>"
                            data-basic-information="<?= htmlspecialchars(json_encode($viewData['teacher'])) ?>"
                            data-all-semester="<?= htmlspecialchars(json_encode($viewData['all_semesters']), ENT_QUOTES, 'UTF-8') ?>">
                            <img src="../assets/icons/folder-clock.svg" alt="folder clock">
                            تحميل ملف التقييمات عبر الفصول
                        </button>
                    </div>
                    <canvas
                        id="evaluation-chart"
                        data-labels="<?= htmlspecialchars(json_encode(array_column($viewData['history'], 'SemesterName'))) ?>"
                        data-averages="<?= htmlspecialchars(json_encode(array_column($viewData['history'], 'avg_rating'))) ?>"
                        data-x-title="الفصل الدراسي"
                        data-y-title="المتوسط"
                        data-main-title="التقييم التاريخي">
                    </canvas>
                </div>
            </div>

            <div class="questions-container">
                <p class="primary-title">الأسئلة النموذج</p>
                <?php foreach ($viewData['questions'] as $type => $questions): ?>
                    <div class="analytics-card">
                        <p class="base-title">
                            الأسئلة
                            <?php
                            switch ($type):
                                case 'essay':
                                    echo "المقالية";
                                    break;
                                case 'evaluation':
                                    echo "التقييمية";
                                    break;
                                case 'multiple_choice':
                                    echo "الاختيار من متعدد";
                                    break;
                                case 'true_false':
                                    echo "الصواب والخطأ";
                                    break;
                            endswitch;
                            ?>
                        </p>

                        <?php foreach ($questions as $questionId => $question): ?>
                            <?php
                            // Validate question structure
                            if (!isset($question['Type'], $question['ID'], $question['Title'])) {
                                continue;
                            }

                            // Determine template
                            $template = match ($question['Type']) {
                                'essay' => 'essay.php',
                                'evaluation' => 'evaluation.php',
                                'multiple_choice' => 'multiple_choice.php',
                                'true_false' => 'true_false.php',
                                default => null
                            };

                            if (!$template) continue;

                            // Format data
                            $questionData = match ($question['Type']) {
                                'essay' => [
                                    'question' => $question['Title'],
                                    'responses' => array_map(function ($a) {
                                        $metadata = $a['metadata'] ?? [];
                                        if (is_string($metadata)) {
                                            $metadata = json_decode($metadata, true);
                                            if (json_last_error() !== JSON_ERROR_NONE) {
                                                $metadata = [];
                                            }
                                        }
                                        return [
                                            'content' => $a['value'] ?? '',
                                            'timestamp' => $a['timestamp'] ?? '',
                                            'metadata' => is_array($metadata) ? $metadata : []
                                        ];
                                    }, $question['Answers']),
                                    'total' => count($question['Answers'])
                                ],
                                'evaluation' => [
                                    'id' => $question['ID'],
                                    'question' => $question['Title'],
                                    'average' => round(
                                        array_sum(array_column($question['Answers'], 'value')) /
                                            count($question['Answers']),
                                        1
                                    ),
                                    'distribution' => $question['distribution'],
                                    'total' => count($question['Answers'])
                                ],
                                'multiple_choice' => [
                                    'id' => $question['ID'],
                                    'question' => $question['Title'],
                                    'choices' => array_count_values(
                                        array_map('strval', array_column($question['Answers'], 'value'))
                                    ),
                                    'total' => count($question['Answers'])
                                ],
                                'true_false' => [
                                    'id' => $question['ID'],
                                    'question' => $question['Title'],
                                    'choices' => array_count_values(
                                        array_map(
                                            fn($a) => is_bool($a['value']) ?
                                                ($a['value'] ? 'true' : 'false') :
                                                strtolower(strval($a['value'])),
                                            $question['Answers']
                                        )
                                    ),
                                    'total' => count($question['Answers'])
                                ],
                                default => []
                            };

                            // Include template with corrected path
                            include __DIR__ . "/../../../questions/{$template}";
                            ?>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php include __DIR__ . '/../../shared/scripts.php'; ?>
        <script src="./analytics/assets/js/chart-utilities.js"></script>
        <script src="./analytics/assets/js/doughnut-chart-utilities.js"></script>

        <!-- Load dependencies first -->
        <script src="./analytics/assets/js/CSVReportGenerator.js"></script>
        <script src="./analytics/assets/js/ExcelReportGenerator.js"></script>
        <script src="./analytics/assets/js/PDFReportGenerator.js"></script>
        <script src="./analytics/assets/js/ZIPReportGenerator.js"></script>

        <!-- Then load utilities -->
        <script src="./analytics/assets/js/download-utilities.js"></script>
</body>

</html>