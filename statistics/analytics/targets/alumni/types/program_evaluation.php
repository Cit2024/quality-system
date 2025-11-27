<?php
// statistics/analytics/targets/alumni/types/program_evaluation.php

require_once __DIR__ . '/../../../shared/auth.php';
require_once __DIR__ . '/../../../shared/database.php';
require_once __DIR__ . '/../../../shared/data_fetcher.php';


// 1. Process Metadata and Link to Questions
// ------------------------------------------------------------
function enhanceAnswersWithMetadata($answers) {
    return array_map(function($answer) {
        $rawMetadata = is_array($answer['metadata']) ? 
            $answer['metadata'] : 
            json_decode($answer['metadata'] ?? '{}', true);

        // Structured metadata definition
        $metadata = [
            [
                'label' => 'الاسم',
                'value' => $rawMetadata['graduate_name'] ?? null,
                'key' => 'graduate_name'
            ],
            [
                'label' => 'الوظيفة',
                'value' => $rawMetadata['job'] ?? null,
                'key' => 'job'
            ],
            [
                'label' => 'التخصص',
                'value' => $rawMetadata['specialization'] ?? null,
                'key' => 'specialization'
            ],
            [
                'label' => 'سنة التخرج',
                'value' => $rawMetadata['graduation_year'] ?? null,
                'key' => 'graduation_year'
            ]
        ];

        // Filter out empty values
        $filteredMetadata = array_filter($metadata, fn($item) => !empty($item['value']));

        return array_merge($answer, [
            'metadata' => $filteredMetadata
        ]);
    }, $answers);
}

// 2. Fetch and Process Evaluation Responses
// ------------------------------------------------------------
$groupedQuestions = getGroupedQuestions(
    getQualityConnection(),
    'program_evaluation',
    'alumni',
        [
            "JSON_EXTRACT(Metadata, '$.graduation_year') IS NOT NULL",
            "JSON_EXTRACT(Metadata, '$.specialization') IS NOT NULL",
            "JSON_EXTRACT(Metadata, '$.job') IS NOT NULL"
        ],
    [],
    ''
) ?? [];

foreach ($groupedQuestions as $type => &$questions) {
    foreach ($questions as &$question) {
        if ($type === 'evaluation') {
            // Calculate distribution from answers
            $ratings = array_map(function($a) {
                return (int)round($a['value'] ?? 0);
            }, $question['Answers'] ?? []);
            
            $rawDistribution = array_count_values($ratings);
            
            // Ensure all rating levels exist
            $question['distribution'] = array_replace(
                [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
                $rawDistribution
            );
        }
        
        // Process metadata for answers
        $question['Answers'] = enhanceAnswersWithMetadata($question['Answers'] ?? []);
    }
}
unset($questions, $question);

// 3. Subscription Time Period Calculations
// ------------------------------------------------------------
function getSubscriptionCount($months) {
    return safeFetch(
        getQualityConnection(),
        "SELECT COUNT(DISTINCT 
            CONCAT(
                JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.specialization')),
                '_',
                JSON_UNQUOTE(JSON_EXTRACT(Metadata, '$.graduation_year'))
            )) AS count
        FROM EvaluationResponses
        WHERE FormTarget = 'alumni'
          AND FormType = 'program_evaluation'
          AND AnsweredAt >= DATE_SUB(NOW(), INTERVAL ? MONTH)
          AND JSON_EXTRACT(Metadata, '$.graduation_year') IS NOT NULL",
        [$months],
        'i'
    )[0]['count'] ?? 0;
}

$subscriptionStats = [
    'current_month' => getSubscriptionCount(1),
    'last_3_months' => getSubscriptionCount(3),
    'last_6_months' => getSubscriptionCount(6),
    'last_year' => getSubscriptionCount(12)
];

// 4. Evaluation history over the past months
// ------------------------------------------------------------
$historyQuery = safeFetch(
    getQualityConnection(),
    "SELECT 
        DATE_FORMAT(AnsweredAt, '%Y-%m') AS month,
        AVG(JSON_EXTRACT(AnswerValue, '$.value')) AS average_rating,
        MAX(AnsweredAt) AS last_response_date
    FROM EvaluationResponses
    WHERE FormType = 'program_evaluation'
      AND FormTarget = 'alumni'
      AND AnsweredAt >= DATE_SUB(NOW(), INTERVAL 9 MONTH)
      AND JSON_EXTRACT(AnswerValue, '$.type') = 'evaluation'
      AND JSON_EXTRACT(AnswerValue, '$.value') IS NOT NULL
    GROUP BY DATE_FORMAT(AnsweredAt, '%Y-%m')
    ORDER BY last_response_date DESC
    LIMIT 9",
    [],
    ''
);

