<?php
/**
 * Validation Tests
 * Tests input validation functions
 */

namespace QualitySystem\Tests\Unit;

use PHPUnit\Framework\TestCase;

class ValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testSlugValidation()
    {
        // Valid slugs
        $this->assertTrue($this->isValidSlug('course_evaluation'));
        $this->assertTrue($this->isValidSlug('student'));
        $this->assertTrue($this->isValidSlug('test_123'));
        $this->assertTrue($this->isValidSlug('IDStudent'));
        
        // Invalid slugs
        $this->assertFalse($this->isValidSlug('invalid-slug'));
        $this->assertFalse($this->isValidSlug('invalid slug'));
        $this->assertFalse($this->isValidSlug('invalid@slug'));
        $this->assertFalse($this->isValidSlug(''));
    }

    public function testSlugPattern()
    {
        $pattern = '/^[a-zA-Z0-9_]+$/';
        
        $this->assertMatchesRegularExpression($pattern, 'valid_slug');
        $this->assertDoesNotMatchRegularExpression($pattern, 'invalid-slug');
    }

    public function testEmailValidation()
    {
        // Valid emails
        $this->assertTrue($this->isValidEmail('test@example.com'));
        $this->assertTrue($this->isValidEmail('user.name@domain.co.uk'));
        
        // Invalid emails
        $this->assertFalse($this->isValidEmail('invalid'));
        $this->assertFalse($this->isValidEmail('invalid@'));
        $this->assertFalse($this->isValidEmail('@example.com'));
    }

    public function testRequiredFieldValidation()
    {
        $data = [
            'field1' => 'value1',
            'field2' => '',
            'field3' => null,
            'field4' => 'value4'
        ];
        
        $required = ['field1', 'field2', 'field3', 'field5'];
        $missing = $this->getMissingFields($data, $required);
        
        $this->assertContains('field2', $missing); // Empty string
        $this->assertContains('field3', $missing); // Null
        $this->assertContains('field5', $missing); // Not set
        $this->assertNotContains('field1', $missing); // Valid
    }

    public function testJSONMetadataValidation()
    {
        // Valid JSON
        $valid = '{"key": "value", "nested": {"key2": "value2"}}';
        $this->assertTrue($this->isValidJSON($valid));
        
        // Invalid JSON
        $invalid = '{key: value}';
        $this->assertFalse($this->isValidJSON($invalid));
        
        $empty = '';
        $this->assertFalse($this->isValidJSON($empty));
    }

    public function testMetadataSizeLimit()
    {
        $smallData = json_encode(['test' => 'data']);
        $this->assertTrue($this->isWithinSizeLimit($smallData, 50000));
        
        // Create large JSON
        $largeArray = array_fill(0, 10000, 'test data for size testing');
        $largeData = json_encode($largeArray);
        $this->assertFalse($this->isWithinSizeLimit($largeData, 50000));
    }

    public function testFormTypeCombinationValidation()
    {
        // This would typically query the database
        // For unit testing, we'll test the logic
        
        $validCombinations = [
            ['form_type' => 'course_evaluation', 'evaluator_type' => 'student'],
            ['form_type' => 'teacher_evaluation', 'evaluator_type' => 'student'],
        ];
        
        $testCombination = ['form_type' => 'course_evaluation', 'evaluator_type' => 'student'];
        $this->assertTrue($this->isValidCombination($testCombination, $validCombinations));
        
        $invalidCombination = ['form_type' => 'course_evaluation', 'evaluator_type' => 'invalid'];
        $this->assertFalse($this->isValidCombination($invalidCombination, $validCombinations));
    }

    // Helper methods (would normally be in a separate ValidationHelper class)
    
    private function isValidSlug($slug)
    {
        return preg_match('/^[a-zA-Z0-9_]+$/', $slug) === 1;
    }

    private function isValidEmail($email)
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    private function getMissingFields($data, $required)
    {
        $missing = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missing[] = $field;
            }
        }
        return $missing;
    }

    private function isValidJSON($string)
    {
        if (empty($string)) {
            return false;
        }
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private function isWithinSizeLimit($data, $limit)
    {
        return strlen($data) <= $limit;
    }

    private function isValidCombination($test, $validCombinations)
    {
        foreach ($validCombinations as $valid) {
            if ($valid['form_type'] === $test['form_type'] && 
                $valid['evaluator_type'] === $test['evaluator_type']) {
                return true;
            }
        }
        return false;
    }
}
?>
