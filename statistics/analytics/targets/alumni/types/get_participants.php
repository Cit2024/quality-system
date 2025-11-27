<?php
// statistics/analytics/targets/alumni/types/get_participants.php
require_once __DIR__ . '/../../../shared/auth.php';
require_once __DIR__ . '/../../../shared/database.php';


$period = $_GET['period'] ?? 'last_year';
$monthsMap = [
    'current_month' => 1,
    'last_3_months' => 3,
    'last_6_months' => 6,
    'last_year' => 12
];
$months = $monthsMap[$period] ?? 12;

$count = safeFetch(
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

header('Content-Type: application/json');
echo json_encode(['success' => true, 'count' => $count]);