<?php
/**
 * Displays true/false question analytics
 * @param array $questionData {
 *     @type string $question  Question text
 *     @type array  $choices   ['true' => count, 'false' => count]
 *     @type int    $total     Total responses
 * }
 */


// Include shared header
require_once __DIR__ . '/../analytics/shared/header.php';
require_once __DIR__ . '/../../config/constants.php';

// Split title
$titleParts = split_arabic_english($questionData['question']);

?>
<div class="question-container tf-question" data-question-type="true_false">
    <h3 class="question-title">
        <div class="arabic-text"><?= htmlspecialchars($titleParts['arabic']) ?></div>
        <?php if (!empty($titleParts['english'])): ?>
            <div class="english-text"><?= htmlspecialchars($titleParts['english']) ?></div>
        <?php endif; ?>
    </h3>
    
    <div class="chart-container">
        <canvas 
            id="tfChart-<?= $questionData['id'] ?>" 
            data-labels="<?= htmlspecialchars(json_encode(['True', 'False'])) ?>" 
            data-values="<?= htmlspecialchars(json_encode(array_values($questionData['choices']))) ?>"
        ></canvas>
    </div>
    
    <div class="response-summary">
        <?php foreach ($questionData['choices'] as $label => $count): ?>
            <div class="summary-item">
                <span class="label"><?= ucfirst($label) ?>:</span>
                <span class="count"><?= $count ?></span>
                <span class="percentage">
                    (<?= number_format(($count / $questionData['total']) * 100, PERCENTAGE_PRECISION) ?>%)
                </span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('tfChart-<?= $questionData['id'] ?>');
    new Chart(ctx, {
        type: 'pie',
        data: {
            labels: JSON.parse(ctx.dataset.labels),
            datasets: [{
                data: JSON.parse(ctx.dataset.values),
                backgroundColor: ['#4dc9f6', '#f67019'],
                hoverOffset: 4
            }]
        },
        options: {
            plugins: {
                tooltip: {
                    callbacks: {
                        label: ctx => `${ctx.label}: ${ctx.raw} (${((ctx.raw/<?= $questionData['total'] ?>)*100).toFixed(PERCENTAGE_PRECISION)}%)`
                    }
                }
            }
        }
    });
});
</script>

<style>
.tf-question .chart-container {
    max-width: 300px;
    margin: 1.5rem auto;
}

.response-summary {
    text-align: center;
    margin-top: 1rem;
}

.summary-item {
    margin: 0.5rem 0;
    font-size: 0.95rem;
}

.label { 
    font-weight: 500;
    color: #2c3e50;
}

.count { color: #7f8c8d; }
.percentage { color: #95a5a6; }
</style>