<?php

class HtaccessEnvFoldersTest extends \PHPUnit\Framework\TestCase
{
    private const UPDATE = '/system/src/Grav/Installer/updates/2.2.4_2026-09-30_0.php';

    /**
     * A multisite keeps each site in `user/env/<host>/`, so the shipped files must
     * protect `config/`, `accounts/` and `data/` inside an env folder without
     * refusing the whole of `user/env/` (#4335).
     */
    public function testShippedHtaccessProtectsEnvFoldersWithoutBlockingThem(): void
    {
        foreach (['.htaccess', 'webserver-configs/htaccess.txt'] as $file) {
            $contents = (string) file_get_contents(GRAV_ROOT . '/' . $file);
            self::assertStringNotContainsString('^(user)/(config|env)/', $contents, $file);
            self::assertStringContainsString('RewriteRule ^(user)/(env/[^/]+/)?config/(.*) error [F,NC]', $contents, $file);
            self::assertStringContainsString('RewriteRule ^(user)/(env/[^/]+/)?accounts/(.*) error [F,NC]', $contents, $file);
            self::assertStringContainsString('RewriteRule ^(user)/(env/[^/]+/)?data/(.*) error [F,NC]', $contents, $file);
        }
    }

    public function testUpgradeTurnsThePreviousHtaccessIntoTheShippedOne(): void
    {
        $update = require GRAV_ROOT . self::UPDATE;
        $file = tempnam(sys_get_temp_dir(), 'htaccess');

        try {
            copy(GRAV_ROOT . '/tests/fake/htaccess-2.2.3.txt', $file);
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
            $old = str_replace("\n", "\r\n", (string) file_get_contents(GRAV_ROOT . '/tests/fake/htaccess-2.2.3.txt'));
            file_put_contents($file, $old);
            $update['postflight']($file);
            self::assertSame(str_replace("\n", "\r\n", (string) file_get_contents(GRAV_ROOT . '/.htaccess')), file_get_contents($file));
        } finally {
            @unlink($file);
        }
    }

    /**
     * Loosening the env rule without the matching accounts and data rules would
     * open those folders, so a file where any one line was edited is left alone.
     */
    public function testUpgradeLeavesACustomisedHtaccessAlone(): void
    {
        $update = require GRAV_ROOT . self::UPDATE;
        $file = tempnam(sys_get_temp_dir(), 'htaccess');

        try {
            $edited = str_replace(
                'RewriteRule ^(user)/data/(.*) error [F,NC]',
                'RewriteRule ^(user)/data/(.*) - [F,NC]',
                (string) file_get_contents(GRAV_ROOT . '/tests/fake/htaccess-2.2.3.txt')
            );
            file_put_contents($file, $edited);
            $update['postflight']($file);
            self::assertSame($edited, file_get_contents($file));
        } finally {
            @unlink($file);
        }
    }
}
