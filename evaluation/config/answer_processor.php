<?php
// evaluation/config/answer_processor.php

function processAnswerResponse(array $questionData, array $response): ?array {
    if (!isset($questionData['TypeQuestion']) || empty($response)) {
        return null;
    }

    $answerValue = [];
    
    switch($questionData['TypeQuestion']) {
        case 'essay':
            if (empty($response['answer'])) return null;
            $answerValue = [
                'type' => 'essay',
                'content' => htmlspecialchars(trim($response['answer']))
            ];
            break;

        case 'multiple_choice':
            if (!isset($response['answer'])) return null;
            
            $selected = htmlspecialchars(trim($response['answer']));
            $options = json_decode($questionData['Choices'] ?? '[]', true) ?: [];
            
            if (!in_array($selected, $options)) {
                throw new InvalidArgumentException("Invalid choice for question");
            }

            $answerValue = [
                'type' => 'multiple_choice',
                'selected' => $selected,
                'options' => $options
            ];
            break;

        case 'evaluation':
            if (!isset($response['rating'])) return null;
            
            $range = (int)$response['rating'];
            
            $value = filter_var($range, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 1, 'max_range' => 5]
            ]);
            
            if (!$value) return null;
            
            $answerValue = [
                'type' => 'evaluation',
                'value' => $value
            ];
            break;

        case 'true_false':
            if (!isset($response['answer'])) return null;
            
            $answerValue = [
                'type' => 'boolean',
                'value' => ($response['answer'] === 'agree') ? 1 : 0
            ];
            break;

        default:
            return null;
    }

    return $answerValue;
}

function encodeAnswerValue(array $answerValue): string {
    return json_encode($answerValue, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}