<?php
/**
 * Processes bilingual text (Arabic + English) for proper RTL display
 * 
 * @param string $text The input text containing both Arabic and English
 * @param int $maxLength Maximum length before truncation (0 for no truncation)
 * @return string Formatted HTML with proper language direction handling
 */
function formatBilingualText($text, $maxLength = 0) {
    // First apply standard trimming if needed
    if ($maxLength > 0) {
        $text = trimText($text, $maxLength);
    }
    
    // Check if the text contains both Arabic and English
    $hasArabic = preg_match('/\p{Arabic}/u', $text);
    $hasEnglish = preg_match('/[a-zA-Z]/', $text);
    
    if ($hasArabic && $hasEnglish) {
        // Improved splitting that handles paragraphs
        $parts = preg_split('/([a-zA-Z][a-zA-Z\s,.!?-]+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        
        // Clean up the parts
        $arabicParts = [];
        $englishParts = [];
        
        foreach ($parts as $part) {
            if (preg_match('/\p{Arabic}/u', $part)) {
                $arabicParts[] = trim($part);
            } elseif (preg_match('/[a-zA-Z]/', $part)) {
                $englishParts[] = trim($part);
            }
        }
        
        $arabicText = implode(' ', $arabicParts);
        $englishText = implode(' ', $englishParts);
        
        if ($arabicText && $englishText) {
            return htmlspecialchars($arabicText, ENT_QUOTES, 'UTF-8') . 
                   '<span class="english-text" dir="ltr">' . 
                   htmlspecialchars($englishText, ENT_QUOTES, 'UTF-8') . 
                   '</span>';
        }
    }
    
    // Fallback for single language text
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Dummy function for GPA calculation
 */
function calculateCumulativeGPA($studentID) {
    return ['gpa' => 0];
}

// Original trim function remains the same
function trimText($text, $maxLength) {
    $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    if (mb_strlen($text) > $maxLength) {
        return mb_substr($text, 0, $maxLength - 3) . '...';
    }
    return $text;
}
?>