// Organize data for the chart
$historyData = [
    'labels' => [],
    'averages' => []
];

foreach ($historyQuery as $row) {
    $historyData['labels'][] = date('M Y', strtotime($row['month']));
    $historyData['averages'][] = round($row['average_rating'], 2);
}

// Reverse to be in the correct chronological order
$historyData['labels'] = array_reverse($historyData['labels']);
$historyData['averages'] = array_reverse($historyData['averages']);

// 5. Process current year's evaluation data by month
// ------------------------------------------------------------
$currentYear = date('Y');
$currentYearData = getGroupedQuestions(
    getQualityConnection(),
    'program_evaluation',
    'alumni',
    ["YEAR(AnsweredAt) = ?", "JSON_EXTRACT(Metadata, '$.graduation_year') IS NOT NULL"],
    [$currentYear],
    'i'
) ?? [];

$in_year = [];

// Process all answers in one pass
foreach ($currentYearData as $questionType => $questions) {
    foreach ($questions as $questionId => $question) {
        foreach ($question['Answers'] as $answer) {
            // Process metadata first
            $enhancedAnswer = enhanceAnswersWithMetadata([$answer])[0];
            
            // Get month info
            $date = new DateTime($enhancedAnswer['timestamp']);
            $monthNum = $date->format('n');
            $monthName = $date->format('F');
            
            // Initialize month structure
            if (!isset($in_year[$monthNum])) {
                $in_year[$monthNum] = [
                    'name_month' => $monthName,
                    'number_month' => $monthNum,
                    'participants' => [],
                    'questions' => []
                ];
            }

            // Track unique participants
            $participant = $enhancedAnswer['metadata']['graduation_year'] ?? null;
            if ($participant && !in_array($participant, $in_year[$monthNum]['participants'])) {
                $in_year[$monthNum]['participants'][] = $participant;
            }

            // Initialize question structure
            $questionKey = "{$questionType}_{$questionId}";
            if (!isset($in_year[$monthNum]['questions'][$questionKey])) {
                $in_year[$monthNum]['questions'][$questionKey] = [
                    'ID' => $question['ID'],
                    'Title' => $question['Title'],
                    'Type' => $questionType,
                    'Answers' => [],
                    'distribution' => $questionType === 'evaluation' ? array_fill(1, 5, 0) : null,
                    'total' => 0,
                    'sum' => 0
                ];
            }

            // Store answer with processed metadata
            $in_year[$monthNum]['questions'][$questionKey]['Answers'][] = $enhancedAnswer;
            $in_year[$monthNum]['questions'][$questionKey]['total']++;

            // Update evaluation distribution
            if ($questionType === 'evaluation') {
                $rating = (int)round($enhancedAnswer['value']);
                $in_year[$monthNum]['questions'][$questionKey]['distribution'][$rating]++;
                $in_year[$monthNum]['questions'][$questionKey]['sum'] += $enhancedAnswer['value'];
            }
        }
    }
}

// Final processing and cleanup
foreach ($in_year as &$month) {
    // Convert participants to count
    $month['participants'] = count($month['participants']);

    // Calculate averages
    foreach ($month['questions'] as &$question) {
        if ($question['Type'] === 'evaluation' && $question['total'] > 0) {
            $question['average'] = round($question['sum'] / $question['total'], 2);
        }
        unset($question['sum']); // Remove temporary field
    }
}

// Sort by month number
ksort($in_year);

// Reset array keys and convert to indexed array
$in_year = array_values($in_year);


// 6. Prepare View Data
// ------------------------------------------------------------
$viewData = [
    'evaluator' => "الخريج",
    'questions' => $groupedQuestions,
    'stats' => [
        'total' => $subscriptionStats['last_year'],
        'subscription' => $subscriptionStats,
        'participants' => getSubscriptionCount(12)
    ],
    'history' => $historyData,
    'history_in_year' => $in_year
];

// Include view template
require_once __DIR__ . '/../../../targets/views/program_evaluation.php';