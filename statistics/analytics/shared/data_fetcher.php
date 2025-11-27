<?php
// statistics/analytics/shared/data_fetcher.php
// Helper function to process JSON responses
function processEvaluationResponses($responses) {
    return array_map(function($response) {
        $answerData = json_decode($response['AnswerValue'], true);
        return [
            'QuestionID' => $response['QuestionID'],
            'type' => $answerData['type'],
            'value' => $answerData['value'] ?? null,
            'content' => $answerData['content'] ?? null,
            'AnsweredAt' => $response['AnsweredAt'],
            'Metadata' => json_decode($response['Metadata'], true)
        ];
    }, $responses);
}

/**
 * Fetches and groups evaluation questions by type with dynamic filtering
 * 
 * @param mysqli $conn Database connection
 * @param string $formType Evaluation form type (e.g., 'course_evaluation')
 * @param string $formTarget Evaluation target (e.g., 'student')
 * @param array $conditions Additional WHERE conditions (e.g., ["er.Semester = ?", "JSON_UNQUOTE(JSON_EXTRACT(er.Metadata, '$.course_id')) = ?"])
 * @param array $params Parameters for the conditions (e.g., [$semester, $courseCode])
 * @param string $paramTypes Parameter types (e.g., 'is')
 * @return array Grouped questions in format ['type' => [questions...]]
 */
function getGroupedQuestions(
    mysqli $conn,
    string $formType,
    string $formTarget,
    array $conditions = [],
    array $params = [],
    string $paramTypes = ''
): array {
    // Base query
    $query = "SELECT
            q.ID AS QuestionID,
            q.TitleQuestion AS QuestionTitle,
            q.TypeQuestion AS QuestionType,
            er.AnswerValue,
            er.AnsweredAt,
            er.Metadata
        FROM EvaluationResponses AS er
        JOIN Question q ON er.QuestionID = q.ID
        WHERE er.FormType = ?
          AND er.FormTarget = ?";
    
    // Add custom conditions
    if (!empty($conditions)) {
        $query .= " AND " . implode(" AND ", $conditions);
    }

    $query .= " ORDER BY q.ID, er.AnsweredAt DESC";

    // Prepare and execute query
    $stmt = $conn->prepare($query);
    
    // Merge parameters: base params first, then additional params
    $fullParams = array_merge([$formType, $formTarget], $params);
    
    // Prepend 'ss' for FormType and FormTarget (both strings)
    $fullParamTypes = 'ss' . $paramTypes;
    
    // Bind parameters with correct type string
    $stmt->bind_param($fullParamTypes, ...$fullParams);
    
    $stmt->execute();
    
    $rawQuestions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Structure questions by type
    $grouped = [
        'evaluation' => [],
        'multiple_choice' => [],
        'true_false' => [],
        'essay' => []
    ];

    foreach ($rawQuestions as $response) {
        $questionId = $response['QuestionID'];
        $questionType = $response['QuestionType'];
        $answerData = json_decode($response['AnswerValue'], true);

        // Normalize type names
        $normalizedType = match($questionType) {
            'boolean' => 'true_false',
            default => $questionType
        };

        if (!isset($grouped[$normalizedType][$questionId])) {
            $grouped[$normalizedType][$questionId] = [
                'ID' => $questionId,
                'Title' => $response['QuestionTitle'],
                'Type' => $normalizedType,
                'Answers' => []
            ];

            if ($normalizedType === 'multiple_choice') {
                $grouped[$normalizedType][$questionId]['options'] = $answerData['options'] ?? [];
            }
        }

        $answerValue = match($normalizedType) {
            'essay' => $answerData['content'] ?? '',
            'multiple_choice' => $answerData['selected'] ?? '',
            'evaluation' => (float)($answerData['value'] ?? 0),
            'true_false' => (bool)($answerData['value'] ?? false),
            default => null
        };

        $grouped[$normalizedType][$questionId]['Answers'][] = [
            'value' => $answerValue,
            'timestamp' => $response['AnsweredAt'],
            'metadata' => json_decode($response['Metadata'], true)
        ];
    }

    return array_filter($grouped);
}