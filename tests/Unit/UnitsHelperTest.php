<?php
/**
 * Units Helper Tests
 * Tests bilingual text formatting and utility functions
 */

namespace QualitySystem\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../helpers/units.php';

class UnitsHelperTest extends TestCase
{
    public function testFormatBilingualText()
    {
        // Test Arabic only
        $arabicOnly = "نص عربي";
        $result = formatBilingualText($arabicOnly);
        $this->assertStringContainsString($arabicOnly, $result);
        
        // Test with English (using @)
        $bilingual = "النص العربي @English Text";
        $result = formatBilingualText($bilingual);
        $this->assertStringContainsString("النص العربي", $result);
        $this->assertStringContainsString("English Text", $result);
        $this->assertStringContainsString('<span', $result);
    }

    public function testFormatBilingualTextWithoutEnglish()
    {
        $arabicOnly = "نص عربي فقط";
        $result = formatBilingualText($arabicOnly);
        
        // Should not create a span for English if no @ present
        $this->assertEquals($arabicOnly, $result);
    }

    public function testFormatBilingualTextEmpty()
    {
        $empty = "";
        $result = formatBilingualText($empty);
        $this->assertEquals("", $result);
    }

    public function testFormatBilingualTextOnlyEnglish()
    {
        $englishOnly = "@English Only Text";
        $result = formatBilingualText($englishOnly);
        
        $this->assertStringContainsString("English Only Text", $result);
        $this->assertStringContainsString('<span', $result);
    }

    public function testFormatBilingualTextMultipleAtSymbols()
    {
        // Should only split on first @
        $text = "النص العربي @English Text @ More Text";
        $result = formatBilingualText($text);
        
        $this->assertStringContainsString("النص العربي", $result);
        $this->assertStringContainsString("English Text @ More Text", $result);
    }

    public function testGetBilingualParts()
    {
        $text = "النص العربي @English Text";
        $parts = explode(' @', $text, 2);
        
        $this->assertCount(2, $parts);
        $this->assertEquals("النص العربي", $parts[0]);
        $this->assertEquals("English Text", $parts[1]);
    }
}
?>
