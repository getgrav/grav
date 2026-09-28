<?php

namespace Grav\Common;

use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Pass-through for the unqualified chmod() call in Security::writeNonceKey().
 * PHP resolves it to this namespaced function first, which lets the test record
 * the mode the temp key file was created with, before it is tightened.
 */
if (!function_exists(__NAMESPACE__ . '\chmod')) {
    function chmod(string $filename, int $permissions): bool
    {
        NonceKeyFilePermissionsTest::$modesBeforeChmod[$filename] = fileperms($filename) & 0777;

        return \chmod($filename, $permissions);
    }
}

/**
 * The nonce key temp file must never exist with
 * group or other permissions, not even between the write and the chmod().
 */
class NonceKeyFilePermissionsTest extends TestCase
{
    /** @var array<string,int> */
    public static array $modesBeforeChmod = [];

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir() . '/grav-noncekey-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        self::$modesBeforeChmod = [];
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->dir);
        parent::tearDown();
    }

    public function testWriteNonceKeyTempFileIsPrivateFromCreation(): void
    {
        $path = $this->dir . '/security-private.php';
        $previous = umask(0022);
        try {
            $method = new ReflectionMethod(Security::class, 'writeNonceKey');
            $method->setAccessible(true);
            $method->invoke(null, $path, str_repeat('c', 64));

            self::assertSame(0022, umask(), 'the caller umask is restored after the write');
        } finally {
            umask($previous);
        }

        self::assertArrayHasKey($path . '.tmp', self::$modesBeforeChmod);
        self::assertSame(
            0600,
            self::$modesBeforeChmod[$path . '.tmp'],
            'The temp key file must be created 0600, not tightened afterwards'
        );
        self::assertSame(0600, fileperms($path) & 0777);
        self::assertSame(str_repeat('c', 64), include $path);
    }
}
