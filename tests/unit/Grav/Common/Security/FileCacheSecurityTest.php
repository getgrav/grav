<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Common\Security;
use Grav\Framework\Cache\Adapter\FileCache;

/**
 * Class FileCacheSecurityTest
 *
 * Covers: GHSA-gwfr-jfjf-92vv (insecure deserialization in FileCache).
 *
 * Verifies the HMAC-integrity wrapper around FileCache's on-disk payloads:
 *   - round-trip with the same key works,
 *   - a tampered payload is treated as a cache miss and the file is removed,
 *   - a pre-v2 file (no version line) is treated as a cache miss and removed,
 *   - a payload signed with a different key is rejected.
 *
 * Naming convention: test{Method}_{GHSA_ID}_{description}
 */
class FileCacheSecurityTest extends \PHPUnit\Framework\TestCase
{
    /** @var Grav */
    protected $grav;

    /** @var string */
    protected $cacheRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $grav = Fixtures::get('grav');
        $this->grav = $grav();

        $this->cacheRoot = sys_get_temp_dir() . '/grav-filecache-sec-' . bin2hex(random_bytes(4));
        @mkdir($this->cacheRoot, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->cacheRoot);
        parent::tearDown();
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = "{$dir}/{$entry}";
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    private function newCache(): FileCache
    {
        return new FileCache('test', 60, $this->cacheRoot);
    }

    private function findCacheFile(): ?string
    {
        $iter = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->cacheRoot, FilesystemIterator::SKIP_DOTS));
        foreach ($iter as $file) {
            if ($file->isFile()) {
                return (string)$file;
            }
        }
        return null;
    }

    // =========================================================================
    // GHSA-gwfr-jfjf-92vv: HMAC integrity on file cache payloads
    // =========================================================================

    public function testGetSet_GHSAgwfr_RoundTripPreservesValue(): void
    {
        $cache = $this->newCache();
        $cache->set('alpha', ['hello' => 'world', 'n' => 42]);

        self::assertSame(['hello' => 'world', 'n' => 42], $cache->get('alpha'));
    }

    public function testGet_GHSAgwfr_RejectsTamperedPayload(): void
    {
        $cache = $this->newCache();
        $cache->set('alpha', 'original-value');

        $file = $this->findCacheFile();
        self::assertNotNull($file, 'cache file should exist after set');

        // Flip a byte inside the serialized payload (last segment after the 4
        // header lines: v2, expires, key, hmac).
        $contents = file_get_contents($file);
        $lines = explode("\n", $contents, 5);
        self::assertCount(5, $lines, 'cache file must have 5 segments');
        $lines[4] = str_replace('original-value', 'taintednvalue', $lines[4]);
        file_put_contents($file, implode("\n", $lines));

        $miss = '__MISS__';
        self::assertSame($miss, $cache->get('alpha', $miss), 'tampered file must be a miss');
        self::assertFileDoesNotExist($file, 'tampered file must be deleted');
    }

    public function testGet_GHSAgwfr_RejectsForgedHmacWithDifferentKey(): void
    {
        $cache = $this->newCache();
        $file = $cache->set('alpha', 'real-value');

        // Reuse the file path. Hand-craft a payload whose HMAC was computed
        // with the wrong key — exactly what an attacker who can write to the
        // cache directory but cannot read user/config/security-private.php
        // would produce.
        $file = $this->findCacheFile();
        $serialized = serialize('attacker-payload');
        $forgedHmac = hash_hmac('sha256', $serialized, 'wrong-key-attacker-guessed');
        $payload = "v2\n" . (time() + 60) . "\n" . rawurlencode('alpha') . "\n" . $forgedHmac . "\n" . $serialized;
        file_put_contents($file, $payload);

        $miss = '__MISS__';
        self::assertSame($miss, $cache->get('alpha', $miss), 'forged HMAC must be a miss');
        self::assertFileDoesNotExist($file, 'forged file must be deleted');
    }

    public function testGet_GHSAgwfr_RejectsPreV2FormatFile(): void
    {
        // Mimic the legacy file format: <expires>\n<key>\n<serialized>
        // No version line, no HMAC. Pre-upgrade caches end up here.
        $cache = $this->newCache();
        $cache->set('alpha', 'placeholder'); // create the file so getFile() path exists

        $file = $this->findCacheFile();
        self::assertNotNull($file);
        $legacy = (time() + 60) . "\n" . rawurlencode('alpha') . "\n" . serialize('legacy-value');
        file_put_contents($file, $legacy);

        $miss = '__MISS__';
        self::assertSame($miss, $cache->get('alpha', $miss), 'pre-v2 file must be a miss');
        self::assertFileDoesNotExist($file, 'pre-v2 file must be deleted');
    }

    public function testGet_GHSAgwfr_RejectsKeyMismatchInPayload(): void
    {
        // The on-disk key field is part of the existing collision check; a
        // valid HMAC over a payload whose key field doesn't match what we
        // asked for must NOT be returned to the caller.
        $cache = $this->newCache();
        $cache->set('alpha', 'a-value');
        $file = $this->findCacheFile();

        $serialized = serialize('a-value');
        $hmac = hash_hmac('sha256', $serialized, Security::getNonceKey());
        $payload = "v2\n" . (time() + 60) . "\n" . rawurlencode('beta') . "\n" . $hmac . "\n" . $serialized;
        file_put_contents($file, $payload);

        $miss = '__MISS__';
        self::assertSame($miss, $cache->get('alpha', $miss), 'key-field mismatch must be a miss');
    }
}
