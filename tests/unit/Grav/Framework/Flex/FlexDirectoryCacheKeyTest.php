<?php

use Codeception\Util\Fixtures;
use Grav\Framework\Flex\FlexDirectory;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Psr16Cache;

/**
 * A storage key is data (a page folder, a username, a group name). The cache key
 * made of it has to lead back to that same storage key, whatever it contains,
 * including text that looks like an encoded character.
 */
class FlexDirectoryCacheKeyTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        $grav();
    }

    public static function storageKeys(): array
    {
        return [
            ['team/alice'],
            ['team__SLASH__alice'],
            ['bob@example.com'],
            ['bob__AT__example.com'],
            ['a:b\\c'],
            ['a__COLON__b__BSLASH__c'],
            ['50%'],
            ['a%2Fb'],
            ['a(b){c}'],
        ];
    }

    /**
     * @dataProvider storageKeys
     */
    public function testCacheKeyLeadsBackToTheStorageKey(string $storageKey): void
    {
        $directory = $this->directory();
        $cacheKey = $directory->encode($storageKey);

        self::assertSame($storageKey, $directory->decode($cacheKey));
        self::assertFalse(strpbrk($cacheKey, '{}()/\\@:'), 'no reserved character in ' . $cacheKey);
    }

    public function testStorageKeysDoNotShareACacheKey(): void
    {
        $directory = $this->directory();
        $cacheKeys = array_map(static fn (array $row) => $directory->encode($row[0]), self::storageKeys());

        self::assertSame($cacheKeys, array_unique($cacheKeys));
    }

    public function testCacheAnswersUnderTheKeysItWasAskedFor(): void
    {
        $fetch = array_column(self::storageKeys(), 0);

        self::assertSame($fetch, array_keys($this->directory()->cached($fetch)));
    }

    /**
     * A pages directory with an empty Symfony object cache, which opens up the
     * protected methods.
     */
    private function directory(): FlexDirectory
    {
        return new class('pages', 'blueprints://flex/pages.yaml', ['enabled' => true]) extends FlexDirectory {
            public function getCache(?string $namespace = null)
            {
                return $this->cache[$namespace ?: 'index'] ??= new Psr16Cache(new ArrayAdapter());
            }

            public function encode(string $key): string
            {
                return $this->encodeCacheKey($key);
            }

            public function decode(string $key): string
            {
                return $this->decodeCacheKey($key);
            }

            public function cached(array $fetch): array
            {
                return $this->loadCachedObjects($fetch);
            }
        };
    }
}
