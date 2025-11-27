<?php
/**
 * Displays star rating question analytics
 * @param array $questionData {
 *     @type string $question      Question text
 *     @type float  $average       Average rating (1-5)
 *     @type array  $distribution  Rating distribution [1=>count, 2=>count,...5=>count]
 *     @type int    $total         Total responses
 * }
 */
 
// Include shared header
require_once __DIR__ . '/../analytics/shared/header.php';

// Split title
$titleParts = split_arabic_english($questionData['question']);

?>
<div class="question-container rating-question" data-question-type="evaluation">
    <h3 class="question-title">
        <div class="arabic-text"><?= htmlspecialchars($titleParts['arabic']) ?></div>
        <?php if (!empty($titleParts['english'])): ?>
            <div class="english-text"><?= htmlspecialchars($titleParts['english']) ?></div>
        <?php endif; ?>
    </h3>
    
    <div class="rating-display">
        <div class="average-rating">
            <!-- Star icons -->
            <div class="stars">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <i class="fas fa-star <?= $i <= round($questionData['average']) ? 'filled' : '' ?>"></i>
                <?php endfor; ?>
            </div>
            
            <!-- Numerical values -->
            <div class="rating-numbers">
                <span class="average"><?= number_format($questionData['average'], 1) ?></span>
                <span class="total">(<?= $questionData['total'] ?> عدد التقييمات)</span>
            </div>
        </div>

        <!-- Distribution chart -->
        <div class="distribution-chart">
            <canvas 
                id="ratingDist-<?= $questionData['id'] ?>"
                data-labels="<?= htmlspecialchars(json_encode(range(1, 5))) ?>" 
                data-values="<?= htmlspecialchars(json_encode(array_values($questionData['distribution']))) ?>"
            ></canvas>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('ratingDist-<?= $questionData['id'] ?>');
    // FIX: Replace 'canvas' with 'ctx'
    const existingChart = Chart.getChart(ctx);
    if (existingChart) {
        existingChart.destroy();
    }
    
    // Destroy existing chart if it exists
        if (window.questionCharts && window.questionCharts[ctx.id]) {
            window.questionCharts[ctx.id].destroy();
        }
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: JSON.parse(ctx.dataset.labels).map(String), // Correct labels (1-5)
            datasets: [{
                label: 'عدد التقييمات',
                data: JSON.parse(ctx.dataset.values), // Fixed syntax
                backgroundColor: JSON.parse(ctx.dataset.values).map(val => 
                    val > 0 ? '#ff6303' : 'rgba(200, 200, 200, 0.2)'
                ),
                borderWidth: 0
            }]
        },
        options: {
            indexAxis: 'x',
            scales: {
                y: { 
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'عدد التقييمات',
                        font: { family: "DINRegular" }
                    }
                },
                x: { 
                    type: 'category',
                    title: {
                        display: true,
                        text: 'التقييم (نجوم)',
                        font: { family: "DINRegular" }
                    },
                    ticks: {
                        callback: function(val) {
                            // Get actual label text
                            return this.getLabelForValue(val) + ' ★';
                        }
                    }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    callbacks: {
                        label: (context) => {
                            const value = context.parsed.y;
                            const percentage = ((value / <?= $questionData['total'] ?>) * 100).toFixed(1);
                            return `التقييمات: ${value} (${percentage}%)`;
                        }
                    }
                }
            },
            responsive: true,
            maintainAspectRatio: false
        }
    });
});
</script>

<style>
.rating-question {
    margin: 2rem 0;
    padding: 1.5rem;
    background: #f8f9fa;
    border-radius: 8px;
}

.stars {
    font-size: 1.5rem;
    color: #ddd;
}

.stars .filled { color: #ffc107; }

.average-rating {
    display: flex;
    flex-direction: row;
    gap: 20px;
}

/* Added chart sizing */
.distribution-chart {
    margin-top: 1.5rem;
    max-width: 600px;
    height: 300px;
    position: relative;
}

/* Adjust star alignment */
.average-rating {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-bottom: 1rem;
}

.rating-numbers {
    display: flex;
    flex-direction: row;
    gap: 4px;
}

.average { 
    font-weight: bold;
    color: #2c3e50;
}

.total {
    font-size: 0.9rem;
    color: #7f8c8d;
}

.distribution-chart {
    margin-top: 1.5rem;
    max-width: 500px;
}
</style>