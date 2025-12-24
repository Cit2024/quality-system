<?php
/**
 * Simple Testing Wrapper for Generic Template
 * Use this to test the generic template directly
 */

require_once __DIR__.'/analytics/config/ConfigurationLoader.php';
require_once __DIR__.'/analytics/templates/GenericEvaluationTemplate.php';

// Test program_evaluation
try {
    echo "Testing Generic Template: program_evaluation + student\n";
    echo "============================================\n\n";
    
    $template = new GenericEvaluationTemplate('program_evaluation', 'student');
    
    // Simulate request
    $_GET = ['semester' => 74];
    
    $template->render($_GET);
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString();
}
