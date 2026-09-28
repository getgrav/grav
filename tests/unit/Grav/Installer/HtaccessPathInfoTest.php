<?php

class HtaccessPathInfoTest extends \PHPUnit\Framework\TestCase
{
    private const RULE = "RewriteCond %{REQUEST_FILENAME} -f\nRewriteCond %{PATH_INFO} ^/\nRewriteRule ^(images|assets|system|vendor|user)/ error [F,NC]\n";

    /**
     * Apache runs `/images/file.php/x` as `file.php` with PATH_INFO `/x`, which the
     * end-anchored file-type rules never match, so both shipped copies must carry
     * the rule that refuses a file with a path after it.
     */
    public function testShippedHtaccessRefusesAPathAfterAFile(): void
    {
        foreach (['.htaccess', 'webserver-configs/htaccess.txt'] as $file) {
            self::assertStringContainsString(self::RULE, (string) file_get_contents(GRAV_ROOT . '/' . $file), $file);
        }
    }

    public function testUpgradeAddsTheRuleOnceAfterTheUserRule(): void
    {
        $update = require GRAV_ROOT . '/system/src/Grav/Installer/updates/2.2.2_2026-09-28_1.php';
        $file = tempnam(sys_get_temp_dir(), 'htaccess');

        try {
            $old = "RewriteEngine On\r\n"
                . "RewriteRule ^(user)/(.*)\\.(txt|md|php)$ error [F,NC]\r\n"
                . "RewriteRule ^(LICENSE\\.txt)$ error [F,NC]\r\n";
            file_put_contents($file, $old);

            $update['postflight']($file);
            $new = (string) file_get_contents($file);
            self::assertStringContainsString(str_replace("\n", "\r\n", self::RULE) . "RewriteRule ^(LICENSE", $new);
            self::assertStringNotContainsString("\r\r", $new);

            $update['postflight']($file);
            self::assertSame($new, file_get_contents($file), 'A second run must not add the rule again.');

            $edited = "RewriteEngine On\n";
            file_put_contents($file, $edited);
            $update['postflight']($file);
            self::assertSame($edited, file_get_contents($file), 'A file without the anchor rule is left alone.');
        } finally {
            @unlink($file);
        }
    }
}
