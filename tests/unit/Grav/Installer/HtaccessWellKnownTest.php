<?php

class HtaccessWellKnownTest extends \PHPUnit\Framework\TestCase
{
    private const UPDATE = '/system/src/Grav/Installer/updates/2.2.19_2026-10-31_0.php';
    private const RULE = 'RewriteRule (^|/)\.(?!well-known(?:/|$)) - [F]';
    /** Later updates that also change `.htaccess`, run after this one as an upgrade from 2.2.18 does. */
    private const LATER = ['/system/src/Grav/Installer/updates/2.2.35_2026-11-24_0.php'];

    private function runLaterUpdates(string $file): void
    {
        foreach (self::LATER as $later) {
            (require GRAV_ROOT . $later)['postflight']($file);
        }
    }

    /**
     * The dot-file exception covers the `.well-known` folder only, not every
     * hidden name that starts with `well-known`.
     */
    public function testShippedHtaccessEndsTheWellKnownException(): void
    {
        foreach (['.htaccess', 'webserver-configs/htaccess.txt'] as $file) {
            $contents = (string) file_get_contents(GRAV_ROOT . '/' . $file);
            self::assertStringContainsString(self::RULE . "\n", $contents, $file);
            self::assertStringNotContainsString("(?!well-known) - [F]", $contents, $file);
        }
    }

    public function testRuleDeniesLookalikesAndKeepsWellKnownFiles(): void
    {
        $regex = '`(^|/)\.(?!well-known(?:/|$))`';
        foreach (['.well-known/acme-challenge/token', '.well-known', 'x/.well-known/security.txt'] as $path) {
            self::assertSame(0, preg_match($regex, $path), $path);
        }
        foreach (['.well-knownX/file', '.well-known-backup', '.well-known/.secret', '.env', 'user/.git/config'] as $path) {
            self::assertSame(1, preg_match($regex, $path), $path);
        }
    }

    public function testUpgradeTurnsThePreviousHtaccessIntoTheShippedOne(): void
    {
        $update = require GRAV_ROOT . self::UPDATE;
        $file = tempnam(sys_get_temp_dir(), 'htaccess');

        try {
            copy(GRAV_ROOT . '/tests/fake/htaccess-2.2.18.txt', $file);
            $update['postflight']($file);
            $this->runLaterUpdates($file);
            self::assertSame(file_get_contents(GRAV_ROOT . '/.htaccess'), file_get_contents($file));

            $update['postflight']($file);
            self::assertSame(file_get_contents(GRAV_ROOT . '/.htaccess'), file_get_contents($file), 'A second run changes nothing.');
        } finally {
            @unlink($file);
        }
    }

    public function testUpgradeKeepsWindowsLineEndings(): void
    {
        $update = require GRAV_ROOT . self::UPDATE;
        $file = tempnam(sys_get_temp_dir(), 'htaccess');

        try {
            file_put_contents($file, str_replace("\n", "\r\n", (string) file_get_contents(GRAV_ROOT . '/tests/fake/htaccess-2.2.18.txt')));
            $update['postflight']($file);
            $this->runLaterUpdates($file);
            self::assertSame(str_replace("\n", "\r\n", (string) file_get_contents(GRAV_ROOT . '/.htaccess')), file_get_contents($file));
        } finally {
            @unlink($file);
        }
    }

    public function testUpgradeLeavesAnEditedRuleAlone(): void
    {
        $update = require GRAV_ROOT . self::UPDATE;
        $file = tempnam(sys_get_temp_dir(), 'htaccess');

        try {
            $edited = "RewriteEngine On\nRewriteRule (^|/)\\.(?!well-known) - [F,L]\n";
            file_put_contents($file, $edited);
            $update['postflight']($file);
            self::assertSame($edited, file_get_contents($file));

            $twice = "RewriteRule (^|/)\\.(?!well-known) - [F]\nRewriteRule (^|/)\\.(?!well-known) - [F]\n";
            file_put_contents($file, $twice);
            $update['postflight']($file);
            self::assertSame($twice, file_get_contents($file), 'Only a single unchanged line is replaced.');
        } finally {
            @unlink($file);
        }
    }
}
