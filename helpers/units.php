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
 * Calculates cumulative GPA from archived grade records (asnzil table)
 * Formula: SUM(Nekat) / SUM(AddWhadat)  — grade points / credit hours
 * Falls back to sprofiles.avrg if no grade records found
 */
function calculateCumulativeGPA($studentID) {
    if (empty($studentID)) return ['gpa' => 0];
    
    global $conn_cit;
    if (!isset($conn_cit)) {
        require_once __DIR__ . '/../config/dbConnectionCit.php';
    }
    
    $id = mysqli_real_escape_string($conn_cit, $studentID);
    
    // Primary: Calculate from actual grade records in asnzil
    // Nekat = grade points per course, AddWhadat = credit hours
    $query = "SELECT SUM(Nekat) AS total_points, SUM(AddWhadat) AS total_units 
              FROM asnzil 
              WHERE KidNo = '$id' AND Nekat IS NOT NULL AND AddWhadat > 0";
    $result = mysqli_query($conn_cit, $query);
    
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $totalUnits = (float)($row['total_units'] ?? 0);
        $totalPoints = (float)($row['total_points'] ?? 0);
        
        if ($totalUnits > 0) {
            $gpa = round($totalPoints / $totalUnits, 2);
            return ['gpa' => $gpa];
        }
    }
    
    // Fallback: Check sprofiles.avrg
    $query = "SELECT avrg FROM sprofiles WHERE KidNo = '$id' LIMIT 1";
    $result = mysqli_query($conn_cit, $query);
    
    if ($result && $row = mysqli_fetch_assoc($result)) {
        $avrg = (float)($row['avrg'] ?? 0);
        if ($avrg > 0) {
            return ['gpa' => round($avrg, 2)];
        }
    }
    
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