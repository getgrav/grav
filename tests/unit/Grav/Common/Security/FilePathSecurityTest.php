<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Language\Language;

/**
 * Class FilePathSecurityTest
 *
 * Tests for file path and language security fixes.
 * Covers: GHSA-p4ww-mcp9-j6f2 (file read), GHSA-m8vh-v6r6-w7p6 (language DoS), GHSA-j422-qmxp-hv94 (backup traversal)
 *
 * Naming convention: test{Method}_{GHSA_ID}_{description}
 */
class FilePathSecurityTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var Language */
    protected $language;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();
        $this->language = new Language($this->grav);
    }

    // =========================================================================
    // GHSA-m8vh-v6r6-w7p6: DoS via Language Regex Injection
    // =========================================================================

    /**
     * @dataProvider providerGHSAm8vh_InvalidLanguageCodes
     */
    public function testSetLanguages_GHSAm8vh_FiltersInvalidLanguageCodes(string $lang, string $description): void
    {
        $this->language->setLanguages([$lang]);
        $languages = $this->language->getLanguages();

        self::assertNotContains($lang, $languages, "Should filter invalid language code: $description");
    }

    public static function providerGHSAm8vh_InvalidLanguageCodes(): array
    {
        return [
            // Regex injection attempts
            ["'", 'Single quote (regex breaker)'],
            ['"', 'Double quote'],
            ['/', 'Forward slash (regex delimiter)'],
            ['\\', 'Backslash'],
            ['()', 'Empty parentheses'],
            ['.*', 'Regex wildcard'],
            ['.+', 'Regex one-or-more'],
            ['[a-z]', 'Regex character class'],
            ['en|rm -rf', 'Pipe with command'],
            ['(?=)', 'Regex lookahead'],

            // Path traversal in language
            ['../en', 'Path traversal'],
            ['en/../../etc', 'Nested path traversal'],

            // Special characters
            ['en;', 'Semicolon'],
            ['en<script>', 'HTML tag'],
            ['en${PATH}', 'Shell variable'],
            ["en\nfr", 'Newline injection'],
            ["en\0fr", 'Null byte injection'],

            // Too short/long
            ['e', 'Single character (too short)'],
            ['englishlanguage', 'Too long without separator'],

            // Invalid format
            ['123', 'All numbers'],
            ['en-', 'Trailing hyphen'],
            ['-en', 'Leading hyphen'],
            ['en--US', 'Double hyphen'],
            ['en_', 'Trailing underscore'],
            ['_en', 'Leading underscore'],
        ];
    }

    /**
     * @dataProvider providerGHSAm8vh_ValidLanguageCodes
     */
    public function testSetLanguages_GHSAm8vh_AllowsValidLanguageCodes(string $lang, string $description): void
    {
        $this->language->setLanguages([$lang]);
        $languages = $this->language->getLanguages();

        self::assertContains($lang, $languages, "Should allow valid language code: $description");
    }

    public static function providerGHSAm8vh_ValidLanguageCodes(): array
    {
        return [
            // Standard ISO 639-1 codes
            ['en', 'English'],
            ['fr', 'French'],
            ['de', 'German'],
            ['es', 'Spanish'],
            ['zh', 'Chinese'],
            ['ja', 'Japanese'],
            ['ru', 'Russian'],
            ['ar', 'Arabic'],
            ['pt', 'Portuguese'],
            ['it', 'Italian'],

            // ISO 639-2 three-letter codes
            ['eng', 'English (3-letter)'],
            ['fra', 'French (3-letter)'],
            ['deu', 'German (3-letter)'],

            // Language with region (hyphen)
            ['en-US', 'English (US)'],
            ['en-GB', 'English (UK)'],
            ['pt-BR', 'Portuguese (Brazil)'],
            ['zh-CN', 'Chinese (Simplified)'],
            ['zh-TW', 'Chinese (Traditional)'],

            // Language with region (underscore)
            ['en_US', 'English (US) underscore'],
            ['pt_BR', 'Portuguese (Brazil) underscore'],

            // Extended subtags
            ['zh-Hans', 'Chinese Simplified script'],
            ['zh-Hant', 'Chinese Traditional script'],
            ['sr-Latn', 'Serbian Latin script'],
            ['sr-Cyrl', 'Serbian Cyrillic script'],
        ];
    }

    public function testSetLanguages_GHSAm8vh_FiltersMultipleMixedCodes(): void
    {
        $input = ['en', '../etc', 'fr', '.*', 'de-DE', 'invalid!', 'es'];
        $this->language->setLanguages($input);
        $languages = $this->language->getLanguages();

        self::assertContains('en', $languages);
        self::assertContains('fr', $languages);
        self::assertContains('de-DE', $languages);
        self::assertContains('es', $languages);

        self::assertNotContains('../etc', $languages);
        self::assertNotContains('.*', $languages);
        self::assertNotContains('invalid!', $languages);
    }

    public function testSetLanguages_GHSAm8vh_HandlesEmptyArray(): void
    {
        $this->language->setLanguages([]);
        $languages = $this->language->getLanguages();

        self::assertIsArray($languages);
        self::assertEmpty($languages);
    }

    public function testSetLanguages_GHSAm8vh_HandlesNumericValues(): void
    {
        // Numeric values cast to string should be filtered as invalid
        $this->language->setLanguages([123, 456]);
        $languages = $this->language->getLanguages();

        // Numeric strings are not valid language codes
        self::assertNotContains('123', $languages);
        self::assertNotContains('456', $languages);
    }

    // =========================================================================
    // GHSA-m8vh-v6r6-w7p6: Regex Delimiter Escaping Test
    // =========================================================================

    public function testGetAvailable_GHSAm8vh_ProperlyEscapesForRegex(): void
    {
        // Set some valid languages
        $this->language->setLanguages(['en', 'fr', 'de']);

        // Get with regex delimiter - should be properly escaped
        $available = $this->language->getAvailable('/');

        // The result should be usable in a regex without breaking
        $pattern = '/^(' . $available . ')$/';

        // This should not throw a preg error
        $result = @preg_match($pattern, 'en');
        self::assertNotFalse($result, 'Pattern should be valid regex');
        self::assertEquals(1, $result, 'Pattern should match "en"');
    }

    // =========================================================================
    // Edge Cases
    // =========================================================================

    public function testSetLanguages_EdgeCase_CaseSensitivity(): void
    {
        // Language codes should preserve case
        $this->language->setLanguages(['EN', 'Fr', 'de-DE', 'PT-br']);
        $languages = $this->language->getLanguages();

        self::assertContains('EN', $languages);
        self::assertContains('Fr', $languages);
        self::assertContains('de-DE', $languages);
        self::assertContains('PT-br', $languages);
    }

    public function testSetLanguages_EdgeCase_MaxLength(): void
    {
        // Test boundary of valid length (2-3 for language, up to 8 for region)
        $this->language->setLanguages(['ab', 'abc', 'ab-12345678', 'abc-12345678']);
        $languages = $this->language->getLanguages();

        self::assertContains('ab', $languages);
        self::assertContains('abc', $languages);
        self::assertContains('ab-12345678', $languages);
        self::assertContains('abc-12345678', $languages);

        // These should be too long
        $this->language->setLanguages(['abcd', 'ab-123456789']);
        $languages = $this->language->getLanguages();

        self::assertNotContains('abcd', $languages, '4-letter language code should be invalid');
        self::assertNotContains('ab-123456789', $languages, '9-char region should be invalid');
    }
}
