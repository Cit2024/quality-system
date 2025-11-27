<?php
// Split bilingual text into Arabic/English parts
function split_arabic_english($text) {
    $splitPos = null;
    $length = mb_strlen($text);
    for ($i = 0; $i < $length; $i++) {
        $char = mb_substr($text, $i, 1);
        if (preg_match('/[a-zA-Z]/', $char)) {
            $splitPos = $i;
            break;
        }
    }
    return [
        'arabic' => trim($splitPos !== null ? mb_substr($text, 0, $splitPos) : $text),
        'english' => trim($splitPos !== null ? mb_substr($text, $splitPos) : '')
    ];
}

// Shared CSS (output as <style> tag)
function analytics_styles() {
    ob_start(); ?>
    <style>
    .arabic-text {
        direction: rtl;
        font-family: 'DINRegular', sans-serif;
        margin-bottom: 0.5rem;
        direction: rtl;
    }
    .english-text {
        color: #666;
        font-size: 0.9em;
        border-top: 1px solid #eee;
        padding-top: 0.5rem;
        margin-top: 0.5rem;
        direction: ltr;
    }
    </style>
    <?php return ob_get_clean();
}
?>