<?php

use Grav\Installer\Install;

class InstallSnapshotManifestTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @dataProvider provideSnapshotManifestCases
     */
    public function testIsSnapshotManifest(array $manifest, bool $expected): void
    {
        $install = Install::instance();
        $method = new ReflectionMethod(Install::class, 'isSnapshotManifest');
        $method->setAccessible(true);

        $actual = $method->invoke($install, $manifest);

        self::assertSame($expected, $actual);
    }

    public static function provideSnapshotManifestCases(): array
    {
        return [
            'valid snapshot prefix' => [[
                'id' => 'snapshot-20260225123000',
                'backup_path' => '/tmp/grav-snapshots/snapshot-20260225123000',
                'entries' => ['system', 'bin'],
            ], true],
            'valid upgrade prefix' => [[
                'id' => 'upgrade-abc123',
                'backup_path' => '/tmp/grav-snapshots/snapshot-upgrade-abc123',
                'entries' => ['system'],
            ], true],
            'valid stage prefix' => [[
                'id' => 'stage-xyz987',
                'backup_path' => '/tmp/grav-snapshots/snapshot-stage-xyz987',
                'entries' => ['index.php'],
            ], true],
            'reject missing id' => [[
                'backup_path' => '/tmp/grav-snapshots/snapshot-noid',
                'entries' => ['system'],
            ], false],
            'reject unknown id prefix' => [[
                'id' => 'manual-1234',
                'backup_path' => '/tmp/grav-snapshots/manual-1234',
                'entries' => ['system'],
            ], false],
            'reject empty backup path' => [[
                'id' => 'snapshot-20260225123001',
                'backup_path' => '',
                'entries' => ['system'],
            ], false],
            'reject missing entries array' => [[
                'id' => 'snapshot-20260225123002',
                'backup_path' => '/tmp/grav-snapshots/snapshot-20260225123002',
            ], false],
            'reject non array entries' => [[
                'id' => 'snapshot-20260225123003',
                'backup_path' => '/tmp/grav-snapshots/snapshot-20260225123003',
                'entries' => 'system',
            ], false],
        ];
    }
}
