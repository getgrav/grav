<?php

use Grav\Common\GPM\Installer;

/**
 * Class ZipSlipSecurityTest
 *
 * Covers: GHSA-w48r-jppp-rcfw (malicious plugin/theme ZIP via directInstall).
 * The unZip path now pre-validates every entry name and aborts the install
 * if any look like Zip Slip primitives — `../` traversal, absolute paths,
 * Windows drive letters, NUL bytes, etc. Well-formed entries still extract
 * normally.
 *
 * Note: this test pins the path-layer hardening only. Defending against a
 * well-formed but malicious plugin (whose own PHP is the payload) is a
 * separate "trust the source" problem the admin owns when using
 * directInstall — see the changelog and advisory triage notes.
 *
 * Naming convention: test{Method}_{GHSA_ID}_{description}
 */
class ZipSlipSecurityTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider providerGHSAw48r_DangerousEntryNames
     */
    public function testIsSafeArchiveEntry_GHSAw48r_RejectsDangerousNames(string $name, string $description): void
    {
        self::assertFalse(Installer::isSafeArchiveEntry($name), "Should reject: $description");
    }

    public static function providerGHSAw48r_DangerousEntryNames(): array
    {
        return [
            ['', 'empty entry name'],
            ["foo\0bar", 'NUL byte in name'],
            ['../etc/passwd', 'classic parent-dir traversal'],
            ['plugin/../../etc/passwd', 'embedded traversal'],
            ['plugin/sub/../../../etc', 'deep traversal'],
            ['/etc/passwd', 'absolute Unix path'],
            ['/var/www/html/shell.php', 'absolute web-root path'],
            ['\\windows\\system32', 'absolute Windows-style backslash'],
            ['C:\\windows\\evil.dll', 'Windows drive letter (backslash)'],
            ['c:/windows/evil.dll', 'Windows drive letter (forward-slash, lowercase)'],
            ['plugin\\..\\..\\etc', 'Windows-style backslash traversal'],
            ['..', 'bare ..'],
            ['./../foo', 'mixed ./ and ../'],
        ];
    }

    /**
     * @dataProvider providerGHSAw48r_SafeEntryNames
     */
    public function testIsSafeArchiveEntry_GHSAw48r_AcceptsLegitimateNames(string $name, string $description): void
    {
        self::assertTrue(Installer::isSafeArchiveEntry($name), "Should accept: $description");
    }

    public static function providerGHSAw48r_SafeEntryNames(): array
    {
        return [
            ['plugin/', 'plugin folder root'],
            ['plugin/plugin.php', 'plugin entry file'],
            ['plugin/blueprints.yaml', 'blueprint'],
            ['plugin/templates/index.html.twig', 'nested template'],
            ['plugin/assets/img/logo-1.svg', 'nested static asset with hyphen'],
            ['plugin/sub.dir/file.ext', 'dotted segment that is not ..'],
            ['plugin/.hidden', 'dotfile inside plugin'],
            ['plugin/CHANGELOG.md', 'all-caps + extension'],
        ];
    }
}
