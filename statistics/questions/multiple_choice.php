<?php
/**
 * Displays multiple choice question analytics
 * @param array $questionData {
 *     @type string $question  Question text
 *     @type array  $choices   [choice => count]
 *     @type int    $total     Total responses
 * }
 */
 
 // Include shared header
require_once __DIR__ . '/../analytics/shared/header.php';

// Split title
$titleParts = split_arabic_english($questionData['question']);
?>
<div class="question-container mc-question" data-question-type="multiple_choice">
    <h3 class="question-title">
        <div class="arabic-text"><?= htmlspecialchars($titleParts['arabic']) ?></div>
        <?php if (!empty($titleParts['english'])): ?>
            <div class="english-text"><?= htmlspecialchars($titleParts['english']) ?></div>
        <?php endif; ?>
    </h3>
    
    <div class="chart-container">
        <canvas 
            id="mcChart-<?= $questionData['id'] ?>" 
            data-labels="<?= htmlspecialchars(json_encode(array_keys($questionData['choices']))) ?>" 
            data-values="<?= htmlspecialchars(json_encode(array_values($questionData['choices']))) ?>"
        ></canvas>
    </div>
    
    <div class="response-total">Total responses: <?= $questionData['total'] ?></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('mcChart-<?= $questionData['id'] ?>');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: JSON.parse(ctx.dataset.labels),
            datasets: [{
                data: JSON.parse(ctx.dataset.values),
                backgroundColor: [
                    '#4dc9f6', '#f67019', '#f53794', '#537bc4', '#acc236',
                    '#166a8f', '#00a950', '#58595b', '#8549ba'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: ctx => `${ctx.parsed.y} (${((ctx.parsed.y/<?= $questionData['total'] ?>)*100).toFixed(1)}%)`
                    }
                }
            },
            scales: {
                y: { 
                    beginAtZero: true,
                    title: {
                        font: { family: "DINRegular" }
                    }
                },
                x: { 
                    title: {
                        font: { family: "DINRegular" }
                    },
                    grid: { color: "rgba(0, 0, 0, 0.1)" },
                    ticks: { font: { family: "DINRegular" } }
                }
            }
        }
    });
});
</script>

<style>
.mc-question .chart-container {
    margin: 1.5rem 0;
    max-width: 600px;
}

.response-total {
    font-size: 0.9rem;
    color: #7f8c8d;
    margin-top: 0.5rem;
}
</style>