<?php
// analytics/shared/chart_helper.php
function render_chart_config($data, $type = 'line') {
    return [
        'type' => $type,
        'data' => [
            'labels' => array_keys($data),
            'datasets' => [[
                'label' => 'التقييمات',
                'data' => array_values($data),
                'borderColor' => '#FF6303',
                'tension' => 0.1
            ]]
        ],
        'options' => [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'scales' => [
                'y' => ['beginAtZero' => true, 'max' => 5]
            ]
        ]
    ];
}