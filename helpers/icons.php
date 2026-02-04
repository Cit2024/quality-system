<?php
/**
 * Icon Helper Functions
 * Provides consistent Font Awesome icon generation across the application
 */

/**
 * Generate a Font Awesome icon
 * 
 * @param string $iconClass Font Awesome class (e.g., 'fa-solid fa-user')
 * @param string $alt Alternative text for accessibility
 * @param string $additionalClasses Additional CSS classes
 * @return string HTML for the icon
 */
function faIcon($iconClass, $alt = '', $additionalClasses = '') {
    $ariaLabel = !empty($alt) ? " aria-label=\"" . htmlspecialchars($alt) . "\"" : "";
    return "<i class=\"$iconClass $additionalClasses\"$ariaLabel></i>";
}

/**
 * Common icon shortcuts for frequently used icons
 */
function iconUser($alt = 'User') {
    return faIcon('fa-solid fa-circle-user', $alt);
}

function iconTrash($alt = 'Delete') {
    return faIcon('fa-solid fa-trash', $alt);
}

function iconCopy($alt = 'Copy') {
    return faIcon('fa-solid fa-copy', $alt);
}

function iconDownload($alt = 'Download') {
    return faIcon('fa-solid fa-file-arrow-down', $alt);
}

function iconPlus($alt = 'Add') {
    return faIcon('fa-solid fa-circle-plus', $alt);
}

function iconCheck($alt = 'Check') {
    return faIcon('fa-solid fa-circle-check', $alt);
}

function iconWarning($alt = 'Warning') {
    return faIcon('fa-solid fa-triangle-exclamation', $alt);
}
?>
