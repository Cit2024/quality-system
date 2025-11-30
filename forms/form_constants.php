<?php
// forms/form_constants.php
// This file loads form types and targets from the database and defines them as constants

require_once __DIR__ . '/../config/DbConnection.php';
require_once __DIR__ . '/../helpers/FormTypes.php';

$fetchedFormTypes = isset($con) ? FormTypes::getFormTypes($con) : [];
$fetchedFormTargets = isset($con) ? FormTypes::getFormTargets($con) : [];

// Add database IDs to the arrays
foreach ($fetchedFormTypes as $slug => &$type) {
    // Fetch the ID from database
    $stmt = $con->prepare("SELECT ID FROM FormTypes WHERE Slug = ?");
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $type['id'] = $row['ID'];
    }
    $stmt->close();
}

foreach ($fetchedFormTargets as $slug => &$target) {
    // Fetch the ID from database
    $stmt = $con->prepare("SELECT ID FROM EvaluatorTypes WHERE Slug = ?");
    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $target['id'] = $row['ID'];
    }
    $stmt->close();
}

define('FORM_TYPES', $fetchedFormTypes);
define('FORM_TARGETS', $fetchedFormTargets);

define('TYPE_QUESTION',[
    'true_false' => [
        'name' => 'صح/خطأ',
        'icon' => './assets/icons/square-check.svg'
    ],
    'evaluation' => [
        'name' => 'تقييم',
        'icon' => './assets/icons/star.svg'
    ],
    'multiple_choice' => [
        'name' => 'إختيار من متعدد',
        'icon' => './assets/icons/list-check.svg'
    ],
    'essay' => [
        'name' => 'مقالي',
        'icon' => './assets/icons/quote.svg'
    ]
]);
