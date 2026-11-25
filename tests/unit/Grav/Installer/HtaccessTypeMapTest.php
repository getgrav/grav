<?php

class HtaccessTypeMapTest extends \PHPUnit\Framework\TestCase
{
    private const UPDATE = '/system/src/Grav/Installer/updates/2.2.35_2026-11-24_0.php';
    private const BLOCK = "<IfModule mod_mime.c>\n    RemoveHandler .var\n</IfModule>\n";

    /**
     * Debian and Ubuntu Apache ship `AddHandler type-map var`, so both shipped
     * copies must remove that handler.
     */
    public function testShippedHtaccessRemovesTheTypeMapHandler(): void
    {
        foreach (['.htaccess', 'webserver-configs/htaccess.txt'] as $file) {
            self::assertStringContainsString(self::BLOCK, (string) file_get_contents(GRAV_ROOT . '/' . $file), $file);
        }
    }

    public function testUpgradeTurnsThePreviousHtaccessIntoTheShippedOne(): void
    {
        $update = require GRAV_ROOT . self::UPDATE;
        $file = tempnam(sys_get_temp_dir(), 'htaccess');

        try {
            copy(GRAV_ROOT . '/tests/fake/htaccess-2.2.34.txt', $file);
            $update['postflight']($file);
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
            file_put_contents($file, str_replace("\n", "\r\n", (string) file_get_contents(GRAV_ROOT . '/tests/fake/htaccess-2.2.34.txt')));
            $update['postflight']($file);
            $new = (string) file_get_contents($file);
            self::assertSame(str_replace("\n", "\r\n", (string) file_get_contents(GRAV_ROOT . '/.htaccess')), $new);
            self::assertStringNotContainsString("\r\r", $new);
        } finally {
            @unlink($file);
        }
    }

    public function testUpgradeLeavesOtherFilesAlone(): void
    {
        $update = require GRAV_ROOT . self::UPDATE;
        $file = tempnam(sys_get_temp_dir(), 'htaccess');

        try {
            // Already removes the handler, in its own words.
            $own = "RewriteEngine On\nremovehandler .VAR .map\n";
            file_put_contents($file, $own);
            $update['postflight']($file);
            self::assertSame($own, file_get_contents($file));

            // No rewrite engine: the host may not allow FileInfo overrides.
            $plain = "Options -Indexes\n";
            file_put_contents($file, $plain);
            $update['postflight']($file);
            self::assertSame($plain, file_get_contents($file));

            // A file without a final newline gets one before the block.
            file_put_contents($file, 'RewriteEngine On');
            $update['postflight']($file);
            self::assertStringStartsWith("RewriteEngine On\n\n# Begin - Apache type maps\n", (string) file_get_contents($file));
        } finally {
            @unlink($file);
        }
    }
}
