<?php

use Codeception\Util\Fixtures;
use Grav\Common\Grav;
use Grav\Framework\Flex\FlexDirectory;

/**
 * An object that comes from the object cache is kept for the rest of the
 * request, like one read from storage. Before, every getObject() of the same
 * key unserialized a fresh copy: a page's parent() and route() ask for the
 * parent once per child, so listing a folder with thousands of children
 * unserialized the folder thousands of times (getgrav/grav-plugin-admin2#200).
 */
class FlexDirectoryCachedObjectReuseTest extends \PHPUnit\Framework\TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        $grav();
    }

    public function testObjectFromTheCacheIsReusedOnTheNextLoad(): void
    {
        $directory = $this->directory();
        $entries = ['docs' => ['key' => 'docs', 'storage_key' => '02.docs']];

        $first = $directory->loadObjects($entries)['docs'] ?? null;
        $second = $directory->loadObjects($entries)['docs'] ?? null;

        self::assertNotNull($first);
        self::assertSame($first, $second, 'the second load returns the object the first one got from the cache');
        self::assertSame(1, $directory->cacheReads, 'the cache is read once, not once per load');
    }

    public function testEachKeyIsReadFromTheCacheOnce(): void
    {
        $directory = $this->directory();

        $directory->loadObjects(['a' => ['key' => 'a', 'storage_key' => '01.a']]);
        $both = $directory->loadObjects([
            'a' => ['key' => 'a', 'storage_key' => '01.a'],
            'b' => ['key' => 'b', 'storage_key' => '02.b'],
        ]);

        self::assertSame(['a', 'b'], array_keys($both));
        self::assertSame([['01.a'], ['02.b']], $directory->fetched, 'only the key not loaded yet goes to the cache');
    }

    /**
     * A pages directory whose object cache hands back a ready object for every
     * key it is asked for, and counts the reads.
     */
    private function directory(): FlexDirectory
    {
        return new class('pages', 'blueprints://flex/pages.yaml', ['enabled' => true]) extends FlexDirectory {
            /** @var int */
            public $cacheReads = 0;

            /** @var list<list<string>> */
            public $fetched = [];

            protected function loadCachedObjects(array $fetch): array
            {
                $this->cacheReads++;
                $this->fetched[] = $fetch;

                $objects = [];
                foreach ($fetch as $storageKey) {
                    $key = preg_replace('/^\d+\./', '', $storageKey);
                    $objects[$storageKey] = $this->createObject(['header' => ['title' => $key]], $key);
                }

                return $objects;
            }
        };
    }
}
