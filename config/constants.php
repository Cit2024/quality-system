<?php
/**
 * Application Constants
 * Consolidates all magic numbers and hardcoded configuration values
 */

// Administrative Configuration
define('MASTER_ADMIN_USERNAME', 'DrGabriel');

// Security & Submission Limits
define('METADATA_SIZE_LIMIT', 50000); // 50KB limit for JSON metadata
define('CSRF_TOKEN_LENGTH', 32);
define('SESSION_LIFETIME', 1800); // 30 minutes in seconds

// Chart & Statistics Configuration
define('DEFAULT_CHART_COLOR', '#FF6303');
define('CHART_COLOR_PALETTE', [
    '#4dc9f6', '#f67019', '#f53794', '#537bc4', '#acc236',
    '#166a8f', '#00a950', '#58595b', '#8549ba', '#ff6303'
]);

// Precision Thresholds
define('PERCENTAGE_PRECISION', 1);
define('RATING_PRECISION', 2);

// UI Configuration
define('NO_DATA_ICON_SIZE', '50px');
define('NO_DATA_ICON_COLOR', '#ccc');

// Database Limits
define('MAX_QUESTION_TITLE_LENGTH', 1000);
define('MAX_SECTION_TITLE_LENGTH', 255);
?>
