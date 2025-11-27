<?php
// analytics/targets/views/program_evaluation.php

// Fix 1: Add defensive checks for all viewData keys
$evaluator = $viewData['evaluator'] ?? $viewData['program'] ?? 'Unknown Evaluator';
$historyInYear = $viewData['history_in_year'] ?? [];
$questions = $viewData['questions'] ?? [];
$stats = $viewData['stats'] ?? [];
$history = $viewData['history'] ?? ['labels' => [], 'averages' => []];
$periodStats = $stats['subscription'] ?? [];
$participants = $stats['participants'] ?? 0;
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
    <title>تحليلات برنامج دراسي - <?= htmlspecialchars($evaluator) ?></title>
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
                <?php if (!empty($periodStats)): ?>
                    <div class="analytics-card">
                        <div class="control-group">
                            <select class="selection-fields" id="period-selector">
                                <?php
                                $periods = [
                                    'current_month' => 'الشهر الحالي',
                                    'last_3_months' => 'آخر 3 أشهر',
                                    'last_6_months' => 'آخر 6 أشهر',
                                    'last_year' => 'آخر سنة'
                                ];

                                foreach ($periods as $key => $label):
                                    $value = $viewData['stats']['periods'][$key] ?? 0;
                                ?>
                                    <option value="<?= $key ?>" data-count="<?= $value ?>">
                                        <?= $label ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button class="download-report primary-button"
                                data-report-type="program"
                                data-file-name="<?= htmlspecialchars($evaluator) ?>"
                                data-basic-information="<?= htmlspecialchars(json_encode([
                                                            'evaluator' => $evaluator,
                                                            'program' => $evaluator
                                                        ])) ?>"
                                data-questions="<?= htmlspecialchars(json_encode($questions)) ?>"
                                data-stats="<?= htmlspecialchars(json_encode($stats)) ?>">
                                <img src="../assets/icons/file-down.svg" alt="download">
                                تنزيل التقرير
                            </button>
                        </div>
                        <h1 class="primary-title">
                            نتائج تقييم <?= htmlspecialchars($evaluator) ?>
                        </h1>
                    </div>
                <?php endif; ?>
                <?php if (!empty($history['labels'])): ?>
                    <div class="chart-container">
                        <div class="control-group">
                            <select class="chart-type-selector selection-fields">
                                <option value="line">رسم بياني خطي</option>
                                <option value="bar">رسم بياني شرطي</option>
                            </select>
                            <button class="download-history primary-button"
                                data-report-type="program"
                                data-basic-information="<?= htmlspecialchars(json_encode([
                                                            'evaluator' => $evaluator,
                                                            'name' => $evaluator
                                                        ])) ?>"
                                data-all-semester="<?= htmlspecialchars(
                                                        json_encode(array_map(function ($month) {
                                                            $grouped = [];
                                                            foreach ($month['questions'] as $questionKey => $question) {
                                                                $type = $question['Type'];
                                                                if (!isset($grouped[$type])) {
                                                                    $grouped[$type] = [];
                                                                }
                                                                $grouped[$type][$questionKey] = $question;
                                                            }
                                                            return [
                                                                'semester_name' => $month['name_month'],
                                                                'participants' => $month['participants'],
                                                                'questions' => $grouped
                                                            ];
                                                        }, $historyInYear)),
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>">
                                <img src="../assets/icons/folder-clock.svg" alt="folder clock">
                                انشاء تقرير شامل
                            </button>
                        </div>
                        <canvas
                            id="evaluation-chart"
                            data-labels="<?= htmlspecialchars(json_encode($history['labels'])) ?>"
                            data-averages="<?= htmlspecialchars(json_encode($history['averages'])) ?>"
                            data-x-title="الاشهر"
                            data-y-title="المتوسط">
                        </canvas>
                    </div>
                <?php endif; ?>
            </div>

            <div class="questions-container">
                <p class="primary-title">الأسئلة النموذج</p>
                <?php foreach ($questions as $type => $typeQuestions): ?>
                    <?php if (!empty($typeQuestions)): ?>
                        <div class="analytics-card">
                            <p class="base-title">
                                الأسالة
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

                            <?php foreach ($typeQuestions as $questionId => $question): ?>
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
                                        'responses' => array_map(fn($a) => [
                                            'content' => $a['value'] ?? '',
                                            'timestamp' => $a['timestamp'] ?? '',
                                            'metadata' => $a['metadata'] ?? []
                                        ], $question['Answers']),
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
                                        'distribution' => $question['distribution'] ?? [],
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
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../shared/scripts.php'; ?>
    <script src="./analytics/assets/js/chart-utilities.js"></script>

    <!-- Load dependencies first -->
    <script src="./analytics/assets/js/CSVReportGenerator.js"></script>
    <script src="./analytics/assets/js/ExcelReportGenerator.js"></script>
    <script src="./analytics/assets/js/PDFReportGenerator.js"></script>
    <script src="./analytics/assets/js/ZIPReportGenerator.js"></script>

    <!-- Then load utilities -->
    <script src="./analytics/assets/js/download-utilities.js"></script>

    <?php if (isset($viewData['evaluator'])): ?>
        <script>
            document.getElementById('period-selector').addEventListener('change', async (e) => {
                const selectedPeriod = e.target.value;
                const participantsElement = document.getElementById('participants-count');

                try {
                    const response = await fetch(`./analytics/targets/alumni/types/get_participants.php?period=${selectedPeriod}`);
                    const data = await response.json();

                    if (data.success && data.count) {
                        console.log("data count : ", data.count);
                        participantsElement.textContent = data.count;
                    } else {
                        throw new Error(data.error || 'فشل تحديث البيانات');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('حدث خطأ أثناء تحديث البيانات');
                }
            });
        </script>
    <?php endif; ?>
</body>

</html>