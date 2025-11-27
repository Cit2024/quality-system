<?php
// components/answer_types/essay-input.php
include_once 'essay-input.php';

    essayAnswerComponent(
        questionId: $question['ID'],
        label: 'الإجابة المقالية',
        placeholder: 'يرجى كتابة إجابة مفصلة...',
        required: true,
        maxLength: 1500
    );

?>