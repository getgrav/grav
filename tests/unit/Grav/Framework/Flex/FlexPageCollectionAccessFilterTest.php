<?php

use Codeception\Util\Fixtures;
use Grav\Common\Filesystem\Folder;
use Grav\Common\Flex\Types\Pages\PageCollection;
use Grav\Framework\Flex\FlexDirectory;

/**
 * Flex pages filter a collection by access level the same way regular pages do: a nested rule
 * (`site: {premium: true}`) names the level `site.premium`, its value is not a level.
 */
class FlexPageCollectionAccessFilterTest extends \PHPUnit\Framework\TestCase
{
    /** @var string */
    protected $folder;

    protected function setUp(): void
    {
        parent::setUp();

        $grav = Fixtures::get('grav');
        $grav();

        $this->folder = 'tmp/flex-access-filter-' . uniqid('', false);
    }

    protected function tearDown(): void
    {
        Folder::delete(GRAV_ROOT . '/' . $this->folder);
        parent::tearDown();
    }

    public function testNestedRuleMatchesByItsNameOnly(): void
    {
        $directory = $this->directory([
            'flat-free' => "access:\n    site.free: true\n",
            'nested-free' => "access:\n    site:\n        free: true\n",
            'flat-premium' => "access:\n    site.premium: true\n",
            'nested-premium' => "access:\n    site:\n        premium: true\n",
            'nested-free-denied' => "access:\n    site:\n        free: false\n",
            'single-free' => "access: site.free\n",
            'single-true' => "access: true\n",
            'open' => '',
        ]);

        self::assertSame(['flat-free', 'nested-free', 'single-free'], $this->filter($directory, ['site.free']));
        self::assertSame(['flat-premium', 'nested-premium'], $this->filter($directory, ['site.premium']));
        self::assertSame(['nested-premium'], $this->filter($directory, ['premium']));
        self::assertSame([], $this->filter($directory, ['site.other']));
    }

    /**
     * @param FlexDirectory $directory
     * @param array $levels
     * @return string[] Keys of the matching pages.
     */
    private function filter(FlexDirectory $directory, array $levels): array
    {
        $collection = $directory->getCollection()->ofOneOfTheseAccessLevels($levels);
        self::assertInstanceOf(PageCollection::class, $collection);

        $keys = $collection->getKeys();
        sort($keys);

        return $keys;
    }

    /**
     * @param array<string,string> $pages Frontmatter by page folder.
     * @return FlexDirectory
     */
    private function directory(array $pages): FlexDirectory
    {
        foreach ($pages as $folder => $frontmatter) {
            $path = GRAV_ROOT . "/{$this->folder}/{$folder}";
            mkdir($path, 0777, true);
            file_put_contents("{$path}/default.md", "---\ntitle: Page\n{$frontmatter}---\n");
        }

        return new FlexDirectory('pages', 'blueprints://flex/pages.yaml', [
            'enabled' => true,
            'data' => ['storage' => ['options' => ['folder' => $this->folder, 'indexed' => false]]],
            'object' => ['cache' => ['index' => ['enabled' => false], 'object' => ['enabled' => false], 'render' => ['enabled' => false]]],
        ]);
    }
}
