<?php

class GravSchemaTest extends \PHPUnit\Framework\TestCase
{
    /**
     * A fresh install records GRAV_SCHEMA as the schema it is already at, and an
     * upgrade runs every update script newer than the recorded schema. A stale
     * constant makes a fresh site's first upgrade replay scripts written for
     * older installs.
     */
    public function testSchemaIsTheNewestUpdateScript(): void
    {
        $revisions = array_map(static fn($f) => basename($f, '.php'), glob(GRAV_ROOT . '/system/src/Grav/Installer/updates/*.php') ?: []);
        usort($revisions, 'version_compare');

        self::assertSame(end($revisions), GRAV_SCHEMA, 'Set GRAV_SCHEMA in system/defines.php to the newest file in system/src/Grav/Installer/updates.');
    }
}
