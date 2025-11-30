<?php

use Grav\Common\User\DataUser\User;

/**
 * Class UsernameValidationTest
 *
 * Tests for username validation security fixes.
 * Covers: GHSA-h756-wh59-hhjv (path traversal), GHSA-cjcp-qxvg-4rjm (uniqueness)
 *
 * Naming convention: test{Method}_{GHSA_ID}_{description}
 */
class UsernameValidationTest extends \PHPUnit\Framework\TestCase
{
    // =========================================================================
    // GHSA-h756-wh59-hhjv: Path Traversal in Username Creation
    // =========================================================================

    /**
     * @dataProvider providerGHSAh756_PathTraversalUsernames
     */
    public function testIsValidUsername_GHSAh756_BlocksPathTraversal(string $username, string $description): void
    {
        $result = User::isValidUsername($username);
        self::assertFalse($result, "Should block path traversal: $description");
    }

    public static function providerGHSAh756_PathTraversalUsernames(): array
    {
        return [
            // Basic path traversal attempts
            ['../admin', 'Unix path traversal to parent'],
            ['..\\admin', 'Windows path traversal to parent'],
            ['../../etc/passwd', 'Multiple level traversal'],
            ['..\\..\\windows\\system32', 'Windows multi-level traversal'],

            // Path traversal in middle of username
            ['foo/../bar', 'Traversal in middle'],
            ['foo\\..\\bar', 'Windows traversal in middle'],

            // Encoded and variant attempts
            ['..', 'Just double dots'],
            ['...', 'Triple dots containing double'],

            // Attempts to escape accounts directory
            ['../accounts/admin', 'Escape to accounts directory'],
            ['..\\accounts\\admin', 'Windows escape to accounts'],
            ['../config/system', 'Escape to config directory'],
        ];
    }

    // =========================================================================
    // GHSA-h756-wh59-hhjv: Dangerous Characters in Username
    // =========================================================================

    /**
     * @dataProvider providerGHSAh756_DangerousCharacters
     */
    public function testIsValidUsername_GHSAh756_BlocksDangerousCharacters(string $username, string $description): void
    {
        $result = User::isValidUsername($username);
        self::assertFalse($result, "Should block dangerous character: $description");
    }

    public static function providerGHSAh756_DangerousCharacters(): array
    {
        return [
            // Filesystem dangerous characters
            ['user/name', 'Forward slash'],
            ['user\\name', 'Backslash'],
            ['user?name', 'Question mark'],
            ['user*name', 'Asterisk wildcard'],
            ['user:name', 'Colon'],
            ['user;name', 'Semicolon'],
            ['user{name', 'Opening brace'],
            ['user}name', 'Closing brace'],
            ["user\nname", 'Newline character'],

            // Hidden files (starting with dot)
            ['.htaccess', 'Hidden file .htaccess'],
            ['.env', 'Hidden file .env'],
            ['.gitignore', 'Hidden file .gitignore'],
            ['.hidden', 'Generic hidden file'],
        ];
    }

    // =========================================================================
    // GHSA-cjcp-qxvg-4rjm: Username Uniqueness (Empty Username)
    // =========================================================================

    public function testIsValidUsername_GHSAcjcp_BlocksEmptyUsername(): void
    {
        self::assertFalse(User::isValidUsername(''), 'Empty username should be invalid');
    }

    // =========================================================================
    // Valid Usernames (Should Pass Validation)
    // =========================================================================

    /**
     * @dataProvider providerValidUsernames
     */
    public function testIsValidUsername_AllowsValidUsernames(string $username, string $description): void
    {
        $result = User::isValidUsername($username);
        self::assertTrue($result, "Should allow valid username: $description");
    }

    public static function providerValidUsernames(): array
    {
        return [
            // Standard usernames
            ['admin', 'Simple admin username'],
            ['john_doe', 'Username with underscore'],
            ['john-doe', 'Username with hyphen'],
            ['john.doe', 'Username with single dot (not at start)'],
            ['user123', 'Username with numbers'],
            ['JohnDoe', 'Mixed case username'],

            // Unicode usernames
            ['用户名', 'Chinese characters'],
            ['пользователь', 'Cyrillic characters'],
            ['ユーザー', 'Japanese characters'],
            ['müller', 'German umlaut'],
            ['josé', 'Spanish accent'],

            // Edge cases that should be valid
            ['a', 'Single character'],
            ['ab', 'Two characters'],
            ['user.name.here', 'Multiple dots (not traversal)'],
            ['123456', 'All numbers'],
            ['user_name_with_many_underscores', 'Many underscores'],
        ];
    }

    // =========================================================================
    // Boundary Tests
    // =========================================================================

    public function testIsValidUsername_BoundaryDotPosition(): void
    {
        // Dot at start is invalid (hidden file)
        self::assertFalse(User::isValidUsername('.user'), 'Dot at start should be invalid');

        // Dot in middle is valid
        self::assertTrue(User::isValidUsername('user.name'), 'Dot in middle should be valid');

        // Dot at end is valid
        self::assertTrue(User::isValidUsername('user.'), 'Dot at end should be valid');
    }

    public function testIsValidUsername_BoundaryDoubleDotsPosition(): void
    {
        // Double dots anywhere should be invalid (path traversal)
        self::assertFalse(User::isValidUsername('..user'), 'Double dots at start');
        self::assertFalse(User::isValidUsername('user..name'), 'Double dots in middle');
        self::assertFalse(User::isValidUsername('user..'), 'Double dots at end');
    }

    // =========================================================================
    // Combined Attack Vectors
    // =========================================================================

    /**
     * @dataProvider providerCombinedAttacks
     */
    public function testIsValidUsername_BlocksCombinedAttacks(string $username, string $description): void
    {
        $result = User::isValidUsername($username);
        self::assertFalse($result, "Should block combined attack: $description");
    }

    public static function providerCombinedAttacks(): array
    {
        return [
            ['../../../etc/passwd', 'Deep path traversal'],
            ['..\\..\\..\\windows\\system32\\config\\sam', 'Windows deep traversal'],
            ['./../admin', 'Hidden file + traversal'],
            ['admin/../../../root', 'Valid prefix + deep traversal'],
            ["admin\n../etc/passwd", 'Newline injection + traversal'],
            ['admin;rm -rf /', 'Semicolon command separator'],
            ['admin/etc/passwd', 'Slash in username'],
        ];
    }
}
